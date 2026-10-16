<?php

namespace App\Filament\Admin\Resources\MaintenanceRequests;

use App\Enums\MaintenanceStatus as S;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\BankAccount;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\Maintenance\MaintenanceService;
use App\Support\Settings;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/** The workflow buttons, shared by the list rows and the request page. */
final class MaintenanceActions
{
    /** @return list<Action> */
    public static function all(): array
    {
        return [self::schedule(), self::start(), self::complete(), self::cancel()];
    }

    public static function schedule(): Action
    {
        return Action::make('schedule')
            ->label(fn (MaintenanceRequest $record) => filled($record->technician_name) ? __('تعديل الموعد') : __('تحديد فني وموعد'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('info')
            ->visible(fn (MaintenanceRequest $record) => in_array($record->status, [S::New, S::Scheduled, S::AwaitingApproval], true) && self::can('assign'))
            ->fillForm(fn (MaintenanceRequest $record) => ['assigned_to' => $record->assigned_to ?? auth()->id()]
                + $record->only(['technician_name', 'technician_phone', 'scheduled_at', 'estimated_cost']))
            ->modalDescription(fn () => filled($limit = Settings::get('maintenance_approval_limit')) && (float) $limit > 0
                ? __('التكلفة التقديرية :amount فأكثر تُرسل للمالك للموافقة قبل التنفيذ.', ['amount' => number_format((float) $limit, 2)]) : null)
            ->schema([
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    TextInput::make('technician_name')->label(__('الفني / الشركة'))->required()->maxLength(120),
                    TextInput::make('technician_phone')->label(__('جوال الفني'))->tel()->maxLength(30),
                    DateTimePicker::make('scheduled_at')->label(__('موعد الزيارة'))->seconds(false)->required(),
                    TextInput::make('estimated_cost')->label(__('التكلفة التقديرية'))->numeric()->minValue(0)->suffix(Settings::currencyLabel()),
                    Select::make('assigned_to')->label(__('الموظف المتابع'))->native(false)->searchable()
                        ->options(fn () => User::query()->where('type', 'staff')->where('is_active', true)->pluck('name', 'id'))
                        ->default(auth()->id())->columnSpanFull(),
                ]),
            ])
            ->action(fn (MaintenanceRequest $record, array $data) => self::run(
                fn () => app(MaintenanceService::class)->schedule($record, $data),
                fn () => $record->status === S::AwaitingApproval ? __('أُرسل الطلب للمالك للموافقة على التكلفة') : __('تم تحديد الموعد وإبلاغ المستأجر'),
            ));
    }

    public static function start(): Action
    {
        return Action::make('start')
            ->label(__('بدء التنفيذ'))
            ->icon(Heroicon::OutlinedWrench)
            ->color('primary')
            ->requiresConfirmation()
            ->visible(fn (MaintenanceRequest $record) => in_array($record->status, [S::New, S::Scheduled], true) && self::can('assign'))
            ->action(fn (MaintenanceRequest $record) => self::run(fn () => app(MaintenanceService::class)->start($record), fn () => __('الطلب قيد التنفيذ')));
    }

    public static function complete(): Action
    {
        return Action::make('complete')
            ->label(__('إنجاز وتحميل التكلفة'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (MaintenanceRequest $record) => in_array($record->status, [S::New, S::Scheduled, S::InProgress], true) && self::can('complete'))
            ->fillForm(fn (MaintenanceRequest $record) => ['cost' => $record->estimated_cost, 'charge_to' => MaintenanceRequest::CHARGE_OWNERS, 'payment_method' => 'cash'])
            ->modalDescription(__('تصدر التكلفة سند صرف للفني وتُحمّل على الجهة المختارة.'))
            ->schema([
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    TextInput::make('cost')->label(__('التكلفة الفعلية'))->numeric()->minValue(0)->suffix(Settings::currencyLabel())->live(onBlur: true)
                        ->helperText(__('اتركها صفرًا إن لم تكن هناك تكلفة.')),
                    Select::make('payment_method')->label(__('طريقة الدفع للفني'))->options(PaymentMethod::class)->native(false)
                        ->visible(fn (Get $get) => (float) $get('cost') > 0),
                    Radio::make('charge_to')->label(__('تُحمّل التكلفة'))->options(MaintenanceRequest::chargeOptions())->columnSpanFull()
                        ->visible(fn (Get $get) => (float) $get('cost') > 0)->required(fn (Get $get) => (float) $get('cost') > 0),
                    Select::make('bank_account_id')->label(__('من حساب بنكي'))->options(fn () => BankAccount::query()->pluck('holder_name', 'id'))->native(false)
                        ->visible(fn (Get $get) => (float) $get('cost') > 0 && $get('payment_method') !== 'cash' && $get('payment_method') !== PaymentMethod::Cash)
                        ->columnSpanFull(),
                    Textarea::make('resolution')->label(__('ما الذي تم'))->rows(2)->columnSpanFull(),
                    SpatieMediaLibraryFileUpload::make('after')->label(__('صور بعد الإنجاز'))->collection('after')->image()->multiple()->maxFiles(6)
                        ->maxSize(8192)->columnSpanFull(),
                ]),
            ])
            ->action(fn (MaintenanceRequest $record, array $data) => self::run(
                fn () => app(MaintenanceService::class)->complete($record, $data),
                fn () => $record->payment_voucher_id ? __('تم الإنجاز وإصدار سند الصرف') : __('تم إنجاز الطلب'),
            ));
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label(__('إلغاء الطلب'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (MaintenanceRequest $record) => $record->status->isOpen() && self::can('cancel'))
            ->schema([Textarea::make('reason')->label(__('السبب'))->required()->rows(2)->maxLength(250)])
            ->action(fn (MaintenanceRequest $record, array $data) => self::run(
                fn () => app(MaintenanceService::class)->cancel($record, $data['reason']),
                fn () => __('تم إلغاء الطلب'),
            ));
    }

    public static function can(string $ability): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can("maintenance_requests.{$ability}"));
    }

    private static function run(callable $callback, callable $success): void
    {
        try {
            $callback();
            Notification::make()->title($success())->success()->send();
        } catch (BusinessRuleException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }
}
