<?php

namespace App\Http\Resources\V1;

use App\Models\RentContract;
use Illuminate\Http\Request;

/** Rent and sale contracts share one shape; type-specific fields are null for the other kind. */
class ContractResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $rent = $this->resource instanceof RentContract;

        return [
            'id' => $this->id,
            'type' => $rent ? 'rent' : 'sale',
            'number' => $this->number,
            'status' => $this->enum($this->status),
            'unit' => $this->whenLoaded('unit', fn () => $this->unit ? ['id' => $this->unit->id, 'title' => $this->unit->title] : null),
            'clients' => $this->whenLoaded('clients', fn () => $this->clients->map(fn ($c) => ['id' => $c->id, 'name' => $c->full_name])),
            'contract_date' => $this->date($this->contract_date),
            'start_date' => $rent ? $this->date($this->start_date) : null,
            'end_date' => $rent ? $this->date($this->end_date) : null,
            'payment_cycle' => $rent ? $this->enum($this->payment_cycle) : null,
            'rent_amount' => $rent ? $this->money($this->rent_amount) : null,
            'vat_amount' => $rent ? $this->money($this->vat_amount) : null,
            'next_payment_date' => $rent ? $this->date($this->next_payment_date) : null,
            'total_amount' => $rent ? null : $this->money($this->total_amount),
            'payment_type' => $rent ? null : $this->enum($this->payment_type),
            'installments' => InstallmentResource::collection($this->whenLoaded('installments')),
        ];
    }
}
