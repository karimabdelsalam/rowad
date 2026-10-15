<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\UserResource;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Profile, push device registration (legacy pns/device_token) and in-app notifications. */
class AccountController extends Controller
{
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function registerDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', Rule::in(['ios', 'android', 'web'])],
        ]);

        // A device belongs to whoever signed in on it last (legacy checkAdded → update userid).
        DeviceToken::updateOrCreate(
            ['token_hash' => hash('sha256', $data['token'])],
            ['token' => $data['token'], 'platform' => $data['platform'], 'user_id' => $request->user()->id],
        );

        return response()->json(['message' => __('تمت الإضافة بنجاح')], 201);
    }

    public function removeDevice(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:512']]);
        DeviceToken::where('token_hash', hash('sha256', $data['token']))->where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => __('تم الحذف')]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $page = $request->user()->notifications()->latest()->paginate($this->perPage($request));

        return response()->json([
            'data' => collect($page->items())->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? null,
                'body' => $n->data['body'] ?? null,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(),
                'unread' => $request->user()->unreadNotifications()->count()],
        ]);
    }

    public function markNotificationsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()
            ->when($request->filled('id'), fn ($query) => $query->whereKey($request->input('id')))
            ->update(['read_at' => now()]);

        return response()->json(['message' => __('تم التحديث')]);
    }
}
