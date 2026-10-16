<?php

namespace App\Filament\Platform\Resources\Tenants\Schemas;

use App\Filament\Infolists\Parts;
use App\Filament\Platform\Resources\Tenants\TenantActions;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Platform\SubscriptionManager;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/** One office in full: subscription state, what it uses of its plan, its admins, and its payment history. */
class TenantInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Parts::header(Heroicon::OutlinedBuildingStorefront, function (Tenant $r) {
                $paidUntil = self::paidUntil($r);

                return [
                    'kicker' => ($r->plan?->name_ar ?? __('بدون باقة')).' · '.TenantForm::statuses()[$r->status],
                    'title' => $r->name,
                    'sub' => [$r->domains->pluck('domain')->implode(' · '), $r->email, $r->phone],
                    'facts' => [
                        Parts::fact('الحالة', TenantForm::statuses()[$r->status], self::tone($r->status)),
                        $r->status === Tenant::STATUS_TRIAL
                            ? Parts::fact('تنتهي التجربة', $r->trial_ends_at?->format('Y-m-d'), $r->trial_ends_at?->lt(now()->addDays(7)) ? 'warning' : null)
                            : Parts::fact('مدفوع حتى', $paidUntil?->format('Y-m-d') ?? '-', $paidUntil && $paidUntil->lt(now()->addDays(7)) ? 'danger' : null),
                        Parts::fact('الوحدات', isset($r->usage['units']) ? number_format($r->usage['units']).' / '.($r->plan?->max_units ? number_format($r->plan->max_units) : '∞') : '-'),
                        Parts::fact('مسجلة منذ', $r->created_at?->locale(app()->getLocale())->diffForHumans()),
                    ],
                ];
            }),
            Grid::make(1)->columnSpan(['lg' => 2])->schema([
                Section::make(__('الاستخدام من الباقة'))->icon(Heroicon::OutlinedChartBar)->columns(['default' => 2, 'md' => 5])
                    ->description(fn (Tenant $r) => isset($r->usage['recorded_at']) ? __('آخر قياس: :at', ['at' => Carbon::parse($r->usage['recorded_at'])->format('Y-m-d H:i')]) : __('لم يُقس بعد — يعمل rowad:usage كل ساعة'))
                    ->schema([
                        self::quota('units', 'الوحدات', 'max_units'),
                        self::quota('users', 'المستخدمون', 'max_users'),
                        self::quota('storage_mb', 'التخزين (ميجا)', 'max_storage_mb'),
                        self::quota('ai_messages', 'أسئلة الذكاء الاصطناعي', 'max_ai_messages_per_month'),
                        TextEntry::make('ai_tokens')->label(__('رموز الذكاء الاصطناعي'))->placeholder('-')
                            ->state(fn (Tenant $r) => isset($r->usage['ai_input_tokens']) ? number_format((int) $r->usage['ai_input_tokens'] + (int) ($r->usage['ai_output_tokens'] ?? 0)) : null),
                    ]),
                Section::make(__('مدراء المؤسسة'))->icon(Heroicon::OutlinedUsers)->schema([
                    RepeatableEntry::make('admins')->hiddenLabel()->columns(['default' => 2, 'md' => 4])
                        ->placeholder(__('تعذر قراءة قاعدة المؤسسة'))
                        ->state(fn (Tenant $r) => self::admins($r))
                        ->schema([
                            TextEntry::make('name')->label(__('الاسم'))->weight('bold'),
                            TextEntry::make('email')->label(__('البريد الإلكتروني'))->extraAttributes(['dir' => 'ltr']),
                            TextEntry::make('roles')->label(__('الأدوار'))->badge()->color('info')->placeholder('-'),
                            TextEntry::make('last_login_at')->label(__('آخر دخول'))->placeholder(__('لم يدخل بعد')),
                        ]),
                ]),
                Section::make(__('سجل الاشتراكات'))->icon(Heroicon::OutlinedClock)->schema([
                    RepeatableEntry::make('subscriptions')->hiddenLabel()->columns(['default' => 2, 'md' => 5])
                        ->placeholder(__('لا توجد اشتراكات مسجلة بعد'))
                        ->state(fn (Tenant $r) => $r->subscriptions()->with('plan')->latest('starts_at')->get())
                        ->schema([
                            TextEntry::make('plan.name_ar')->label(__('الباقة'))->weight('bold'),
                            TextEntry::make('status')->label(__('الحالة'))->badge()
                                ->formatStateUsing(fn ($state) => __(SubscriptionManager::STATUSES[$state] ?? $state))
                                ->color(fn ($state) => match ($state) { 'active' => 'success', 'trialing' => 'info', 'past_due' => 'warning', default => 'gray' }),
                            TextEntry::make('period')->label(__('الفترة'))
                                ->state(fn ($record) => $record->starts_at?->format('Y-m-d').' ← '.($record->ends_at?->format('Y-m-d') ?? '…')),
                            TextEntry::make('amount')->label(__('المبلغ'))->numeric(2)->suffix(' '.__('ر.س')),
                            TextEntry::make('gateway_reference')->label(__('المرجع'))->placeholder('-')->fontFamily('mono')->size('xs'),
                        ]),
                ]),
            ]),
            Grid::make(1)->columnSpan(['lg' => 1])->schema([
                Section::make(__('الاشتراك'))->icon(Heroicon::OutlinedCreditCard)->columns(2)->schema([
                    TextEntry::make('plan.name_ar')->label(__('الباقة'))->badge()->color('primary')->placeholder(__('بدون باقة')),
                    TextEntry::make('status')->label(__('الحالة'))->badge()->formatStateUsing(fn ($state) => TenantForm::statuses()[$state] ?? $state)->color(fn ($state) => self::color($state)),
                    TextEntry::make('trial_ends_at')->label(__('نهاية التجربة'))->date('Y-m-d')->placeholder('-'),
                    TextEntry::make('paid_until')->label(__('مدفوع حتى'))->placeholder('-')->state(fn (Tenant $r) => self::paidUntil($r)?->format('Y-m-d')),
                    TextEntry::make('mrr')->label(__('إيراد شهري'))->columnSpanFull()
                        ->state(fn (Tenant $r) => ($s = $r->activeSubscription) ? number_format($s->billing_cycle === 'yearly' ? (float) $s->amount / 12 : (float) $s->amount, 0).' '.__('ر.س') : '-'),
                    TextEntry::make('upgrade_request.plan')->label(__('طلب ترقية'))->badge()->color('warning')->placeholder('-')->columnSpanFull()
                        ->helperText(fn (Tenant $r) => $r->upgrade_request['note'] ?? null),
                ]),
                Section::make(__('التواصل'))->icon(Heroicon::OutlinedPhone)->schema([
                    Parts::email(),
                    Parts::phone('phone'),
                    TextEntry::make('domains.domain')->label(__('النطاقات'))->badge()->color('gray')->extraAttributes(['dir' => 'ltr']),
                ]),
                Section::make(__('السجل'))->icon(Heroicon::OutlinedClock)->collapsed()->columns(2)->schema([
                    TextEntry::make('created_at')->label(__('تاريخ التسجيل'))->dateTime('Y-m-d H:i'),
                    TextEntry::make('id')->label(__('المعرّف'))->fontFamily('mono')->size('xs')->copyable(),
                ]),
            ]),
        ]);
    }

    private static function quota(string $key, string $label, string $planColumn): TextEntry
    {
        return TextEntry::make('usage_'.$key)->label(__($label))->placeholder('-')->weight('bold')
            ->state(fn (Tenant $r) => isset($r->usage[$key]) ? number_format((float) $r->usage[$key]).' / '.($r->plan?->{$planColumn} ? number_format($r->plan->{$planColumn}) : '∞') : null)
            ->color(fn (Tenant $r) => isset($r->usage[$key]) && $r->plan?->{$planColumn} && $r->usage[$key] >= $r->plan->{$planColumn} * 0.9 ? 'danger' : null);
    }

    public static function paidUntil(Tenant $r): ?Carbon
    {
        $max = $r->subscriptions()->whereIn('status', ['active', 'past_due'])->max('ends_at');

        return $max ? Carbon::parse($max) : null;
    }

    /** @return list<array{name: string, email: ?string, roles: list<string>, last_login_at: ?string}> */
    private static function admins(Tenant $r): array
    {
        try {
            return $r->run(fn () => User::query()->where('type', 'staff')->with('roles')->orderByDesc('last_login_at')->limit(10)->get()
                ->map(fn (User $u) => ['name' => $u->name, 'email' => $u->email, 'roles' => $u->is_super_admin ? [__('مدير النظام')] : $u->getRoleNames()->all(),
                    'last_login_at' => $u->last_login_at?->format('Y-m-d H:i')])->all());
        } catch (\Throwable) {
            return [];
        }
    }

    public static function tone(string $status): ?string
    {
        return match ($status) { Tenant::STATUS_ACTIVE => 'success', Tenant::STATUS_TRIAL => 'info', Tenant::STATUS_PAST_DUE => 'warning', default => 'danger' };
    }

    public static function color(string $status): string
    {
        return self::tone($status) ?? 'gray';
    }
}
