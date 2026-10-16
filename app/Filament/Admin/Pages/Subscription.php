<?php

namespace App\Filament\Admin\Pages;

use App\Jobs\SendOutgoingMessage;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\PlanLimits;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Throwable;
use UnitEnum;

/** The organisation's plan, what it has used of it, and a way to ask for an upgrade (legacy updatecore plan page). */
class Subscription extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 9;

    protected static ?string $slug = 'subscription';

    protected string $view = 'filament.admin.subscription';

    public static function getNavigationLabel(): string
    {
        return __('الاشتراك');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    public function getTitle(): string
    {
        return __('الاشتراك');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return tenancy()->initialized && $user !== null && ($user->is_super_admin || $user->can('settings.update'));
    }

    protected function getViewData(): array
    {
        /** @var Tenant $office */
        $office = tenant();
        $plan = PlanLimits::plan();
        $subscription = $office->activeSubscription;
        $ar = app()->getLocale() === 'ar';

        $quotas = collect(PlanLimits::QUOTAS)->map(function (array $quota, string $key) {
            $limit = PlanLimits::limit($key);
            $used = PlanLimits::used($key);

            return [
                'label' => __($quota[1]),
                'used' => $used,
                'limit' => $limit,
                'percent' => $limit ? min(100, (int) round($used / $limit * 100)) : null,
                'level' => $limit === null ? 'ok' : ($used >= $limit ? 'full' : ($used >= $limit * 0.8 ? 'near' : 'ok')),
            ];
        })->all();

        return [
            'office' => $office,
            'plan' => $plan,
            'planName' => $plan ? ($ar ? $plan->name_ar : $plan->name_en) : __('غير محدودة'),
            'status' => self::statusLabel($office),
            'statusColor' => match ($office->status) {
                Tenant::STATUS_ACTIVE => 'success',
                Tenant::STATUS_TRIAL => 'info',
                Tenant::STATUS_PAST_DUE => 'warning',
                default => 'danger',
            },
            'endsAt' => $office->status === Tenant::STATUS_TRIAL ? $office->trial_ends_at : $subscription?->ends_at,
            'endsLabel' => $office->status === Tenant::STATUS_TRIAL ? __('تنتهي التجربة المجانية') : __('ينتهي الاشتراك'),
            'quotas' => $quotas,
            'features' => collect(PlanLimits::FEATURES)->map(fn ($label, $key) => ['label' => __($label), 'on' => PlanLimits::allows($key)])->all(),
            'request' => $office->upgrade_request ? [
                'plan' => ($requested = Plan::find($office->upgrade_request['plan_id'] ?? null)) ? ($ar ? $requested->name_ar : $requested->name_en) : '-',
                'at' => $office->upgrade_request['at'],
            ] : null,
            'storageMeasuredAt' => $office->usage['recorded_at'] ?? null,
        ];
    }

    /**
     * A notice at the top of every admin page while the office has to act: trial or paid period ending
     * within a week, or payment overdue. Shown to those who manage settings (they can pay or upgrade).
     *
     * @return array{level: string, text: string, url: string}|null
     */
    public static function notice(): ?array
    {
        if (! tenancy()->initialized || ! static::canAccess()) {
            return null;
        }
        /** @var Tenant $office */
        $office = tenant();
        $url = static::getUrl();

        if ($office->status === Tenant::STATUS_PAST_DUE) {
            return ['level' => 'danger', 'url' => $url, 'text' => __('انتهت فترة اشتراك المؤسسة. الخدمة مستمرة لفترة سماح قصيرة ثم تتوقف حتى التجديد.')];
        }

        $endsAt = match ($office->status) {
            Tenant::STATUS_TRIAL => $office->trial_ends_at,
            Tenant::STATUS_ACTIVE => ($end = $office->subscriptions()->where('status', 'active')->max('ends_at')) ? \Illuminate\Support\Carbon::parse($end) : null,
            default => null,
        };
        if ($endsAt === null || $endsAt->isPast() || $endsAt->gt(now()->addDays(7))) {
            return null;
        }
        $days = max(1, (int) today()->diffInDays($endsAt->copy()->startOfDay()));

        return ['level' => 'warning', 'url' => $url, 'text' => $office->status === Tenant::STATUS_TRIAL
            ? trans_choice('{1} تنتهي الفترة التجريبية غدًا. اختر باقتك لتستمر الخدمة.|[2,*] تنتهي الفترة التجريبية بعد :count أيام. اختر باقتك لتستمر الخدمة.', $days)
            : trans_choice('{1} ينتهي اشتراك المؤسسة غدًا. جدّد لتستمر الخدمة دون انقطاع.|[2,*] ينتهي اشتراك المؤسسة بعد :count أيام. جدّد لتستمر الخدمة دون انقطاع.', $days)];
    }

    public static function statusLabel(Tenant $office): string
    {
        return __(match ($office->status) {
            Tenant::STATUS_TRIAL => 'فترة تجريبية',
            Tenant::STATUS_ACTIVE => 'نشط',
            Tenant::STATUS_PAST_DUE => 'متأخر السداد',
            Tenant::STATUS_SUSPENDED => 'موقوف',
            default => 'ملغي',
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cancel')
                ->label(__('إلغاء الاشتراك'))
                ->color('danger')
                ->icon(Heroicon::OutlinedXCircle)
                ->visible(fn () => ! \App\Support\Demo::active() && tenant()->status !== Tenant::STATUS_CANCELLED && (auth()->user()?->is_super_admin || auth()->user()?->hasRole('admin')))
                ->modalHeading(__('إلغاء اشتراك المؤسسة'))
                ->modalDescription(__('يتوقف الدخول إلى لوحة الإدارة والبوابات والموقع فورًا لكل المستخدمين. تبقى بياناتك محفوظة :days يومًا يمكنك خلالها إعادة التفعيل بضغطة واحدة من رسالة التأكيد، ثم تُحذف نهائيًا.', ['days' => \App\Services\Platform\SubscriptionManager::RETENTION_DAYS]))
                ->modalSubmitActionLabel(__('إلغاء الاشتراك نهائيًا'))
                ->schema([
                    Radio::make('reason')->label(__('سبب الإلغاء'))->options(collect(\App\Services\Platform\SubscriptionManager::CANCEL_REASONS)->map(fn ($l) => __($l))->all())->required(),
                    Textarea::make('note')->label(__('ما الذي كان يمكن أن يُبقيك معنا؟'))->rows(3)->maxLength(1000),
                    \Filament\Forms\Components\TextInput::make('confirm')->label(__('اكتب اسم المؤسسة للتأكيد'))->required()
                        ->rule(fn () => fn (string $attribute, mixed $value, \Closure $fail) => trim((string) $value) === tenant()->name ?: $fail(__('الاسم غير مطابق'))),
                ])
                ->action(function (array $data) {
                    $user = auth()->user();
                    app(\App\Services\Platform\SubscriptionManager::class)->cancel(tenant(), $data['reason'], $data['note'] ?? null, $user?->name, $user?->email);
                    Notification::make()->success()->title(__('أُلغي الاشتراك. أرسلنا رابط إعادة التفعيل إلى بريدك.'))->send();
                    auth()->logout();
                    $this->redirect('/admin/login');
                }),
            Action::make('requestUpgrade')
                ->label(__('طلب ترقية الباقة'))
                ->icon(Heroicon::OutlinedArrowTrendingUp)
                ->visible(fn () => ! \App\Support\Demo::active() && self::upgradeOptions() !== [])
                ->modalHeading(__('طلب ترقية الباقة'))
                ->modalDescription(__('يصل طلبك لفريق عقار نوفا ويتواصل معك لإتمام الترقية. لا يتغير شيء في حسابك قبل الموافقة.'))
                ->modalSubmitActionLabel(__('إرسال الطلب'))
                ->schema([
                    Radio::make('plan_id')
                        ->label(__('الباقة المطلوبة'))
                        ->options(fn () => self::upgradeOptions())
                        ->required(),
                    Textarea::make('note')->label(__('ملاحظات'))->rows(3)->maxLength(1000),
                ])
                ->action(fn (array $data) => $this->requestUpgrade((int) $data['plan_id'], $data['note'] ?? null)),
        ];
    }

    /** @return array<int, string> */
    public static function upgradeOptions(): array
    {
        $ar = app()->getLocale() === 'ar';
        $current = PlanLimits::plan();

        return Plan::query()->where('is_active', true)
            ->when($current, fn ($q) => $q->whereKeyNot($current->getKey())->where('monthly_price', '>', $current->monthly_price))
            ->orderBy('monthly_price')->get()
            ->mapWithKeys(fn (Plan $plan) => [$plan->id => sprintf('%s — %s %s', $ar ? $plan->name_ar : $plan->name_en,
                number_format((float) $plan->monthly_price), __('ر.س / شهريًا'))])
            ->all();
    }

    public function requestUpgrade(int $planId, ?string $note): void
    {
        abort_unless(array_key_exists($planId, self::upgradeOptions()), 422);
        $plan = Plan::findOrFail($planId);
        /** @var Tenant $office */
        $office = tenant();
        $user = auth()->user();

        $office->forceFill(['upgrade_request' => [
            'plan_id' => $plan->id,
            'plan' => $plan->name_ar,
            'note' => $note,
            'by' => $user->name,
            'email' => $user->email,
            'at' => now()->toIso8601String(),
        ]])->save();

        $to = config('rowad.platform_admin.email');
        if (filled($to)) {
            $body = sprintf("<p>طلبت <b>%s</b> الترقية من باقة <b>%s</b> إلى <b>%s</b>.</p><p>مقدم الطلب: %s (%s)</p>%s",
                e($office->name), e(PlanLimits::plan()?->name_ar ?? '-'), e($plan->name_ar), e($user->name), e($user->email),
                filled($note) ? '<p>'.nl2br(e($note)).'</p>' : '');
            try {
                Mail::html(SendOutgoingMessage::layout('طلب ترقية باقة', $body), fn ($mail) => $mail->to($to)->subject('طلب ترقية باقة — '.$office->name));
            } catch (Throwable $e) {
                report($e); // the request is saved and shows on the platform panel even if mail is down
            }
        }

        Notification::make()->title(__('تم إرسال طلب الترقية. سنتواصل معك قريبًا.'))->success()->send();
    }
}
