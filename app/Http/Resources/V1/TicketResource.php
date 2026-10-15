<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;

/** @mixin \App\Models\SupportTicket */
class TicketResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->enum($this->type),
            'status' => $this->enum($this->status),
            'unit_id' => $this->unit_id,
            'subject' => $this->subject,
            'message' => $this->message,
            'replies' => $this->whenLoaded('replies', fn () => $this->replies->map(fn ($r) => [
                'message' => $r->message, 'created_at' => $r->created_at?->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
