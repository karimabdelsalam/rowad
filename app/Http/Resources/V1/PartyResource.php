<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;

/** Owners and clients. */
class PartyResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->full_name,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'id_type' => $this->enum($this->id_type),
            'id_number' => $this->id_number,
            'nationality' => $this->nationality,
            'address' => $this->address,
        ];
    }
}
