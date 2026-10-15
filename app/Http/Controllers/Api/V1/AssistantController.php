<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\Assistant;
use App\Support\PlanLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The AI assistant for the mobile app: same assistant, tools, permissions and monthly quota as the web panels.
 * Staff, owners and tenants each get the assistant of their own panel.
 */
class AssistantController extends Controller
{
    public function ask(Request $request, Assistant $assistant): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);
        $user = $request->user();

        $conversation = isset($data['conversation_id'])
            ? AiConversation::where('user_id', $user->id)->findOrFail($data['conversation_id'])
            : null;

        try {
            $conversation ??= $assistant->startConversation($user);
            $answer = $assistant->ask($conversation, $data['question']);
        } catch (AiUnavailable $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'ai_unavailable', 'conversation_id' => $conversation?->id], 503);
        }

        return response()->json([
            'conversation_id' => $conversation->id,
            'answer' => $this->message($answer),
            'remaining_this_month' => $this->remaining($user),
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $items = AiConversation::where('user_id', $request->user()->id)->latest('updated_at')->paginate(20);

        return response()->json([
            'data' => $items->getCollection()->map(fn (AiConversation $c) => [
                'id' => $c->id, 'title' => $c->title, 'type' => $c->context['type'] ?? 'chat', 'updated_at' => $c->updated_at?->toIso8601String(),
            ])->all(),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'total' => $items->total()],
            'remaining_this_month' => $this->remaining($request->user()),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $conversation = AiConversation::where('user_id', $request->user()->id)->findOrFail($id);

        return response()->json([
            'id' => $conversation->id,
            'title' => $conversation->title,
            'messages' => $conversation->messages->map(fn (AiMessage $m) => $this->message($m))->all(),
        ]);
    }

    public function destroy(Request $request, int $id): Response
    {
        AiConversation::where('user_id', $request->user()->id)->findOrFail($id)->delete();

        return response()->noContent();
    }

    /** @return array<string, mixed> answers are markdown; apps render them with a markdown view */
    private function message(AiMessage $m): array
    {
        return [
            'id' => $m->id, 'role' => $m->role, 'content' => $m->content, 'format' => 'markdown',
            'sources' => array_map(fn ($t) => \App\Filament\Pages\AssistantPage::toolLabel($t), $m->tools_used ?? []),
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    /** The office's quota is only shown to its staff. */
    private function remaining($user): ?int
    {
        $limit = PlanLimits::limit('ai');

        return $limit === null || Assistant::panelFor($user) !== 'admin' ? null : max(0, $limit - (int) PlanLimits::used('ai'));
    }
}
