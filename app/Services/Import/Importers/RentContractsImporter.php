<?php

namespace App\Services\Import\Importers;

use App\Enums\ContractStatus;
use App\Enums\EjarStatus;
use App\Enums\PaymentCycle;
use App\Enums\RentPeriodUnit;
use App\Models\Client;
use App\Models\RentContract;
use App\Models\Unit;
use App\Services\Contracts\ContractService;
use App\Services\Import\Importer;
use App\Support\Hijri;
use Carbon\CarbonImmutable;

/**
 * Running rent contracts brought over from another system. Each goes through the normal contract service, so
 * its payment schedule is generated as if entered by hand; the tenant is matched by ID number or mobile (or
 * created from the name given). Instalments already due can be marked as collected, since that history was
 * settled in the old system. Nobody is messaged about imported contracts.
 */
class RentContractsImporter extends Importer
{
    public static function key(): string
    {
        return 'rent_contracts';
    }

    public static function title(): string
    {
        return __('عقود الإيجار السارية');
    }

    public static function columns(): array
    {
        return [
            'unit' => ['اسم الوحدة', true, 'شقة 12'],
            'building' => ['العقار / المبنى', false, 'عمارة النخيل'],
            'tenant_id_number' => ['رقم هوية المستأجر', false, '1098765432'],
            'tenant_mobile' => ['جوال المستأجر', false, '0559876543'],
            'tenant_name' => ['اسم المستأجر', false, 'خالد سعد الحربي'],
            'start_date' => ['تاريخ البداية', true, '2026-01-01'],
            'end_date' => ['تاريخ النهاية', true, '2026-12-31'],
            'rent_amount' => ['قيمة الدفعة', true, '3000'],
            'payment_cycle' => ['دورة السداد', true, 'شهري'],
            'commission' => ['العمولة', false, '1500'],
            'ejar_number' => ['رقم العقد في إيجار', false, ''],
            'notes' => ['ملاحظات', false, ''],
        ];
    }

    public function problems(array $row): array
    {
        $f = static::fields($row);
        $problems = static::missing($f);
        if (blank($f['tenant_id_number']) && blank($f['tenant_mobile'])) {
            $problems[] = __('أدخل رقم هوية المستأجر أو جواله');
        } elseif ($this->tenant($f) === null && blank($f['tenant_name'])) {
            $problems[] = __('المستأجر غير موجود؛ أضف اسمه أو استورد العملاء أولًا');
        }
        if (filled($f['unit'])) {
            $units = $this->units($f);
            if ($units->isEmpty()) {
                $problems[] = __('الوحدة «:unit» غير موجودة؛ استورد الوحدات أولًا', ['unit' => $f['unit']]);
            } elseif ($units->count() > 1) {
                $problems[] = __('يوجد أكثر من وحدة باسم «:unit»؛ أضف اسم العقار', ['unit' => $f['unit']]);
            }
        }
        $cycle = static::enum(PaymentCycle::class, $f['payment_cycle']);
        if (filled($f['payment_cycle']) && ($cycle === null || $cycle === PaymentCycle::Once)) {
            $problems[] = __('دورة السداد «:value» غير معروفة', ['value' => $f['payment_cycle']]);
        }
        foreach (['rent_amount', 'commission'] as $field) {
            if (filled($f[$field]) && ((static::number($f[$field]) ?? -1) < 0 || ($field === 'rent_amount' && static::number($f[$field]) == 0))) {
                $problems[] = __('قيمة «:column» غير صحيحة', ['column' => __(static::columns()[$field][0])]);
            }
        }
        [$start, $end] = [static::date($f['start_date']), static::date($f['end_date'])];
        if (filled($f['start_date']) && $start === null) {
            $problems[] = __('تاريخ البداية غير صحيح');
        }
        if (filled($f['end_date']) && $end === null) {
            $problems[] = __('تاريخ النهاية غير صحيح');
        }
        if ($start && $end) {
            if ($end->lt(today())) {
                $problems[] = __('العقد منتهٍ؛ تُستورد العقود السارية فقط');
            } elseif ($cycle && $cycle !== PaymentCycle::Once && static::period($f, $cycle) === null) {
                $problems[] = __('مدة العقد يجب أن تكون سنوات كاملة، أو أشهرًا كاملة مع سداد شهري');
            }
        }

        return $problems;
    }

