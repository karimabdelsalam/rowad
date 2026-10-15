<?php

namespace App\Filament\Platform\Widgets;

use App\Models\Tenant;
use Filament\Widgets\Widget;

/** Who uses the AI most this month, so the platform can watch its AI bill and suggest upgrades. */
class AiUsage extends Widget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.platform.ai-usage';

    protected function getViewData(): array
    {
        $offices = Tenant::query()->with('plan')->get()
            ->filter(fn (Tenant $t) => ($t->usage['ai_messages'] ?? 0) > 0)
            ->sortByDesc(fn (Tenant $t) => $t->usage['ai_messages'])
            ->values();

        return [
            'rows' => $offices->take(8)->map(fn (Tenant $t) => [
                'name' => $t->name,
                'messages' => (int) $t->usage['ai_messages'],
                'limit' => $t->plan?->max_ai_messages_per_month,
                'tokens' => (int) ($t->usage['ai_input_tokens'] ?? 0) + (int) ($t->usage['ai_output_tokens'] ?? 0),
            ])->all(),
            'totalMessages' => $offices->sum(fn (Tenant $t) => (int) $t->usage['ai_messages']),
            'totalInput' => $offices->sum(fn (Tenant $t) => (int) ($t->usage['ai_input_tokens'] ?? 0)),
            'totalOutput' => $offices->sum(fn (Tenant $t) => (int) ($t->usage['ai_output_tokens'] ?? 0)),
        ];
    }
}
