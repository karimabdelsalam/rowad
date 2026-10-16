<?php

namespace App\Services\Collection;

use App\Enums\ContractStatus;
use App\Enums\InstallmentType;
use App\Enums\LetterType;
use App\Enums\NotificationEvent as E;
use App\Models\Client;
use App\Models\Installment;
use App\Models\Letter;
use App\Models\RentContract;
use App\Models\Task;
use App\Models\User;
use App\Services\Messaging\DomainNotifications;
use App\Support\Hijri;
use App\Support\Settings;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The collection ladder for late rent, run daily when the office turns it on:
 *   1. a few days late → a polite late notice (with the pay link when e-collection is on);
 *   2. later → a firmer second notice, and a follow-up task for the staff member in charge;
 *   3. later still → a formal warning letter (one per contract, listing every late instalment), ready to
 *      print and send, and the tenant is told a warning was issued.
 * An instalment climbs at most one step per day, so turning the ladder on never fires a warning out of the blue.
 */
class CollectionEscalation
{
    public const STAGES = [1 => 'تنبيه أول', 2 => 'تنبيه ثانٍ ومتابعة', 3 => 'إنذار رسمي'];

    public const DEFAULT_DAYS = [1 => 3, 2 => 10, 3 => 20];

    public function __construct(private readonly DomainNotifications $notifications) {}

    public static function enabled(): bool
    {
        return Settings::enabled('collection_escalation_enabled');
    }

    /** Days late before each stage, from the office's settings. @return array<int, int> */
    public static function days(): array
    {
        return collect(self::DEFAULT_DAYS)->map(fn (int $default, int $stage) => max(1, (int) (Settings::get("collection_stage{$stage}_days") ?: $default)))->all();
    }

    /** @return array{notices: int, tasks: int, warnings: int} */
    public function run(): array
    {
        $done = ['notices' => 0, 'tasks' => 0, 'warnings' => 0];
        if (! self::enabled()) {
            return $done;
        }
        $days = self::days();

        $late = Installment::query()->with(['unit', 'contract.clients'])
            ->where('is_collected', false)->where('type', '!=', InstallmentType::Expenses->value)
            ->where('contract_type', (new RentContract)->getMorphClass())
            ->where('escalation_stage', '<', 3)
            ->whereDate('due_date', '<=', today()->subDays($days[1]))
            ->whereHasMorph('contract', [RentContract::class], fn ($q) => $q->where('status', ContractStatus::Active))
            ->orderBy('due_date')->get();

        $warnings = collect();
        foreach ($late as $installment) {
            $daysLate = (int) $installment->due_date->diffInDays(today());
            $next = $installment->escalation_stage + 1;
            if ($daysLate < $days[$next]) {
                continue;
            }
            match ($next) {
                1 => $this->notifications->installmentReminder(E::RentOverdue, $installment),
                2 => $this->secondNotice($installment, $done),
                3 => $warnings->push($installment),
            };
            if ($next < 3) {
                $installment->forceFill(['escalation_stage' => $next, 'escalated_at' => now()])->save();
                $done['notices']++;
            }
        }

        foreach ($warnings->groupBy('contract_id') as $installments) {
            $this->warn($installments);
            $done['warnings']++;
        }

        return $done;
    }

    /** Issue the formal warning now for a contract's late instalments (also from the instalment screen). */
    public function warn(Collection $installments): Letter
    {
        $installments = (new \Illuminate\Database\Eloquent\Collection($installments->values()->all()))->loadMissing(['unit', 'contract.clients']);
        /** @var Installment $first */
        $first = $installments->first();
        $contract = $first->contract;
        $tenant = $contract->clients->first();

        $letter = DB::transaction(function () use ($installments, $first, $contract, $tenant) {
            $letter = Letter::create([
                'type' => LetterType::Official,
                'calendar' => $contract->calendar?->value ?? 'gregorian',
                'letter_date' => today(),
                'unit_id' => $first->unit_id,
                'client_id' => $tenant?->id,
                'created_by' => auth()->id(),
                'data' => ['official' => [
                    'sendto' => 'person',
                    'sendto_name' => $tenant?->displayName(),
                    'sendto_title' => __('المحترم'),
                    'content' => self::letterBody($contract, $installments),
                ]],
            ]);
            Installment::whereKey($installments->modelKeys())->update(['escalation_stage' => 3, 'escalated_at' => now(), 'warning_letter_id' => $letter->id]);

            return $letter;
        });

        $this->notifications->installmentReminder(E::RentWarning, $first, [
            'amount' => number_format($installments->sum(fn (Installment $i) => $i->remainingAmount()), 2).' '.Settings::currencyLabel(),
        ]);
        $this->alertStaff(__('صدر إنذار رسمي للمستأجر :name', ['name' => $tenant?->displayName()]),
            __(':unit — عقد :number', ['unit' => $first->unit?->title, 'number' => $contract->number]));

        return $letter;
    }

