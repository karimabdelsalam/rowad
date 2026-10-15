<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;

/** @mixin \App\Models\Unit */
class UnitResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'number' => $this->number,
            'building' => $this->whenLoaded('building', fn () => $this->building ? ['id' => $this->building->id, 'title' => $this->building->title] : null),
            'offer_type' => $this->enum($this->offer_type),
            'status' => $this->enum($this->status),
            'floor' => $this->floor,
            'rooms' => $this->rooms,
            'area' => $this->area,
            'is_furnished' => $this->is_furnished,
            'rent_amount' => $this->money($this->rent_amount),
            'rent_payment_cycle' => $this->enum($this->rent_payment_cycle),
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'owners' => $this->whenLoaded('owners', fn () => $this->owners->map(fn ($owner) => [
                'id' => $owner->id, 'name' => $owner->full_name, 'share_percent' => (float) $owner->pivot->share_percent,
            ])),
            'photo_url' => $this->coverPhotoUrl(),
        ];
    }
}
