<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;

/** @mixin \App\Models\Installment */
class InstallmentResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->enum($this->type),
            'unit' => $this->whenLoaded('unit', fn () => $this->unit ? ['id' => $this->unit->id, 'title' => $this->unit->title] : null),
            'contract' => ['type' => $this->contract_type === 'rent_contract' ? 'rent' : 'sale', 'id' => $this->contract_id],
            'due_date' => $this->date($this->due_date),
            'amount' => $this->money($this->amount),
            'vat_amount' => $this->money($this->vat_amount),
            'paid_amount' => $this->money($this->paidTotal()),
            'remaining' => $this->money(max(0, $this->remainingAmount())),
            'is_collected' => $this->is_collected,
            'is_overdue' => ! $this->is_collected && $this->due_date?->lt(today()),
            'collected_at' => $this->collected_at?->toIso8601String(),
        ];
    }
}
