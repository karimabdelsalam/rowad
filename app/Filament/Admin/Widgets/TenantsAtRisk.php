<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Clients\ClientResource;
use App\Services\Insights\PaymentRisk;
use Filament\Widgets\Widget;

/** Tenants most likely to pay late, from their own payment history — a call list before arrears grow. */
class TenantsAtRisk extends Widget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 6];

    protected string $view = 'filament.widgets.tenants-at-risk';

    public static function canView(): bool
    {
        return ClientResource::canViewAny();
    }

    /** @return list<array{name: string, url: string, mobile: ?string, score: int, level: string, label: string, reason: string, overdue: float}> */
    public function getRows(): array
    {
        return array_map(fn (array $r) => [
            'name' => $r['client']->displayName(),
            'url' => ClientResource::getUrl('view', ['record' => $r['client']]),
            'mobile' => $r['client']->mobile,
            'score' => $r['score'],
            'level' => $r['level'],
            'label' => $r['label'],
            'reason' => $r['reasons'][0] ?? '',
            'overdue' => (float) $r['stats']['overdue_amount'],
        ], app(PaymentRisk::class)->atRisk(6));
    }
}
