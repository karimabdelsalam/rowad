<?php

namespace App\Filament\Widgets;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\Assistant;
use App\Services\Ai\Tools\Tool;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

/**
 * "ملخصي الذكي" on the owner and tenant dashboards: the AI reads only the signed-in person's own data
 * (the same tools as their assistant) and writes a short summary. Generated on request, kept per person.
 */
abstract class PortalAiSummary extends Widget
{
    protected static ?int $sort = 11;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.ai-insights';

    /** @var class-string<\Filament\Pages\Page> */
    abstract protected function assistantPage(): string;

    abstract protected function instruction(): string;

    abstract protected function emptyText(): string;

    public static function canView(): bool
    {
        return \App\Filament\Pages\AssistantPage::canAccess();
    }

    public function generate(): void
    {
        $user = auth()->user();
        $data = collect(Assistant::tools($user))->mapWithKeys(fn (Tool $tool) => [$tool->name => $tool->run([], $user)])->all();
        try {
            app(Assistant::class)->analyze($user, __('ملخصي :date', ['date' => today()->toDateString()]), $data, $this->instruction(), 'summary');
        } catch (AiUnavailable $e) {
            Notification::make()->title($e->getMessage())->warning()->send();
        }
    }

    protected function getViewData(): array
    {
        $latest = AiMessage::where('kind', 'summary')->where('role', 'assistant')
            ->whereIn('ai_conversation_id', AiConversation::where('user_id', auth()->id())->select('id'))
            ->latest('id')->first();
        $page = $this->assistantPage();

        return [
            'insight' => $latest,
            'isToday' => $latest?->created_at->isToday() ?? false,
            'chatUrl' => $latest ? $page::getUrl(['c' => $latest->ai_conversation_id]) : $page::getUrl(),
            'title' => __('ملخصي الذكي'),
            'subtitle' => __('ملخص بالذكاء الاصطناعي لبياناتك'),
            'empty' => $this->emptyText(),
        ];
    }
}
