<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;

/** @mixin \App\Models\OwnerTransaction */
class LedgerEntryResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type?->title,
            'code' => $this->type?->code,
            'direction' => $this->enum($this->direction),
            'unit' => $this->unit ? ['id' => $this->unit->id, 'title' => $this->unit->title] : null,
            'amount' => $this->money($this->amount),
            'vat_amount' => $this->money($this->vat_amount),
            'due_date' => $this->date($this->due_date),
            'is_settled' => $this->is_settled,
            'settled_at' => $this->settled_at?->toIso8601String(),
        ];
    }
}
