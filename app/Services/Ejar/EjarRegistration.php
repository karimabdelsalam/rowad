<?php

namespace App\Services\Ejar;

use App\Enums\ClientKind;
use App\Enums\EjarStatus;
use App\Enums\UnitUsage;
use App\Models\Client;
use App\Models\Owner;
use App\Models\RentContract;
use App\Models\UnitMeter;
use Illuminate\Support\Facades\Log;

/**
 * Registers a rent contract on Ejar through the office's own API account, step by step as the ECRS
 * specification orders it: draft → unit → parties → unit services → submit. Each finished step is kept in
 * ejar_progress, so after a rejection (a missing ID, an unverified tenant…) the office fixes the record and
 * sends again from the failed step without creating a second draft. Once submitted, Ejar asks every party
 * to approve; the hourly sync (or "refresh") marks the contract documented when Ejar registers it.
 */
final class EjarRegistration
{
    public const STEPS = ['contract', 'unit', 'parties', 'services', 'submitted'];

    /** Ejar contract states that mean the contract is registered / in force. */
    private const DOCUMENTED_STATES = ['registered', 'active', 'approved', 'documented', 'renewed'];

    /** States after which the draft is dead (a new one has to be created). */
    private const CLOSED_STATES = ['archived', 'rejected', 'cancelled', 'canceled', 'terminated', 'expired'];

    public function __construct(private EjarApi $api) {}

    public static function make(): self
    {
        return new self(new EjarApi);
    }

    /** @return list<string> what stops this contract from being sent (empty = ready) */
    public static function problems(RentContract $contract): array
    {
        $contract->loadMissing(['unit', 'clients']);
        $problems = [];
        if (! EjarApi::connected()) {
            $problems[] = __('اربط حساب المكتب بمنصة إيجار من صفحة "منصة إيجار" في الإعدادات.');
        }
        if ($contract->usage === UnitUsage::Commercial) {
            $problems[] = __('واجهة إيجار للأنظمة الخارجية تدعم العقود السكنية فقط حاليًا. وثّق العقد التجاري من منصة إيجار مباشرة.');
        }
        if (in_array($contract->ejar_status, [EjarStatus::Documented, EjarStatus::NotRequired], true)) {
            $problems[] = __('العقد موثق أو لا يلزم توثيقه.');
        }
        if ($contract->start_date === null || $contract->end_date === null || $contract->end_date->lte($contract->start_date)) {
            $problems[] = __('حدد تاريخي بداية ونهاية صحيحين للعقد.');
        } elseif ($contract->start_date->lte(today()) && blank($contract->ejar_contract_id)) {
            // ECRS: "contract_start_date … must be in the future"
            $problems[] = __('إيجار يقبل عبر الربط العقود التي تبدأ بعد اليوم فقط. لعقد بدأ بالفعل استخدم "ربط بعقد موجود في إيجار" أو وثّقه من المنصة.');
        }
        if (blank($contract->unit?->ejar_property_id) || blank($contract->unit?->ejar_unit_id)) {
            $problems[] = __('اربط الوحدة بعقارها في إيجار أولًا (زر "ربط بإيجار" في صفحة الوحدة).');
        }
        foreach ($contract->clients as $client) {
            if ($client->kind === ClientKind::Company && $client->company_registration_date === null) {
                $problems[] = __('أدخل تاريخ قيد السجل التجاري للمستأجر :name.', ['name' => $client->displayName()]);
            }
        }

        return [...$problems, ...EjarSheet::missing($contract)];
    }

    public function register(RentContract $contract): void
    {
        $contract->loadMissing(['clients', 'unit.owners', 'unit.meters.meterType']);
        $progress = (array) ($contract->ejar_progress ?? []);

        try {
            if (empty($progress['contract']) || blank($contract->ejar_contract_id)) {
                $draft = $this->api->createContract($contract->start_date->format('Y-m-d'), $contract->end_date->format('Y-m-d'));
                $contract->forceFill(['ejar_contract_id' => $draft['id'], 'ejar_number' => $draft['contract_number'] ?? $contract->ejar_number, 'ejar_version' => $draft['version']]);
                $progress = ['contract' => now()->toIso8601String()];
                $this->save($contract, $progress);
            }
            $id = (string) $contract->ejar_contract_id;

            if (empty($progress['unit'])) {
                $progress['contract_unit_id'] = $this->api->setUnit($id, (string) $contract->unit->ejar_property_id, (string) $contract->unit->ejar_unit_id);
                $progress['unit'] = now()->toIso8601String();
                $this->save($contract, $progress);
            }

            if (empty($progress['parties'])) {
                $this->api->addParties($id, $this->parties($contract));
                $progress['parties'] = now()->toIso8601String();
                $this->save($contract, $progress);
            }

            if (empty($progress['services'])) {
                if (($services = $this->services($contract, $progress['contract_unit_id'] ?? null)) !== []) {
                    $this->api->setUnitServices($id, $services);
                }
                $progress['services'] = now()->toIso8601String();
                $this->save($contract, $progress);
            }

            $this->api->submit($id);
            $progress['submitted'] = now()->toIso8601String();
            $contract->forceFill(['ejar_status' => EjarStatus::AwaitingParties, 'ejar_state' => 'submitted', 'ejar_error' => null, 'ejar_synced_at' => now()]);
            $this->save($contract, $progress);
        } catch (EjarException $e) {
            $contract->forceFill(['ejar_error' => trim($e->getMessage().($e->messages ? "\n".implode("\n", $e->messages) : ''))]);
            $this->save($contract, $progress);
            Log::info('Ejar registration stopped', ['contract' => $contract->id, 'step' => $this->nextStep($progress), 'status' => $e->status]);

            throw $e;
        }
    }

