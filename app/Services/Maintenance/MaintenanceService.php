<?php

namespace App\Services\Maintenance;

use App\Enums\MaintenanceStatus as S;
use App\Enums\NotificationEvent as E;
use App\Exceptions\BusinessRuleException;
use App\Models\Client;
use App\Models\MaintenanceRequest;
use App\Models\Owner;
use App\Models\RentContract;
use App\Models\Unit;
use App\Models\User;
use App\Services\DocumentNumberGenerator;
use App\Services\Finance\VoucherService;
use App\Services\Messaging\Notifier;
use App\Support\Portal;
use App\Support\Settings;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Facades\DB;

/**
 * The life of a maintenance request: reported → (owner approves the estimate) → scheduled with a technician
 * → in progress → completed, when the bill becomes a payment voucher charged to the owners (by expenses
 * share), the tenant's contract (an expenses instalment) or the office. Tenants hear about every step.
 */
class MaintenanceService
{
    public function __construct(
        private readonly VoucherService $vouchers,
        private readonly Notifier $notifier,
        private readonly DocumentNumberGenerator $numbers,
    ) {}

    /** A new request from staff, or from a tenant for one of their own units. */
    public function open(array $data, ?Client $reporter = null): MaintenanceRequest
    {
        $unit = Unit::findOrFail($data['unit_id'] ?? null);
        if ($reporter !== null && ! Portal::clientUnits($reporter)->whereKey($unit->id)->exists()) {
            throw new BusinessRuleException(__('اختر وحدة من وحداتك'));
        }
        $contract = $unit->current_contract_type === (new RentContract)->getMorphClass()
            ? RentContract::with('clients')->find($unit->current_contract_id) : null;

        $request = MaintenanceRequest::create([
            'code' => $this->numbers->next('maintenance'),
            'unit_id' => $unit->id,
            'rent_contract_id' => $contract?->id,
            'client_id' => $reporter?->id ?? ($data['client_id'] ?? null) ?? $contract?->clients->first()?->id,
            'category' => $data['category'] ?? 'other',
            'priority' => $data['priority'] ?? 'normal',
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'preferred_time' => $data['preferred_time'] ?? null,
            'status' => S::New,
            'created_by' => $reporter === null ? auth()->id() : null,
        ]);

        if ($reporter !== null) {
            $this->alertStaff(__('طلب صيانة جديد :code', ['code' => $request->code]), $unit->title.' — '.$request->title, $request->priority->value !== 'normal');
        }

        return $request;
    }

    /** Book the technician; above the office's limit the owners approve the estimate first. */
    public function schedule(MaintenanceRequest $request, array $data): MaintenanceRequest
    {
        $this->ensureOpen($request);
        $request->fill(array_intersect_key($data, array_flip(['technician_name', 'technician_phone', 'scheduled_at', 'assigned_to', 'estimated_cost'])));

        $limit = Settings::get('maintenance_approval_limit');
        $needsApproval = filled($limit) && (float) $limit > 0 && (float) $request->estimated_cost >= (float) $limit
            && $request->owner_decision !== 'approved' && $request->unit->owners()->exists();

        if ($needsApproval) {
            $request->forceFill(['status' => S::AwaitingApproval, 'owner_decision' => null])->save();
            $this->askOwners($request);
        } else {
            $request->forceFill(['status' => S::Scheduled])->save();
            $this->tellTenant($request, $this->visitDetails($request));
        }

        return $request;
    }

    public function decide(MaintenanceRequest $request, Owner $owner, bool $approve, ?string $note = null): MaintenanceRequest
    {
        if ($request->status !== S::AwaitingApproval) {
            throw new BusinessRuleException(__('لا ينتظر هذا الطلب موافقة'));
        }
        if (! $request->unit()->whereHas('owners', fn ($q) => $q->whereKey($owner->id))->exists()) {
            throw new BusinessRuleException(__('هذه الوحدة ليست من أملاكك'));
        }

        $request->forceFill([
            'owner_decision' => $approve ? 'approved' : 'rejected',
            'owner_decided_by' => $owner->id,
            'owner_decided_at' => now(),
            'owner_note' => $note,
            // a refusal goes back to the office to find a cheaper option or bill someone else
            'status' => $approve ? S::Scheduled : S::New,
        ])->save();

        $this->alertStaff(
            ($approve ? __('وافق المالك على صيانة :code', ['code' => $request->code]) : __('رفض المالك صيانة :code', ['code' => $request->code])),
            $owner->full_name.($note ? ' — '.$note : ''),
            ! $approve,
        );
        if ($approve) {
            $this->tellTenant($request, $this->visitDetails($request));
        }

        return $request;
    }

    public function start(MaintenanceRequest $request): MaintenanceRequest
    {
        $this->ensureOpen($request);
        if ($request->status === S::AwaitingApproval) {
            throw new BusinessRuleException(__('بانتظار موافقة المالك على التكلفة'));
        }
        $request->forceFill(['status' => S::InProgress])->save();
        $this->tellTenant($request, '');

        return $request;
    }

