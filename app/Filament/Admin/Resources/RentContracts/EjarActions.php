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
        return ActionGroup::make([self::send(), self::refresh(), self::download(), self::withdraw(), self::sheet(), self::record(), self::notRequired(), self::open()])
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
                : __('أكمل ما يلي قبل الإرسال:')."\n• ".implode("\n• ", array_slice($problems, 0, 8)))
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
