<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Pages\Assistant as AssistantPage;
use App\Models\AiMessage;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\Assistant;
use App\Services\Reports\DashboardStats;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

/**
 * "رؤى ذكية": a short AI briefing on the office's figures. Generated on request (it uses one question
 * from the plan), then shared by every staff member who opens the dashboard that day.
 */
class AiInsights extends Widget
{
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.ai-insights';

    public static function canView(): bool
    {
        $user = auth()->user();

        return AssistantPage::canAccess() && $user !== null && ($user->is_super_admin || $user->can('reports.profits'));
    }

    public function generate(): void
    {
        $stats = app(DashboardStats::class);
        try {
            app(Assistant::class)->analyze(auth()->user(), __('رؤى اليوم :date', ['date' => today()->toDateString()]), [
                'today' => today()->toDateString(),
                'office' => $stats->office(),
                'attention' => $stats->attention(),
                'last_6_months' => $stats->recentMonths(6),
                'collection_rate_12_months' => $stats->collectionRate(),
                'monthly_collections_this_year' => $stats->monthlyCollections((int) now()->year),
                'top_buildings_collected_this_year' => $stats->topBuildings(),
                'unit_statuses' => $stats->unitStatuses(),
            ], 'Write today\'s briefing for the office manager from these figures: 4-6 short bullet insights, each with its key number '
                .'(collections vs last months, arrears, occupancy and vacancies, contracts ending soon, strongest/weakest buildings, anything unusual), '
                .'then a heading "أولويات اليوم" (or "Today\'s priorities" in English) with 3 concrete actions. Under 200 words, no tables, no preamble.', 'insights');
        } catch (AiUnavailable $e) {
            Notification::make()->title($e->getMessage())->warning()->send();
        }
    }

    protected function getViewData(): array
    {
        $latest = AiMessage::where('kind', 'insights')->where('role', 'assistant')->latest('id')->first();

        return [
            'insight' => $latest,
            'isToday' => $latest?->created_at->isToday() ?? false,
            'chatUrl' => $latest ? AssistantPage::getUrl(['c' => $latest->ai_conversation_id]) : AssistantPage::getUrl(),
            'title' => __('رؤى ذكية'),
            'subtitle' => __('ملخص يومي بالذكاء الاصطناعي لأرقام مؤسستك'),
            'empty' => __('اضغط "توليد الرؤى" ليقرأ المساعد التحصيل والمتأخرات والإشغال والعقود القريبة من الانتهاء، ويكتب لك أهم الملاحظات وأولويات اليوم.'),
        ];
    }
}
