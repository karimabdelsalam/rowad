@php
    use App\Models\Tenant;
    use App\Support\Settings;

    $user = filament()->auth()->user();
    $panel = filament()->getCurrentPanel()?->getId();
    $roleLabels = ['admin' => 'مدير', 'accountant' => 'محاسب', 'agent' => 'مسوّق', 'viewer' => 'مشاهد'];
    $role = $user?->roles?->first()?->name;
    $who = match (true) {
        $panel === 'owner' => __('مالك'),
        $panel === 'client' => __('مستأجر / عميل'),
        $user?->is_super_admin ?? false => __('مدير النظام'),
        default => __($roleLabels[$role] ?? 'موظف'),
    };
    $initials = mb_substr(trim((string) $user?->name), 0, 1);

    $plan = null;
    if ($panel === 'admin' && tenancy()->initialized && \App\Filament\Admin\Pages\Subscription::canAccess()) {
        /** @var Tenant $office */
        $office = tenant();
        $endsAt = match ($office->status) {
            Tenant::STATUS_TRIAL => $office->trial_ends_at,
            Tenant::STATUS_ACTIVE => ($end = $office->subscriptions()->where('status', 'active')->max('ends_at')) ? \Illuminate\Support\Carbon::parse($end) : null,
            default => null,
        };
        $plan = [
            'url' => \App\Filament\Admin\Pages\Subscription::getUrl(),
            'name' => $office->status === Tenant::STATUS_TRIAL ? __('فترة تجريبية') : ($office->plan?->name ?? __('الاشتراك')),
            'until' => $endsAt && $endsAt->isFuture() ? __('حتى :date', ['date' => $endsAt->translatedFormat('j M Y')]) : null,
            'tone' => match ($office->status) { Tenant::STATUS_PAST_DUE, Tenant::STATUS_SUSPENDED => 'danger', Tenant::STATUS_TRIAL => 'warning', default => 'ok' },
        ];
    }
    $contact = $panel !== 'admin' && tenancy()->initialized ? (Settings::get('mobile') ?: Settings::get('phone')) : null;
@endphp
<div class="rowad-side-footer" x-show="$store.sidebar.isOpen" x-cloak>
    @if ($plan)
        <a href="{{ $plan['url'] }}" class="rowad-side-plan is-{{ $plan['tone'] }}">
            <span class="rowad-side-plan__dot"></span>
            <span class="rowad-side-plan__text"><b>{{ $plan['name'] }}</b>@if ($plan['until']) <small>{{ $plan['until'] }}</small>@endif</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
        </a>
    @elseif ($contact)
        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact) }}" class="rowad-side-plan is-ok">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>
            <span class="rowad-side-plan__text"><b>{{ __('تواصل مع المكتب') }}</b><small dir="ltr">{{ $contact }}</small></span>
        </a>
    @endif
    @if ($user)
        <a href="{{ filament()->hasProfile() ? filament()->getProfileUrl() : '#' }}" class="rowad-side-user">
            <span class="rowad-side-user__avatar">{{ $initials }}</span>
            <span class="rowad-side-user__text"><b>{{ $user->name }}</b><small>{{ $who }}</small></span>
        </a>
    @endif
</div>