    /** Asks Ejar where the submitted contract stands; marks it documented once Ejar registers it. */
    public function refresh(RentContract $contract): ?string
    {
        if (blank($contract->ejar_number)) {
            return null;
        }
        $status = $this->api->status((string) $contract->ejar_number, $contract->ejar_version);
        $state = $status['state'] !== null ? strtolower((string) $status['state']) : null;
        $contract->forceFill(['ejar_state' => $state, 'ejar_synced_at' => now(), 'ejar_contract_id' => $status['id'] ?? $contract->ejar_contract_id]);

        if (in_array($state, self::DOCUMENTED_STATES, true)) {
            $contract->forceFill(['ejar_status' => EjarStatus::Documented, 'ejar_documented_at' => $contract->ejar_documented_at ?? today(), 'ejar_error' => null])->save();
            $this->downloadPdf($contract, quietly: true);
        } elseif (in_array($state, self::CLOSED_STATES, true)) {
            // the draft is gone on Ejar's side: the next send starts a fresh one
            $contract->forceFill(['ejar_status' => EjarStatus::Pending, 'ejar_progress' => null, 'ejar_contract_id' => null,
                'ejar_error' => __('أغلقت منصة إيجار هذا العقد (:state). أرسله من جديد بعد تصحيح بياناته.', ['state' => $state])])->save();
        } else {
            $contract->save();
        }

        return $state;
    }

    /** Stores Ejar's own PDF of the contract on the contract (media collection "ejar"). */
    public function downloadPdf(RentContract $contract, bool $quietly = false): bool
    {
        if (blank($contract->ejar_contract_id)) {
            return false;
        }
        try {
            $bytes = $this->api->pdf((string) $contract->ejar_contract_id);
        } catch (EjarException $e) {
            if ($quietly) {
                return false;
            }
            throw $e;
        }
        $contract->clearMediaCollection('ejar');
        $contract->addMediaFromString($bytes)->usingFileName('ejar-'.($contract->ejar_number ?: $contract->number).'.pdf')->toMediaCollection('ejar');

        return true;
    }

    /** Withdraws the unsubmitted draft from Ejar and starts over. */
    public function archive(RentContract $contract): void
    {
        if (filled($contract->ejar_contract_id)) {
            $this->api->archive((string) $contract->ejar_contract_id);
        }
        $contract->forceFill(['ejar_status' => EjarStatus::Pending, 'ejar_contract_id' => null, 'ejar_number' => null, 'ejar_version' => null,
            'ejar_state' => 'archived', 'ejar_progress' => null, 'ejar_error' => null, 'ejar_synced_at' => now()])->save();
    }

    /** @param array<string, mixed> $progress */
    public function nextStep(array $progress): ?string
    {
        foreach (self::STEPS as $step) {
            if (empty($progress[$step])) {
                return $step;
            }
        }

        return null;
    }

    /** @return list<array{party_type: string, party_role: string, party_data: array<string, string>}> */
    private function parties(RentContract $contract): array
    {
        $person = fn (Owner|Client $p, string $role) => ['party_type' => 'individual', 'party_role' => $role, 'party_data' => [
            'party_id' => (string) $p->id_number, 'party_id_type' => EjarApi::ID_TYPES[$p->id_type?->value] ?? 'national_id',
        ]];

        return [
            ...$contract->unit->owners->map(fn (Owner $owner) => $owner->id_type?->value === 'commercial_register'
                ? ['party_type' => 'organization', 'party_role' => 'lessor', 'party_data' => ['cr_number' => (string) $owner->id_number, 'registration_date' => (string) $owner->birth_date?->format('Y-m-d')]]
                : $person($owner, 'lessor'))->values()->all(),
            ...$contract->clients->map(fn (Client $client) => $client->kind === ClientKind::Company
                ? ['party_type' => 'organization', 'party_role' => 'tenant', 'party_data' => [
                    'cr_number' => (string) $client->company_registration_number, 'registration_date' => (string) $client->company_registration_date?->format('Y-m-d')]]
                : $person($client, 'tenant'))->values()->all(),
        ];
    }

    /** Electricity, water and gas meters of the unit, paid by the tenant on their meter (the usual residential case). */
    private function services(RentContract $contract, ?string $contractUnitId): array
    {
        if ($contractUnitId === null) {
            return [];
        }
        $readings = (array) ($contract->meter_readings ?? []);

        return $contract->unit->meters->map(function (UnitMeter $meter) use ($contractUnitId, $readings) {
            $title = (string) $meter->meterType?->title;
            $type = match (true) {
                str_contains($title, 'كهرباء') || stripos($title, 'electric') !== false => 'electricity',
                str_contains($title, 'مياه') || str_contains($title, 'ماء') || stripos($title, 'water') !== false => 'water',
                str_contains($title, 'غاز') || stripos($title, 'gas') !== false => 'gas',
                default => null,
            };

            return $type === null ? null : array_filter([
                'contract_unit_id' => $contractUnitId,
                'utility_service_type' => $type,
                'to_be_paid_by' => 'metered_fee',
                'to_be_paid_per' => 'monthly',
                'number_of_services' => 1,
                'meter_current_reading' => is_numeric($readings[$meter->number] ?? null) ? (float) $readings[$meter->number] : null,
            ], fn ($v) => $v !== null);
        })->filter()->unique('utility_service_type')->values()->all();
    }

    /** @param array<string, mixed> $progress */
    private function save(RentContract $contract, array $progress): void
    {
        $contract->forceFill(['ejar_progress' => $progress ?: null])->save();
    }
}
