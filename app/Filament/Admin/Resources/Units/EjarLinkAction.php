<?php

namespace App\Filament\Admin\Resources\Units;

use App\Models\Unit;
use App\Services\Ejar\EjarApi;
use App\Services\Ejar\EjarException;
use App\Support\Hijri;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;

/**
 * Finds the unit's property on Ejar by its ownership deed (number + Gregorian issue date) and remembers the
 * Ejar property and unit ids, which every contract sent to Ejar for this unit needs.
 */
final class EjarLinkAction
{
    private static ?string $error = null;

    /** Ejar's unit types, as the office reads them. */
    private const UNIT_TYPES = ['apartment' => 'شقة', 'villa' => 'فيلا', 'duplex' => 'دوبلكس', 'studio' => 'استوديو', 'floor' => 'دور',
        'room' => 'غرفة', 'office' => 'مكتب', 'shop' => 'محل', 'showroom' => 'معرض', 'warehouse' => 'مستودع', 'building' => 'عمارة', 'land' => 'أرض'];

    public static function make(): Action
    {
        return Action::make('ejarLink')
            ->label(fn (Unit $record) => filled($record->ejar_unit_id) ? __('تعديل الربط بإيجار') : __('ربط بإيجار'))
            ->icon(Heroicon::OutlinedBuildingLibrary)
            ->color('gray')
            ->visible(fn () => EjarApi::connected() && self::can())
            ->modalHeading(__('ربط الوحدة بعقارها في منصة إيجار'))
            ->modalDescription(__('أدخل رقم صك الملكية وتاريخه الميلادي كما سُجل العقار في إيجار، ثم اختر العقار والوحدة.'))
            ->modalSubmitActionLabel(__('حفظ الربط'))
            ->fillForm(fn (Unit $record) => [
                'document_number' => $record->deed_number ?: $record->deeds()->value('number'),
                'issued_date' => self::gregorian($record->deeds()->value('issue_date')),
                'ejar_property_id' => $record->ejar_property_id,
                'ejar_unit_id' => $record->ejar_unit_id,
            ])
            ->schema([
                TextInput::make('document_number')->label(__('رقم الصك'))->required()->maxLength(40)->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set) => $set('ejar_property_id', null)),
                DatePicker::make('issued_date')->label(__('تاريخ الصك (ميلادي)'))->required()->maxDate(today())->live()
                    ->afterStateUpdated(fn (Set $set) => $set('ejar_property_id', null)),
                Select::make('ejar_property_id')->label(__('العقار في إيجار'))->required()->native(false)->live()
                    ->options(fn (Get $get) => self::properties($get('document_number'), $get('issued_date')))
                    ->afterStateUpdated(fn (Set $set) => $set('ejar_unit_id', null))
                    ->helperText(fn () => self::$error)
                    ->placeholder(__('أدخل الصك وتاريخه لعرض العقارات')),
                Select::make('ejar_unit_id')->label(__('الوحدة في إيجار'))->required()->native(false)
                    ->options(fn (Get $get, Unit $record) => self::units($get('ejar_property_id'), $record))
                    ->disabled(fn (Get $get) => blank($get('ejar_property_id'))),
            ])
            ->action(function (Unit $record, array $data) {
                $record->forceFill(['ejar_property_id' => $data['ejar_property_id'], 'ejar_unit_id' => $data['ejar_unit_id']])->save();
                Notification::make()->success()->title(__('تم ربط الوحدة بإيجار'))
                    ->body(__('عقود هذه الوحدة يمكن إرسالها الآن إلى إيجار من صفحة العقد.'))->send();
            });
    }

    /** @return array<string, string> */
    private static function properties(?string $number, mixed $date): array
    {
        if (blank($number) || blank($date)) {
            return [];
        }
        $date = substr((string) $date, 0, 10);

        return self::remember("properties:{$number}:{$date}", fn () => collect((new EjarApi)->properties(trim($number), $date))
            ->filter(fn ($p) => filled($p['id'] ?? null))
            ->mapWithKeys(fn ($p) => [(string) $p['id'] => trim(($p['property_name'] ?? __('عقار')).' · '.($p['property_number'] ?? '').' · '.data_get($p, 'address.attributes.city', ''), ' ·')])
            ->all(), __('لا يوجد عقار في إيجار بهذا الصك. سجّل العقار في إيجار أولًا ثم أعد المحاولة.'));
    }

    /** @return array<string, string> the unit with the same number first */
    private static function units(?string $propertyId, Unit $unit): array
    {
        if (blank($propertyId)) {
            return [];
        }

        return self::remember("units:{$propertyId}", fn () => collect((new EjarApi)->units($propertyId))
            ->filter(fn ($u) => filled($u['id'] ?? null))
            ->sortByDesc(fn ($u) => (string) ($u['unit_number'] ?? '') === (string) $unit->number)
            ->mapWithKeys(fn ($u) => [(string) $u['id'] => trim(__('وحدة').' '.($u['unit_number'] ?? '').' · '.(isset(self::UNIT_TYPES[$u['unit_type'] ?? '']) ? __(self::UNIT_TYPES[$u['unit_type']]) : ($u['unit_type'] ?? '')).(isset($u['floor_number']) ? ' · '.__('الطابق').' '.$u['floor_number'] : ''), ' ·')])
            ->all(), __('لا توجد وحدات لهذا العقار في إيجار.'));
    }

    /** Lookups are cached briefly so re-rendering the form does not call Ejar again. */
    private static function remember(string $key, \Closure $fetch, string $empty): array
    {
        try {
            $options = Cache::remember('ejar:'.tenant()?->getTenantKey().':'.md5($key), 600, $fetch);
            self::$error = $options === [] ? $empty : null;

            return $options;
        } catch (EjarException $e) {
            self::$error = trim($e->getMessage().' '.implode(' ', $e->messages));

            return [];
        }
    }

    /** Deed dates are often kept in Hijri ("1440/03/12"); Ejar wants the Gregorian date. */
    private static function gregorian(?string $date): ?string
    {
        if (blank($date) || ! preg_match('/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})/', trim($date), $m)) {
            return null;
        }
        try {
            return (int) $m[1] < 1700 ? Hijri::parse("{$m[1]}/{$m[2]}/{$m[3]}")->format('Y-m-d') : sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function can(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can('units.update'));
    }
}