    public function import(array $row, array $options): string
    {
        $f = static::fields($row);
        $unit = $this->units($f)->sole();
        $start = static::date($f['start_date']);
        $existing = RentContract::query()->where('unit_id', $unit->id)->where('status', ContractStatus::Active)
            ->whereDate('start_date', $start->toDateString())->exists();
        if ($existing) {
            return 'skipped'; // already imported (contracts are never overwritten)
        }

        $tenant = $this->tenant($f) ?? Client::create(UnitsImporter::nameParts($f['tenant_name']) + array_filter([
            'id_number' => static::digits($f['tenant_id_number']) ?: null,
            'mobile' => static::mobile($f['tenant_mobile']),
        ]));
        $cycle = static::enum(PaymentCycle::class, $f['payment_cycle']);
        [$periodUnit, $periodCount] = static::period($f, $cycle);
        $amount = (float) static::number($f['rent_amount']);
        $commission = (float) (static::number($f['commission']) ?? 0);

        $contract = app(ContractService::class)->createRent(array_filter([
            'unit_id' => $unit->id,
            'calendar' => static::isHijri($f['start_date']) ? 'hijri' : 'gregorian',
            'contract_date' => $start->toDateString(),
            'start_date' => $start->toDateString(),
            'end_date' => static::date($f['end_date'])->toDateString(),
            'period_unit' => $periodUnit->value,
            'period_count' => $periodCount,
            'payment_cycle' => $cycle->value,
            'rent_amount' => $amount,
            'yearly_rent' => $cycle === PaymentCycle::Day ? null : round($amount * 12 / $cycle->months(), 2),
            'commission' => $commission,
            'commission_cycle' => $commission > 0 ? PaymentCycle::Once->value : null,
            'notes' => $f['notes'],
            'ejar_number' => static::digits($f['ejar_number']),
            'ejar_status' => filled($f['ejar_number']) ? EjarStatus::Documented->value : null,
        ], fn ($v) => $v !== null && $v !== ''), [$tenant->id => 100], [], notify: false);

        if ($options['collect_past'] ?? false) {
            foreach ($contract->installments()->where('is_collected', false)->whereDate('due_date', '<', today())->get() as $installment) {
                $installment->forceFill(['is_collected' => true, 'collected_at' => $installment->due_date, 'paid_amount' => $installment->amount])->save();
            }
            $contract->refreshNextPayment();
        }

        return 'created';
    }

    /** @return \Illuminate\Support\Collection<int, Unit> */
    private function units(array $f): \Illuminate\Support\Collection
    {
        return Unit::query()->where('title', $f['unit'])
            ->when(filled($f['building']), fn ($query) => $query->whereHas('building', fn ($query) => $query->where('title', $f['building'])))
            ->limit(2)->get();
    }

    private function tenant(array $f): ?Client
    {
        /** @var ?Client */
        return ClientsImporter::find(static::digits($f['tenant_id_number']) ?: null, static::mobile($f['tenant_mobile']));
    }

    /**
     * The contract term from its dates: whole years, or whole months when paid monthly, or days when paid daily.
     * An end date on the anniversary itself (01/01 → 01/01) is accepted as well as the day before it.
     *
     * @return array{0: RentPeriodUnit, 1: int}|null
     */
    private static function period(array $f, PaymentCycle $cycle): ?array
    {
        $start = static::date($f['start_date']);
        $end = static::date($f['end_date']);
        if ($start === null || $end === null || $end->lt($start)) {
            return null;
        }
        if ($cycle === PaymentCycle::Day) {
            return [RentPeriodUnit::Day, (int) $start->diffInDays($end) + 1];
        }
        $hijri = static::isHijri($f['start_date']);
        for ($months = 1; $months <= 240; $months++) {
            $next = $hijri ? Hijri::addMonths($start, $months) : $start->addMonthsNoOverflow($months);
            if ($next->gt($end->addDays(2))) {
                break;
            }
            // Hijri month lengths differ between calendars by a day, so a Hijri end date may be a day short
            if ($next->subDay()->isSameDay($end) || $next->isSameDay($end) || ($hijri && $next->subDays(2)->isSameDay($end))) {
                if ($months % 12 === 0) {
                    return [RentPeriodUnit::Year, intdiv($months, 12)];
                }

                return $cycle === PaymentCycle::Month ? [RentPeriodUnit::Month, $months] : null;
            }
        }

        return null;
    }

    private static function isHijri(mixed $value): bool
    {
        return is_string($value) && preg_match('/^1[34]\d\d[\/-]/', static::digits($value)) === 1;
    }
}
