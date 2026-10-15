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

    /** @return list<array{label: string, url: string, qr: string, prompt: string, print: string}> */
    public function getLinks(): array
    {
        $kinds = ['owner', 'client', ...(\App\Support\Website::enabled() ? ['site'] : [])];

        return array_map(fn (string $kind) => self::link($kind) + ['print' => route('documents.qr', $kind)], $kinds);
    }

    /** @return array{label: string, url: string, qr: string, prompt: string} */
    public static function link(string $kind): array
    {
        [$label, $path, $prompt] = match ($kind) {
            'owner' => [__('بوابة الملاك'), '/owner', __('امسح الرمز بكاميرا جوالك للدخول إلى بوابة الملاك: كشف حسابك، وحداتك، عقودك وتحصيلاتها')],
            'client' => [__('بوابة العملاء'), '/client', __('امسح الرمز بكاميرا جوالك للدخول إلى بوابة العملاء: عقدك، دفعاتك القادمة، وطلبات الصيانة')],
            'site' => [__('موقع المؤسسة'), '/', __('امسح الرمز بكاميرا جوالك لتصفح الوحدات المتاحة للإيجار والبيع')],
        };
        $url = url($path);

        return ['label' => $label, 'url' => $url, 'prompt' => $prompt, 'qr' => (new QRCode)->render($url)];
    }
}
