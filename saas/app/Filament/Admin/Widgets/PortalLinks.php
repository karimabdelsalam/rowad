<?php

namespace App\Filament\Admin\Widgets;

use chillerlan\QRCode\QRCode;
use Filament\Widgets\Widget;

/** Legacy dashboard qrcode(): printable QR codes for the owner and client portals (and the office website). */
class PortalLinks extends Widget
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    protected static ?int $sort = 10;

    protected string $view = 'filament.widgets.portal-links';

    /** @return list<array{label: string, url: string, qr: string}> */
    public function getLinks(): array
    {
        $links = [
            ['label' => __('بوابة الملاك'), 'url' => url('/owner')],
            ['label' => __('بوابة العملاء'), 'url' => url('/client')],
        ];
        if (\App\Support\Website::enabled()) {
            $links[] = ['label' => __('موقع المكتب'), 'url' => url('/')];
        }

        return array_map(fn (array $link) => $link + ['qr' => (new QRCode)->render($link['url'])], $links);
    }
}