    /**
     * @param  array{cost?: float|string|null, charge_to?: ?string, resolution?: ?string, payment_method?: ?string, bank_account_id?: ?int}  $data
     */
    public function complete(MaintenanceRequest $request, array $data): MaintenanceRequest
    {
        $this->ensureOpen($request);
        if ($request->status === S::AwaitingApproval) {
            throw new BusinessRuleException(__('بانتظار موافقة المالك على التكلفة'));
        }
        $cost = round((float) ($data['cost'] ?? 0), 2);
        $chargeTo = $cost > 0 ? ($data['charge_to'] ?? MaintenanceRequest::CHARGE_OFFICE) : null;
        if ($chargeTo !== null && ! array_key_exists($chargeTo, MaintenanceRequest::chargeOptions())) {
            throw new BusinessRuleException(__('اختر على من تُحمّل التكلفة'));
        }

        DB::transaction(function () use ($request, $data, $cost, $chargeTo) {
            $voucher = $cost > 0 ? $this->vouchers->createPayment(array_filter([
                'issued_at' => today()->toDateString(),
                'party_name' => $request->technician_name ?: __('فني صيانة'),
                'unit_id' => $request->unit_id,
                'amount' => $cost,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'reason' => __('صيانة: :title (طلب :code)', ['title' => $request->title, 'code' => $request->code]),
            ], fn ($v) => $v !== null), $chargeTo === MaintenanceRequest::CHARGE_OFFICE ? VoucherService::CHARGE_NONE : $chargeTo) : null;

            $request->forceFill([
                'status' => S::Completed,
                'completed_at' => now(),
                'cost' => $cost > 0 ? $cost : null,
                'charge_to' => $chargeTo,
                'payment_voucher_id' => $voucher?->id,
                'resolution' => $data['resolution'] ?? null,
            ])->save();
        });

        $this->tellTenant($request, __('يسعدنا تقييمكم للخدمة من بوابة العملاء.'));

        return $request;
    }

    public function cancel(MaintenanceRequest $request, string $reason): MaintenanceRequest
    {
        $this->ensureOpen($request);
        $request->forceFill(['status' => S::Cancelled, 'cancel_reason' => $reason])->save();
        $this->tellTenant($request, $reason);

        return $request;
    }

    /** The tenant who reported it rates the finished job once. */
    public function rate(MaintenanceRequest $request, Client $client, int $rating, ?string $feedback = null): MaintenanceRequest
    {
        if ($request->status !== S::Completed || $request->client_id !== $client->id || $request->rating !== null) {
            throw new BusinessRuleException(__('لا يمكن تقييم هذا الطلب'));
        }
        $request->forceFill(['rating' => max(1, min(5, $rating)), 'feedback' => $feedback])->save();
        if ($rating <= 2) {
            $this->alertStaff(__('تقييم منخفض لصيانة :code', ['code' => $request->code]), str_repeat('★', $rating).' '.$feedback, true);
        }

        return $request;
    }

    private function ensureOpen(MaintenanceRequest $request): void
    {
        if (! $request->status->isOpen()) {
            throw new BusinessRuleException(__('الطلب مغلق'));
        }
    }

    private function visitDetails(MaintenanceRequest $request): string
    {
        return trim(implode(' — ', array_filter([
            $request->technician_name ? __('الفني: :name', ['name' => $request->technician_name]) : null,
            $request->technician_phone,
            $request->scheduled_at ? __('الموعد: :at', ['at' => $request->scheduled_at->format('Y-m-d H:i')]) : null,
        ])));
    }

    private function tellTenant(MaintenanceRequest $request, string $details): void
    {
        $client = $request->client;
        if ($client === null) {
            return;
        }
        $this->notifier->send(E::MaintenanceUpdate, [
            'code' => $request->code,
            'unit' => $request->unit->title,
            'title' => $request->title,
            'status' => $request->status->getLabel(),
            'details' => $details,
        ], [['email' => $client->email, 'mobile' => $client->mobile, 'name' => $client->displayName()]]);
    }

    private function askOwners(MaintenanceRequest $request): void
    {
        $this->notifier->send(E::MaintenanceApproval, [
            'code' => $request->code,
            'unit' => $request->unit->title,
            'title' => $request->title,
            'amount' => number_format((float) $request->estimated_cost, 2).' '.Settings::currencyLabel(),
            'login_url' => url('/owner'),
        ], $request->unit->owners()->get()->map(fn (Owner $o) => ['email' => $o->email, 'mobile' => $o->mobile, 'name' => $o->full_name])->all());
    }

    private function alertStaff(string $title, string $body, bool $important): void
    {
        $staff = User::query()->where('type', 'staff')->where('is_active', true)->get()
            ->filter(fn (User $u) => $u->is_super_admin || $u->can('maintenance_requests.assign'));
        if ($staff->isEmpty()) {
            return;
        }
        $notification = FilamentNotification::make()->title($title)->body($body)->icon('heroicon-o-wrench-screwdriver');
        ($important ? $notification->warning() : $notification->info())->sendToDatabase($staff);
    }
}