    private function secondNotice(Installment $installment, array &$done): void
    {
        $this->notifications->installmentReminder(E::RentOverdueSecond, $installment);
        $tenant = $installment->contract?->clients->first();
        Task::create([
            'created_by' => null,
            'assigned_to' => $this->defaultAssignee(),
            'due_date' => today()->addDays(2),
            'content' => __('متابعة سداد متأخر: :tenant — :unit — :amount (متأخر :days يومًا) :mobile', [
                'tenant' => $tenant?->displayName(), 'unit' => $installment->unit?->title,
                'amount' => number_format($installment->remainingAmount(), 2), 'days' => (int) $installment->due_date->diffInDays(today()),
                'mobile' => $tenant?->mobile,
            ]),
        ]);
        $done['tasks']++;
    }

    private static function letterBody(RentContract $contract, Collection $installments): string
    {
        $calendar = $contract->calendar?->value;
        $date = fn ($d) => $calendar === 'hijri' ? Hijri::toString($d) : $d->format('Y-m-d');
        $rows = $installments->map(fn (Installment $i) => '<tr><td>'.e($date($i->due_date)).'</td><td>'.number_format($i->remainingAmount(), 2)
            .'</td><td>'.(int) $i->due_date->diffInDays(today()).'</td></tr>')->join('');
        $total = number_format($installments->sum(fn (Installment $i) => $i->remainingAmount()), 2);

        return '<p>'.e(__('الموضوع: إنذار بسداد الأجرة المتأخرة')).'</p>'
            .'<p>'.e(__('إشارة إلى عقد الإيجار رقم :number للوحدة :unit، نفيدكم بأن الأقساط التالية لم تُسدد حتى تاريخه:', [
                'number' => $contract->number, 'unit' => $contract->unit?->title,
            ])).'</p>'
            .'<table class="grid"><thead><tr><th>'.e(__('تاريخ الاستحقاق')).'</th><th>'.e(__('المبلغ المتبقي')).'</th><th>'.e(__('أيام التأخير')).'</th></tr></thead><tbody>'.$rows.'</tbody></table>'
            .'<p><strong>'.e(__('الإجمالي المستحق: :total :currency', ['total' => $total, 'currency' => Settings::currencyLabel()])).'</strong></p>'
            .'<p>'.e(__('وعليه نطالبكم بسداد كامل المبلغ خلال (7) أيام من تاريخ هذا الإنذار، وإلا سنضطر آسفين لاتخاذ الإجراءات النظامية وفق بنود العقد والأنظمة المعمول بها، بما في ذلك تسجيل التعثر في منصة إيجار والمطالبة أمام الجهات المختصة.')).'</p>';
    }

    private function defaultAssignee(): ?int
    {
        return User::query()->where('type', 'staff')->where('is_active', true)->where('is_super_admin', true)->value('id')
            ?? User::query()->where('type', 'staff')->where('is_active', true)->value('id');
    }

    private function alertStaff(string $title, string $body): void
    {
        $staff = User::query()->where('type', 'staff')->where('is_active', true)->get()
            ->filter(fn (User $u) => $u->is_super_admin || $u->can('installments.collect'));
        if ($staff->isNotEmpty()) {
            FilamentNotification::make()->title($title)->body($body)->icon('heroicon-o-exclamation-triangle')->danger()->sendToDatabase($staff);
        }
    }
}
