<?php

namespace App\Filament\Admin\Resources\RentContracts;

use App\Enums\EjarStatus;
use App\Models\RentContract;
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
 * Ejar documentation from the contract page: the data sheet to copy into the platform, then the Ejar
 * contract number, date and PDF once the parties approve. (Direct API submission needs an Ejar
 * integration agreement; the sheet and the tracking are what every office can use today.)
 */
final class EjarActions
{
    public const PLATFORM_URL = 'https://www.ejar.sa';

    public static function group(): ActionGroup
    {
        return ActionGroup::make([self::sheet(), self::record(), self::notRequired(), self::open()])
            ->label(__('منصة إيجار'))->icon(Heroicon::OutlinedBuildingLibrary)->button()->color('gray');
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
