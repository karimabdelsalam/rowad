<?php

namespace App\Filament\Pages;

use App\Models\AiConversation;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\Assistant;
use App\Support\PlanLimits;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

/**
 * "اسأل عقار نوفا": chat with the AI assistant about the office's data.
 * One page class per panel (admin, owner, client) so each gets its own tools and suggestions.
 */
abstract class AssistantPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?int $navigationSort = -1;

    protected static ?string $slug = 'assistant';

    protected string $view = 'filament.assistant';

    #[Url(as: 'c')]
    public ?int $conversationId = null;

    public string $question = '';

    /** The question being answered, shown straight away while the answer is fetched in a second request. */
    public ?string $pending = null;

    public ?string $error = null;

    /** "Ask about this" from a record page: ?about=unit:12 */
    #[Url]
    public ?string $about = null;

    /** @var array{type: string, id: int, label: string}|null */
    public ?array $subject = null;

    /** Records staff can ask about, with the permission needed to see them and starter questions. */
    private const SUBJECTS = [
        'unit' => [\App\Models\Unit::class, 'units.view', 'title', 'الوحدة', ['لخص وضع هذه الوحدة', 'هل على هذه الوحدة مبالغ متأخرة؟', 'ما تاريخ عقود هذه الوحدة؟']],
        'building' => [\App\Models\Building::class, 'buildings.view', 'title', 'المبنى', ['ما أداء هذا المبنى؟', 'ما الوحدات الشاغرة في هذا المبنى؟', 'كم المتأخرات على وحدات هذا المبنى؟']],
        'rent_contract' => [\App\Models\RentContract::class, 'rent_contracts.view', 'number', 'عقد الإيجار', ['لخص هذا العقد', 'ما الدفعات المتبقية على هذا العقد؟', 'هل المستأجر ملتزم بالسداد؟']],
        'owner' => [\App\Models\Owner::class, 'owners.view', 'full_name', 'المالك', ['لخص حساب هذا المالك', 'ما وحدات هذا المالك وحالتها؟', 'كم المستحق لهذا المالك؟']],
        'client' => [\App\Models\Client::class, 'clients.view', 'full_name', 'العميل', ['لخص وضع هذا العميل', 'هل على هذا العميل متأخرات؟', 'ما عقود هذا العميل؟']],
    ];

    public static function askAboutAction(string $type): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('askAi')
            ->label(__('اسأل المساعد عن هذا'))
            ->icon(Heroicon::OutlinedSparkles)
            ->color('gray')
            ->visible(fn () => static::canAccess())
            ->url(fn ($record) => static::getUrl(['about' => $type.':'.$record->getKey()]));
    }

    /** @return list<string> starter questions for this panel */
    abstract protected function suggestions(): array;

    protected function intro(): string
    {
        return 'اسأل عن أي شيء في بياناتك: الوحدات، العقود، التحصيل، المتأخرات، الأرباح… وأجيبك بالأرقام والتحليل.';
    }

    protected function example(): string
    {
        return 'اكتب سؤالك… مثال: كم المتأخرات على مبنى النخيل؟';
    }

    public static function getNavigationLabel(): string
    {
        return __('المساعد الذكي');
    }

    public function getTitle(): string
    {
        return __('المساعد الذكي');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && tenancy()->initialized && PlanLimits::allows('ai');
    }

    public function mount(): void
    {
        if ($this->conversationId && ! $this->conversation()) {
            $this->conversationId = null;
        }
        $this->subject = $this->about && ! $this->conversationId ? $this->resolveSubject($this->about) : null;
    }

    /** @return array{type: string, id: int, label: string}|null only records this user may open */
    private function resolveSubject(string $about): ?array
    {
        [$type, $id] = array_pad(explode(':', $about, 2), 2, null);
        $def = self::SUBJECTS[$type] ?? null;
        $user = auth()->user();
        if ($def === null || Assistant::panelFor($user) !== 'admin' || ! ($user->is_super_admin || $user->can($def[1]))) {
            return null;
        }
        $record = $def[0]::find((int) $id);

        return $record ? ['type' => $type, 'id' => $record->getKey(), 'label' => __($def[3]).' «'.$record->{$def[2]}.'»'] : null;
    }

    public function clearSubject(): void
    {
        $this->subject = null;
        $this->about = null;
    }

    public function conversation(): ?AiConversation
    {
        return $this->conversationId
            ? AiConversation::where('user_id', auth()->id())->find($this->conversationId)
            : null;
    }

    public function send(?string $text = null): void
    {
        $text = trim($text ?? $this->question);
        if ($text === '' || $this->pending !== null) {
            return;
        }
        $this->error = null;
        $this->question = '';
        $this->pending = Str::limit($text, 4000, '');
        $this->js('$wire.answer()');
    }

    public function answer(): void
    {
        if ($this->pending === null) {
            return;
        }
        $assistant = app(Assistant::class);
        try {
            $conversation = $this->conversation() ?? $assistant->startConversation(auth()->user(), $this->subject ? [
                'type' => 'record',
                'subject' => "{$this->subject['label']} ({$this->subject['type']} id {$this->subject['id']})",
            ] : null);
            $this->conversationId = $conversation->id;
            if (! app()->runningInConsole()) {
                set_time_limit(180); // an answer that needs several data lookups can take longer than PHP's default 30 s
            }
            $assistant->ask($conversation, $this->pending);
        } catch (AiUnavailable $e) {
            $this->error = $e->getMessage();
            $this->question = $this->pending; // nothing lost: the question goes back in the box
        } finally {
            $this->pending = null;
        }
        $this->dispatch('assistant-answered');
    }

    public function open(int $id): void
    {
        if (AiConversation::where('user_id', auth()->id())->whereKey($id)->exists()) {
            $this->conversationId = $id;
            $this->error = null;
        }
    }

    public function newConversation(): void
    {
        $this->conversationId = null;
        $this->error = null;
        $this->question = '';
        $this->clearSubject();
    }

    public function deleteConversation(int $id): void
    {
        AiConversation::where('user_id', auth()->id())->whereKey($id)->delete();
        if ($this->conversationId === $id) {
            $this->newConversation();
        }
    }

    protected function getViewData(): array
    {
        $conversation = $this->conversation();
        $limit = PlanLimits::limit('ai');

        return [
            'conversations' => AiConversation::where('user_id', auth()->id())->where('panel', Assistant::panelFor(auth()->user()))
                ->latest('updated_at')->limit(30)->get(['id', 'title', 'updated_at', 'context']),
            'messages' => $conversation?->messages()->get() ?? collect(),
            'suggestions' => array_map('__', $this->subject ? self::SUBJECTS[$this->subject['type']][4] : $this->suggestions()),
            // the office's quota is its business, not its owners' or tenants'
            'remaining' => $limit === null || Assistant::panelFor(auth()->user()) !== 'admin' ? null : max(0, $limit - (int) PlanLimits::used('ai')),
            'intro' => __($this->intro()),
            'example' => __($this->example()),
            'configured' => \App\Services\Ai\ClaudeClient::configured(),
        ];
    }

    /** Which data an answer relied on, shown under it so users know where numbers came from. */
    private const TOOL_LABELS = [
        'office_overview' => 'نظرة عامة على المؤسسة', 'monthly_collections' => 'التحصيل الشهري', 'collection_rate' => 'نسبة التحصيل',
        'search_units' => 'الوحدات', 'unit_details' => 'بيانات الوحدة', 'search_rent_contracts' => 'عقود الإيجار', 'rent_contract_details' => 'تفاصيل العقد',
        'installments' => 'الأقساط', 'search_people' => 'الملاك والعملاء', 'owner_balances' => 'أرصدة الملاك', 'finance_summary' => 'الملخص المالي',
        'buildings_performance' => 'أداء المباني', 'support_tickets' => 'تذاكر الدعم', 'my_overview' => 'ملخص حسابي', 'my_units' => 'وحداتي',
        'my_collections' => 'تحصيل وحداتي', 'my_statement' => 'كشف حسابي', 'my_contracts' => 'عقودي', 'my_payments' => 'دفعاتي', 'my_tickets' => 'طلبات الدعم',
    ];

    public static function toolLabel(string $tool): string
    {
        return __(self::TOOL_LABELS[$tool] ?? $tool);
    }

    /** Model answers are markdown; raw HTML in them is escaped, never rendered. */
    public static function markdown(string $markdown): string
    {
        return Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }
}
