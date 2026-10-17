<?php

namespace App\Filament\Admin\Resources\RentContracts;

use App\Enums\EjarStatus;
use App\Models\RentContract;
use App\Services\Ejar\EjarApi;
use App\Services\Ejar\EjarException;
use App\Services\Ejar\EjarRegistration;
use App\Services\Ejar\EjarSheet;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/**
 * Ejar documentation from the contract page. An office connected to Ejar (its own ECRS key) sends the
 * contract straight to the platform, follows the parties' approval and pulls Ejar's PDF; every office can
 * still copy the data sheet into Ejar by hand and record the Ejar number, date and PDF itself.
 */
final class EjarActions
{
    public const PLATFORM_URL = 'https://www.ejar.sa';

    public static function group(): ActionGroup
    {
        return ActionGroup::make([self::send(), self::linkExisting(), self::refresh(), self::download(), self::withdraw(), self::sheet(), self::record(), self::notRequired(), self::open()])
            ->label(__('منصة إيجار'))->icon(Heroicon::OutlinedBuildingLibrary)->button()->color('gray');
    }

    public static function send(): Action
    {
        return Action::make('ejarSend')
            ->label(fn (RentContract $record) => filled($record->ejar_progress) ? __('استكمال الإرسال إلى إيجار') : __('إرسال إلى إيجار'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('primary')
            ->visible(fn (RentContract $record) => self::can() && EjarApi::connected() && in_array($record->ejar_status, [EjarStatus::Pending, null], true))
            ->modalHeading(fn (RentContract $record) => __('إرسال العقد :number إلى منصة إيجار', ['number' => $record->number]))
            ->modalDescription(fn (RentContract $record) => ($problems = EjarRegistration::problems($record)) === []
                ? __('سيُنشأ العقد في إيجار بحساب المكتب، ويُضاف العقار والمؤجرون والمستأجرون وعدادات الخدمات، ثم يُرسل ليوافق عليه الأطراف من تطبيق إيجار.')
                : new \Illuminate\Support\HtmlString(e(__('أكمل ما يلي قبل الإرسال:')).'<ul style="margin:8px 0 0;padding-inline-start:20px;list-style:disc;display:grid;gap:4px;color:#b45309">'
                    .collect($problems)->take(8)->map(fn ($p) => '<li>'.e($p).'</li>')->join('').'</ul>'))
            ->modalSubmitAction(fn (Action $action, RentContract $record) => $action->disabled(EjarRegistration::problems($record) !== []))
            ->modalSubmitActionLabel(__('إرسال الآن'))
            ->action(function (RentContract $record) {
                try {
                    EjarRegistration::make()->register($record);
                    Notification::make()->success()->title(__('أُرسل العقد إلى إيجار'))
                        ->body(__('رقم العقد في إيجار :number. بانتظار موافقة الأطراف من تطبيق إيجار.', ['number' => $record->ejar_number ?: '—']))->persistent()->send();
                } catch (EjarException $e) {
                    Notification::make()->danger()->title(__('توقف الإرسال إلى إيجار'))
                        ->body(implode("\n", array_slice([$e->getMessage(), ...$e->messages], 0, 6))."\n".__('صحّح البيانات ثم اضغط "استكمال الإرسال"؛ يُكمل النظام من حيث توقف.'))->persistent()->send();
                }
            });
    }

    /** A contract already documented on Ejar (outside the system): find it by the parties' IDs and follow it from here. */
    public static function linkExisting(): Action
    {
        return Action::make('ejarLinkExisting')
            ->label(__('ربط بعقد موجود في إيجار'))
            ->icon(Heroicon::OutlinedLink)
            ->visible(fn (RentContract $record) => self::can() && EjarApi::connected() && blank($record->ejar_contract_id) && $record->ejar_status !== EjarStatus::NotRequired)
            ->modalHeading(__('ربط العقد بعقد موجود في إيجار'))
            ->modalDescription(__('للعقود الموثقة في إيجار من قبل: ابحث بهوية المستأجر و/أو المؤجر واختر العقد، ثم يتابعه النظام من هنا.'))
            ->modalSubmitActionLabel(__('ربط'))
            ->fillForm(function (RentContract $record) {
                $record->loadMissing(['clients', 'unit.owners']);

                return ['tenant_id_number' => $record->clients->first()?->id_number, 'lessor_id_number' => $record->unit?->owners->first()?->id_number];
            })
            ->schema([
                TextInput::make('tenant_id_number')->label(__('هوية المستأجر'))->maxLength(20)->live(onBlur: true)->extraInputAttributes(['dir' => 'ltr']),
                TextInput::make('lessor_id_number')->label(__('هوية المؤجر'))->maxLength(20)->live(onBlur: true)->extraInputAttributes(['dir' => 'ltr']),
                Select::make('ejar_contract')->label(__('العقد في إيجار'))->required()->native(false)
                    ->options(fn (Get $get) => self::existing($get('tenant_id_number'), $get('lessor_id_number')))
                    ->placeholder(__('أدخل هوية المستأجر أو المؤجر لعرض العقود')),
            ])
            ->action(function (RentContract $record, array $data) {
                [$id, $number, $version] = array_pad(explode('|', (string) $data['ejar_contract']), 3, null);
                $record->forceFill(['ejar_contract_id' => $id, 'ejar_number' => $number, 'ejar_version' => $version !== null && $version !== '' ? (int) $version : null,
                    'ejar_status' => EjarStatus::AwaitingParties, 'ejar_progress' => ['linked' => now()->toIso8601String()], 'ejar_error' => null])->save();
                try {
                    $state = EjarRegistration::make()->refresh($record);
                    Notification::make()->success()->title(__('رُبط العقد بعقد إيجار :number', ['number' => $number]))
                        ->body(__('الحالة في إيجار: :state', ['state' => $state ?? '—']))->send();
                } catch (EjarException $e) {
                    Notification::make()->warning()->title(__('رُبط العقد، وتعذّر جلب حالته الآن'))->body($e->getMessage())->send();
                }
            });
    }

    /** @return array<string, string> "id|number|version" => label */
    private static function existing(?string $tenant, ?string $lessor): array
    {
        if (blank($tenant) && blank($lessor)) {
            return [];
        }
        try {
            $contracts = \Illuminate\Support\Facades\Cache::remember('ejar:'.tenant()?->getTenantKey().':contracts:'.md5($tenant.'|'.$lessor), 300,
                fn () => (new EjarApi)->contracts(['tenant_id_number' => $tenant, 'lessor_id_number' => $lessor]));
        } catch (EjarException) {
            return [];
        }

        return collect($contracts)->filter(fn ($c) => filled($c['id']))->mapWithKeys(fn ($c) => [
            $c['id'].'|'.$c['contract_number'].'|'.$c['version'] => trim(($c['contract_number'] ?? '—').' · '.($c['contract_start_date'] ?? '').' ← '.($c['contract_end_date'] ?? '').' · '.($c['property_name'] ?? '').' '.($c['unit_number'] ?? ''), ' ·'),
        ])->all();
    }

    public static function refresh(): Action
    {
        return Action::make('ejarRefresh')
            ->label(__('تحديث الحالة من إيجار'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (RentContract $record) => EjarApi::connected() && filled($record->ejar_number) && filled($record->ejar_contract_id))
            ->action(function (RentContract $record) {
                try {
                    $state = EjarRegistration::make()->refresh($record);
                    Notification::make()->success()->title(__('الحالة في إيجار: :state', ['state' => $state ?? '—']))
                        ->body($record->ejar_status->getLabel())->send();
                } catch (EjarException $e) {
                    Notification::make()->danger()->title($e->getMessage())->body(implode("\n", $e->messages))->send();
                }
            });
    }

    public static function download(): Action
    {
        return Action::make('ejarPdf')
            ->label(__('نسخة إيجار (PDF)'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->visible(fn (RentContract $record) => filled($record->ejar_contract_id) && ($record->getFirstMedia('ejar') !== null || EjarApi::connected()))
            ->action(function (RentContract $record) {
                try {
                    if ($record->getFirstMedia('ejar') === null || EjarApi::connected()) {
                        EjarRegistration::make()->downloadPdf($record);
                        $record->unsetRelation('media');
                    }
                } catch (EjarException $e) {
                    if ($record->getFirstMedia('ejar') === null) {
                        Notification::make()->danger()->title($e->getMessage())->body(implode("\n", $e->messages))->send();

                        return null;
                    }
                }
                $media = $record->getFirstMedia('ejar');

                return response()->download($media->getPath(), $media->file_name);
            });
    }

    public static function withdraw(): Action
    {
        return Action::make('ejarWithdraw')
            ->label(__('سحب المسودة من إيجار'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (RentContract $record) => self::can() && EjarApi::connected() && filled($record->ejar_contract_id) && $record->ejar_status !== EjarStatus::Documented)
            ->requiresConfirmation()
            ->modalDescription(__('تُؤرشف المسودة في إيجار ولا يمكن إرسالها بعد ذلك. يمكنك إرسال العقد من جديد كمسودة جديدة.'))
            ->action(function (RentContract $record) {
                try {
                    EjarRegistration::make()->archive($record);
                    Notification::make()->success()->title(__('سُحبت المسودة من إيجار'))->send();
                } catch (EjarException $e) {
                    Notification::make()->danger()->title($e->getMessage())->body(implode("\n", $e->messages))->send();
                }
            });
    }

    public static function sheet(): Action
    {
        return Action::make('ejarSheet')
            ->label(__('بيانات التوثيق في إيجار'))
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->modalHeading(fn (RentContract $record) => __('بيانات توثيق العقد :number في منصة إيجار', ['number' => $record->number]))
            ->modalWidth('5xl')
            ->modalContent(fn (RentContract $record) => view('filament.admin.ejar-sheet', [
                'groups' => EjarSheet::for($record), 'missing' => EjarSheet::missing($record),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('إغلاق'));
    }

    public static function record(): Action
    {
        return Action::make('ejarRecord')
            ->label(fn (RentContract $record) => $record->ejar_status === EjarStatus::Documented ? __('تعديل بيانات التوثيق') : __('تسجيل التوثيق'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->visible(fn () => self::can())
            ->fillForm(fn (RentContract $record) => [
                'ejar_status' => in_array($record->ejar_status, [EjarStatus::AwaitingParties, EjarStatus::Documented], true) ? $record->ejar_status : EjarStatus::Documented,
                'ejar_number' => $record->ejar_number,
                'ejar_documented_at' => $record->ejar_documented_at ?? today(),
            ])
            ->schema([
                Select::make('ejar_status')->label(__('الحالة في إيجار'))->required()->native(false)->live()
                    ->options([EjarStatus::AwaitingParties->value => EjarStatus::AwaitingParties->getLabel(), EjarStatus::Documented->value => EjarStatus::Documented->getLabel()]),
                TextInput::make('ejar_number')->label(__('رقم العقد في إيجار'))->maxLength(40)
                    ->required(fn (Get $get) => self::documented($get('ejar_status'))),
                DatePicker::make('ejar_documented_at')->label(__('تاريخ التوثيق'))->maxDate(today())
                    ->visible(fn (Get $get) => self::documented($get('ejar_status'))),
                SpatieMediaLibraryFileUpload::make('ejar_pdf')->label(__('نسخة العقد الموثق (PDF)'))->collection('ejar')
                    ->acceptedFileTypes(['application/pdf'])->maxSize(10240)->downloadable()
                    ->visible(fn (Get $get) => self::documented($get('ejar_status'))),
            ])
            ->action(function (RentContract $record, array $data) {
                $documented = self::documented($data['ejar_status']);
                $record->forceFill([
                    'ejar_status' => $data['ejar_status'],
                    'ejar_number' => $data['ejar_number'] ?? null,
                    'ejar_documented_at' => $documented ? ($data['ejar_documented_at'] ?? today()) : null,
                ])->save();
                Notification::make()->success()->title($documented ? __('تم تسجيل توثيق العقد في إيجار') : __('تم تحديث حالة التوثيق'))->send();
            });
    }

    public static function notRequired(): Action
    {
        return Action::make('ejarNotRequired')
            ->label(__('لا يلزم التوثيق'))
            ->icon(Heroicon::OutlinedMinusCircle)
            ->color('gray')
            ->visible(fn (RentContract $record) => self::can() && $record->ejar_status === EjarStatus::Pending)
            ->schema([TextInput::make('ejar_notes')->label(__('السبب'))->required()->maxLength(250)])
            ->action(fn (RentContract $record, array $data) => $record->forceFill(['ejar_status' => EjarStatus::NotRequired, 'ejar_notes' => $data['ejar_notes']])->save());
    }

    public static function open(): Action
    {
        return Action::make('ejarOpen')
            ->label(__('فتح منصة إيجار'))
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->url(self::PLATFORM_URL, shouldOpenInNewTab: true);
    }

    private static function documented(mixed $status): bool
    {
        return ($status instanceof EjarStatus ? $status : EjarStatus::tryFrom((string) $status)) === EjarStatus::Documented;
    }

    private static function can(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can('rent_contracts.update'));
    }
}
