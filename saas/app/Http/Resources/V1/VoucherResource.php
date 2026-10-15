<?php

namespace App\Http\Resources\V1;

use App\Models\ReceiptVoucher;
use Illuminate\Http\Request;

/** Receipt and payment vouchers. */
class VoucherResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->resource instanceof ReceiptVoucher ? 'receipt' : 'payment',
            'number' => $this->number,
            'issued_at' => $this->date($this->issued_at),
            'party' => $this->party?->full_name ?? $this->party?->name ?? $this->party?->title ?? $this->party_name,
            'unit' => $this->unit ? ['id' => $this->unit->id, 'title' => $this->unit->title] : null,
            'amount' => $this->money($this->amount),
            'payment_method' => $this->enum($this->payment_method),
            'reason' => $this->reason,
        ];
    }
}
