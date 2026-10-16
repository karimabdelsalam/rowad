<?php

namespace App\Filament\Platform\Resources\Tenants;

use App\Exceptions\BusinessRuleException;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Platform\OfficeProvisioner;
use App\Services\Platform\SubscriptionManager;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Stancl\Tenancy\Database\Models\Domain;

/** Everything the platform does to one office, shared by the list and the detail page. */
final class TenantActions
{
    public static function recordPayment(): Action
    {
        return Action::make('recordPayment')
            ->label(__('تسجيل دفعة اشتراك'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->modalHeading(__('تسجيل دفعة اشتراك'))
            ->modalDescription(__('تُفعَّل المؤسسة على الباقة المدفوعة وتمتد الفترة من نهاية الفترة الحالية.'))
            ->fillForm(fn (Tenant $record) => [
                'plan_id' => $record->upgrade_request['plan_id'] ?? $record->plan_id,
                'billing_cycle' => 'monthly',
                'amount' => ($plan = Plan::find($record->upgrade_request['plan_id'] ?? $record->plan_id)) ? SubscriptionManager::price($plan, 'monthly') : null,
                'starts_at' => SubscriptionManager::nextStart($record)->toDateString(),
            ])
            ->schema([
                Select::make('plan_id')->label(__('الباقة'))->options(fn () => Plan::orderBy('sort_order')->pluck('name_ar', 'id'))->required()->live()
                    ->afterStateUpdated(fn (Get $get, Set $set, $state) => $set('amount', ($plan = Plan::find($state)) ? SubscriptionManager::price($plan, $get('billing_cycle')) : null)),
                Select::make('billing_cycle')->label(__('دورة الفوترة'))->options(fn () => array_map('__', SubscriptionManager::CYCLES))->required()->live()
                    ->afterStateUpdated(fn (Get $get, Set $set, $state) => $set('amount', ($plan = Plan::find($get('plan_id'))) ? SubscriptionManager::price($plan, $state) : null)),
                TextInput::make('amount')->label(__('المبلغ المدفوع شامل الضريبة'))->numeric()->minValue(0)->required()->suffix(__('ر.س')),
                DatePicker::make('starts_at')->label(__('بداية الفترة'))->required(),
                TextInput::make('reference')->label(__('مرجع الدفع'))->helperText(__('رقم التحويل البنكي أو الإيصال.'))->maxLength(190),
            ])
            ->action(function (Tenant $record, array $data) {
                $subscription = app(SubscriptionManager::class)->record($record, Plan::findOrFail($data['plan_id']), $data['billing_cycle'],
                    (float) $data['amount'], Carbon::parse($data['starts_at']), $data['reference'] ?? null);
                Notification::make()->success()->title(__('تم تفعيل الاشتراك حتى :date', ['date' => $subscription->ends_at->format('Y-m-d')]))->send();
            });
    }

    public static function extendTrial(): Action
    {
        return Action::make('extendTrial')
            ->label(__('تمديد التجربة'))
            ->icon(Heroicon::OutlinedClock)
            ->color('warning')
            ->visible(fn (Tenant $record) => in_array($record->status, [Tenant::STATUS_TRIAL, Tenant::STATUS_SUSPENDED], true) && $record->subscriptions()->where('status', 'active')->doesntExist())
            ->schema([
                TextInput::make('days')->label(__('عدد الأيام'))->integer()->minValue(1)->maxValue(365)->default(14)->required()
                    ->helperText(fn (Tenant $record) => __('تنتهي التجربة حاليًا في :date', ['date' => $record->trial_ends_at?->format('Y-m-d') ?? '-'])),
            ])
            ->action(function (Tenant $record, array $data) {
                $from = $record->trial_ends_at && $record->trial_ends_at->isFuture() ? $record->trial_ends_at : now();
                $until = $from->copy()->addDays((int) $data['days']);
                $record->forceFill(['trial_ends_at' => $until, 'status' => Tenant::STATUS_TRIAL])->save();
                $record->subscriptions()->where('status', 'trialing')->update(['ends_at' => $until]);
                Notification::make()->success()->title(__('مُددت التجربة حتى :date', ['date' => $until->format('Y-m-d')]))->send();
            });
    }

    public static function changeSubdomain(): Action
    {
        return Action::make('changeSubdomain')
            ->label(__('تغيير النطاق الفرعي'))
            ->icon(Heroicon::OutlinedGlobeAlt)
            ->fillForm(fn (Tenant $record) => ['subdomain' => str(self::platformDomain($record)?->domain)->before('.'.config('rowad.tenancy.base_domain'))->toString()])
            ->schema([
                TextInput::make('subdomain')->label(__('النطاق الفرعي الجديد'))->required()->alphaDash()->maxLength(40)
                    ->suffix('.'.config('rowad.tenancy.base_domain'))->extraInputAttributes(['dir' => 'ltr'])
                    ->helperText(__('الرابط القديم يتوقف فورًا؛ أبلغ المؤسسة بالرابط الجديد (وروابط QR المطبوعة تحتاج إعادة طباعة).')),
            ])
            ->action(function (Tenant $record, array $data) {
                try {
                    $subdomain = app(OfficeProvisioner::class)->normaliseSubdomain($data['subdomain']);
                } catch (BusinessRuleException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }
                $domain = $subdomain.'.'.config('rowad.tenancy.base_domain');
                $current = self::platformDomain($record);
                if (Domain::where('domain', $domain)->where('id', '!=', $current?->id)->exists()) {
                    Notification::make()->danger()->title(__('النطاق :domain مستخدم من قبل', ['domain' => $domain]))->send();

                    return;
                }
                $current ? $current->update(['domain' => $domain]) : $record->domains()->create(['domain' => $domain]);
                Notification::make()->success()->title(__('أصبح رابط المؤسسة :domain', ['domain' => $domain]))->send();
            });
    }

    /** Sign the platform operator in as one of the office's staff accounts, on the office's own domain. */
    public static function loginAs(): Action
    {
        return Action::make('loginAs')
            ->label(__('الدخول كمدير المؤسسة'))
            ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
            ->visible(fn (Tenant $record) => $record->isAccessible() || $record->status === Tenant::STATUS_SUSPENDED)
            ->schema([
                Select::make('user_id')->label(__('الحساب'))->required()
                    ->options(fn (Tenant $record) => self::staff($record)),
            ])
            ->action(function (Tenant $record, array $data) {
                $domain = $record->domains->first()?->domain;
                if ($domain === null) {
                    return;
                }
                activity()->performedOn($record)->withProperties(['user_id' => (int) $data['user_id']])->log('platform.login_as');
                $root = request()->getScheme().'://'.$domain.(in_array(request()->getPort(), [80, 443], true) ? '' : ':'.request()->getPort());
                URL::forceRootUrl($root);
                try {
                    $url = URL::temporarySignedRoute('platform.access', now()->addMinutes(2), ['user' => (int) $data['user_id']]);
                } finally {
                    URL::forceRootUrl(null);
                }
                // opened in the same tab: the session is created on the office's domain
                return redirect()->away($url);
            });
    }

    public static function open(): Action
    {
        return Action::make('open')
            ->label(__('فتح لوحة المؤسسة'))
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->url(fn (Tenant $record) => request()->getScheme().'://'.$record->domains->first()?->domain.'/admin', shouldOpenInNewTab: true);
    }

    public static function upgradeHandled(): Action
    {
        return Action::make('upgradeHandled')
            ->label(__('تمت معالجة طلب الترقية'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->visible(fn (Tenant $record) => filled($record->upgrade_request))
            ->requiresConfirmation()
            ->modalDescription(__('غيّر الباقة من تعديل المؤسسة أولًا، ثم أزل الطلب من القائمة.'))
            ->action(fn (Tenant $record) => $record->forceFill(['upgrade_request' => null])->save());
    }

    public static function suspend(): Action
    {
        return Action::make('suspend')
            ->label(__('إيقاف'))
            ->color('danger')
            ->icon(Heroicon::OutlinedPauseCircle)
            ->requiresConfirmation()
            ->modalDescription(__('يتوقف دخول المؤسسة وبواباتها وموقعها فورًا، وتبقى بياناتها كما هي حتى التفعيل.'))
            ->visible(fn (Tenant $record) => $record->status !== Tenant::STATUS_SUSPENDED)
            ->action(fn (Tenant $record) => app(OfficeProvisioner::class)->suspend($record));
    }

    public static function activate(): Action
    {
        return Action::make('activate')
            ->label(__('تفعيل'))
            ->color('success')
            ->icon(Heroicon::OutlinedPlayCircle)
            ->requiresConfirmation()
            ->visible(fn (Tenant $record) => $record->status !== Tenant::STATUS_ACTIVE)
            ->action(fn (Tenant $record) => app(OfficeProvisioner::class)->activate($record));
    }

    public static function platformDomain(Tenant $record): ?Domain
    {
        return $record->domains()->where('domain', 'like', '%.'.config('rowad.tenancy.base_domain'))->first();
    }

    /** @return array<int, string> staff accounts inside the office's database */
    public static function staff(Tenant $record): array
    {
        try {
            return $record->run(fn () => User::query()->where('type', 'staff')->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'email'])->mapWithKeys(fn (User $u) => [$u->id => $u->name.' — '.$u->email])->all());
        } catch (\Throwable) {
            return [];
        }
    }
}
