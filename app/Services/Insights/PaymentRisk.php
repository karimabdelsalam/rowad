<?php

namespace App\Services\Insights;

use App\Enums\ContractStatus;
use App\Models\Client;
use App\Models\Installment;
use App\Models\RentContract;
use App\Models\SaleContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * How likely a tenant is to pay late, from their own history with this office — a transparent score
 * (0–100) with the reasons behind it, so staff can act early instead of chasing arrears.
 */
class PaymentRisk
{
    /** Paying within this many days of the due date still counts as on time. */
    public const GRACE_DAYS = 5;

    public const LEVELS = ['low' => 'منخفض', 'medium' => 'متوسط', 'high' => 'مرتفع', 'new' => 'سجل غير كافٍ'];

    /** @return array{score: int, level: string, label: string, reasons: list<string>, stats: array<string, int|float>} */
    public function forClient(Client $client): array
    {
        $installments = Installment::query()->where('type', '!=', 'expenses')->whereDate('due_date', '<=', today())
            ->whereHasMorph('contract', [RentContract::class, SaleContract::class],
                fn (Builder $q) => $q->whereHas('clients', fn (Builder $q) => $q->whereKey($client->getKey())))
            ->get(['id', 'due_date', 'amount', 'paid_amount', 'is_collected', 'collected_at']);

        return $this->score($installments);
    }

    /** @param  Collection<int, Installment>  $installments  past-due instalments of one client */
    public function score(Collection $installments): array
    {
        $today = today();
        $due = $installments->count();
        $lateDays = [];
        $late = 0;
        // unpaid beyond the grace period; a payment a few days late is not yet a sign of anything
        $overdue = $installments->filter(fn (Installment $i) => ! $i->is_collected && $i->due_date->copy()->addDays(self::GRACE_DAYS)->lt($today));
        foreach ($installments as $i) {
            $days = $i->is_collected
                ? max(0, (int) $i->due_date->diffInDays(($i->collected_at ?? $i->due_date)->copy()->startOfDay(), false))
                : max(0, (int) $i->due_date->diffInDays($today, false));
            $lateDays[] = $days;
            if ($days > self::GRACE_DAYS) {
                $late++;
            }
        }
        $overdueAmount = round((float) $overdue->sum(fn (Installment $i) => (float) $i->amount - (float) $i->paid_amount), 2);
        $oldest = $overdue->isEmpty() ? 0 : (int) $overdue->min('due_date')->diffInDays($today);
        $partials = $overdue->filter(fn (Installment $i) => (float) $i->paid_amount > 0)->count();
        $avgLate = $due ? round(array_sum($lateDays) / $due, 1) : 0.0;
        $lateRatio = $due ? $late / $due : 0.0;

        $stats = ['past_due' => $due, 'late' => $late, 'avg_days_late' => $avgLate, 'overdue_now' => $overdue->count(),
            'overdue_amount' => $overdueAmount, 'oldest_overdue_days' => $oldest, 'partials' => $partials];

        if ($due < 2 && $overdue->isEmpty()) {
            return ['score' => 0, 'level' => 'new', 'label' => __(self::LEVELS['new']), 'reasons' => [__('لا توجد دفعات مستحقة كافية للحكم بعد.')], 'stats' => $stats];
        }

        $score = 0.0;
        $reasons = [];
        if ($overdue->isNotEmpty()) {
            $score += 30 + min(30, $oldest) / 30 * 20;
            $reasons[] = trans_choice('{1} عليه دفعة متأخرة الآن منذ :days يومًا|[2,*] عليه :count دفعات متأخرة الآن، أقدمها منذ :days يومًا', $overdue->count(), ['count' => $overdue->count(), 'days' => $oldest]);
        }
        if ($late > 0) {
            $score += $lateRatio * 35;
            $reasons[] = __('تأخر في :late من :due دفعات سابقة (أكثر من :grace أيام).', ['late' => $late, 'due' => $due, 'grace' => self::GRACE_DAYS]);
        }
        if ($avgLate > 0) {
            $score += min(30, $avgLate) / 30 * 10;
            if ($avgLate >= 3) {
                $reasons[] = __('متوسط التأخير :days يومًا.', ['days' => $avgLate]);
            }
        }
        if ($partials > 0) {
            $score += min(2, $partials) * 2.5;
            $reasons[] = __('سدّد جزئيًا :count دفعة دون إكمالها.', ['count' => $partials]);
        }
        if ($reasons === []) {
            $reasons[] = __('يسدد في موعده — :due دفعات بلا تأخير.', ['due' => $due]);
        }
        $score = (int) round(min(100, $score));
        $level = $score >= 60 ? 'high' : ($score >= 30 ? 'medium' : 'low');

        return ['score' => $score, 'level' => $level, 'label' => __(self::LEVELS[$level]), 'reasons' => $reasons, 'stats' => $stats];
    }

    /**
     * Tenants of active contracts ranked by risk, highest first (medium and high only).
     *
     * @return list<array{client: Client, score: int, level: string, label: string, reasons: list<string>, stats: array}>
     */
    public function atRisk(int $limit = 10): array
    {
        $installments = Installment::query()->where('type', '!=', 'expenses')->whereDate('due_date', '<=', today())
            ->whereHasMorph('contract', [RentContract::class, SaleContract::class], fn (Builder $q) => $q->where('status', ContractStatus::Active))
            ->with('contract.clients')
            ->get(['id', 'contract_type', 'contract_id', 'due_date', 'amount', 'paid_amount', 'is_collected', 'collected_at']);

        $byClient = [];
        $clients = [];
        foreach ($installments as $i) {
            foreach ($i->contract?->clients ?? [] as $client) {
                $byClient[$client->id][] = $i;
                $clients[$client->id] = $client;
            }
        }

        return collect($byClient)
            ->map(fn (array $rows, int $id) => ['client' => $clients[$id]] + $this->score(collect($rows)))
            ->filter(fn (array $r) => in_array($r['level'], ['medium', 'high'], true))
            ->sortByDesc('score')->take($limit)->values()->all();
    }
}
