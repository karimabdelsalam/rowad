<x-filament-widgets::widget>
    <x-filament::section :heading="__('روابط البوابات')" :description="__('اطبع الرمز أو شاركه مع الملاك والعملاء للدخول إلى بواباتهم.')">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(100px,1fr));gap:1rem;text-align:center">
            @foreach ($this->getLinks() as $link)
                <div style="text-align:center">
                    <img src="{{ $link['qr'] }}" alt="QR" width="96" height="96" style="margin:auto">
                    <div style="font-weight:700">{{ $link['label'] }}</div>
                    <a href="{{ $link['url'] }}" target="_blank" style="font-size:.75rem;color:#0f766e">{{ __('فتح') }}</a>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
