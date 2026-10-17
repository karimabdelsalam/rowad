<?php

namespace App\Filament\Support;

use App\Enums\ClientKind;
use App\Enums\IdType;
use App\Models\Client;
use App\Models\Owner;
use App\Services\Ejar\EjarApi;
use App\Services\Ejar\EjarException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * "التحقق في إيجار" on an owner's or client's page: asks Ejar who this ID belongs to (Ejar checks IDs against
 * Absher / the commercial register), shows the name Ejar has, and remembers the Ejar entity — properties are
 * registered under it and contracts are refused for people Ejar cannot verify.
 */
final class EjarVerifyAction
{
    public static function make(): Action
    {
        return Action::make('ejarVerify')
            ->label(__('التحقق في إيجار'))
            ->icon(Heroicon::OutlinedFingerPrint)
            ->color('gray')
            ->visible(fn () => EjarApi::connected())
            ->action(function (Owner|Client $record) {
                $organization = self::organization($record);
                [$number, $date] = $organization
                    ? [$record instanceof Client ? $record->company_registration_number : $record->id_number, $record instanceof Client ? $record->company_registration_date : $record->birth_date]
                    : [$record->id_number, null];
                if (blank($number) || ($organization && $date === null)) {
                    Notification::make()->warning()->title($organization
                        ? __('أدخل رقم السجل التجاري وتاريخ قيده أولًا.')
                        : __('أدخل رقم الهوية أولًا.'))->send();

                    return;
                }
                try {
                    $api = new EjarApi;
                    $entity = $organization
                        ? $api->organization((string) $number, $date->format('Y-m-d'))
                        : $api->individual((string) $number, $record->id_type?->value ?? 'national_id');
                } catch (EjarException $e) {
                    $record->forceFill(['ejar_verification' => 'not_found', 'ejar_verified_at' => now()])->save();
                    Notification::make()->danger()->title(__('لم يتحقق إيجار من هذه الهوية'))
                        ->body(implode("\n", array_slice([$e->getMessage(), ...$e->messages], 0, 4)))->persistent()->send();

                    return;
                }

                $status = (string) ($entity['verification_status'] ?? 'found');
                $record->forceFill(['ejar_entity_id' => $entity['id'] ?? null, 'ejar_verification' => $status, 'ejar_verified_at' => now()])->save();
                $name = (string) ($entity['full_name'] ?? $entity['name'] ?? '');
                $ours = $organization && $record instanceof Client ? ($record->company_name ?: $record->full_name) : $record->full_name;
                $same = $name === '' || self::norm($name) === self::norm((string) $ours) || str_contains(self::norm($name), self::norm((string) $record->first_name));

                Notification::make()->{$same ? 'success' : 'warning'}()
                    ->title(str_contains($status, 'succeed') || $status === 'found' ? __('الهوية مسجلة في إيجار') : __('حالة التحقق في إيجار: :status', ['status' => $status]))
                    ->body(collect([
                        $name !== '' ? __('الاسم في إيجار: :name', ['name' => $name]) : null,
                        ! $same ? __('يختلف عن الاسم المسجل لدينا؛ راجع البيانات قبل إرسال العقود.') : null,
                        filled($entity['date_of_birth'] ?? null) ? __('تاريخ الميلاد: :date', ['date' => $entity['date_of_birth']]) : null,
                    ])->filter()->join("\n"))->persistent()->send();
            });
    }

    private static function organization(Owner|Client $record): bool
    {
        return $record instanceof Client ? $record->kind === ClientKind::Company : $record->id_type === IdType::CommercialRegister;
    }

    private static function norm(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', strtr(mb_strtolower($name), ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي'])));
    }
}
