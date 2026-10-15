<x-filament-widgets::widget>
    <x-filament::section :heading="__('روابط البوابات')" :description="__('اطبع الرمز أو شاركه مع الملاك والعملاء للدخول إلى بواباتهم.')">
        <div style="display:flex;flex-wrap:wrap;gap:1.5rem;justify-content:space-around">
            @foreach ($this->getLinks() as $link)
                <div style="text-align:center">
                    <img src="{{ $link['qr'] }}" alt="QR" width="130" height="130" style="margin:auto">
                    <div style="font-weight:700">{{ $link['label'] }}</div>
                    <a href="{{ $link['url'] }}" target="_blank" dir="ltr" style="font-size:.75rem;color:rgb(var(--primary-600))">{{ $link['url'] }}</a>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
