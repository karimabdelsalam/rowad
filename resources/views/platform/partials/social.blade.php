{{-- The platform's social profiles (platform console › موقع عقار نوفا), as icons in the footer --}}
@php
    $links = \App\Support\PlatformSettings::socialLinks();
    $icons = [
        'x' => '<path fill="currentColor" d="M18.24 2.25h3.31l-7.23 8.26 8.5 11.24h-6.65l-5.21-6.82-5.97 6.82H1.68l7.73-8.84L1.25 2.25h6.83l4.71 6.23zm-1.16 17.52h1.83L7.08 4.13H5.12z"/>',
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.3" fill="currentColor"/>',
        'linkedin' => '<path fill="currentColor" d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.34V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46zM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13M7.12 20.45H3.56V9h3.56z"/>',
        'facebook' => '<path fill="currentColor" d="M24 12.07C24 5.41 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.04V9.41c0-3.02 1.8-4.7 4.54-4.7 1.31 0 2.68.24 2.68.24v2.97h-1.5c-1.5 0-1.96.93-1.96 1.89v2.26h3.33l-.53 3.5h-2.8V24C19.62 23.1 24 18.1 24 12.07"/>',
        'tiktok' => '<path fill="currentColor" d="M19.6 6.7a4.8 4.8 0 0 1-3.8-4.2V2h-3.4v13.4a2.9 2.9 0 1 1-2-2.75V9.2a6.3 6.3 0 1 0 5.4 6.23V8.6a8.2 8.2 0 0 0 3.8 1.2z"/>',
        'youtube' => '<path fill="currentColor" d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8M9.6 15.6V8.4l6.3 3.6z"/>',
        'snapchat' => '<path fill="currentColor" d="M12 2c3 0 5 2.2 5 5.2v2.3l1.6-.5c.6-.2 1 .5.5.9l-1.8 1.2c.6 1.9 2 3.3 3.7 3.8.5.1.5.8 0 1-1 .4-2 .4-2.4.8-.2.3 0 1-.5 1.2-.6.2-1.6-.3-2.6.1-1 .4-1.8 1.5-3.5 1.5s-2.5-1.1-3.5-1.5c-1-.4-2-.1-2.6-.1-.5-.2-.3-.9-.5-1.2-.4-.4-1.4-.4-2.4-.8-.5-.2-.5-.9 0-1 1.7-.5 3.1-1.9 3.7-3.8l-1.8-1.2c-.5-.4-.1-1.1.5-.9l1.6.5V7.2C7 4.2 9 2 12 2"/>',
    ];
@endphp
@if ($links !== [])
    <div class="social" aria-label="{{ __('حساباتنا على التواصل الاجتماعي') }}">
        @foreach ($links as $network => $url)
            <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ \App\Support\PlatformSettings::SOCIAL[$network] }}" title="{{ \App\Support\PlatformSettings::SOCIAL[$network] }}">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">{!! $icons[$network] !!}</svg>
            </a>
        @endforeach
    </div>
@endif
