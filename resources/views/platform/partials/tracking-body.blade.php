@if (filled($gtm = \App\Support\PlatformSettings::get('analytics_gtm')) && preg_match('/^GTM-[A-Z0-9]+$/i', $gtm))
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtm }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
@endif
