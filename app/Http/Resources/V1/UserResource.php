<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;

/** @mixin \App\Models\User */
class UserResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->enum($this->type),
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'locale' => $this->locale,
            'is_super_admin' => $this->is_super_admin,
            'permissions' => $this->when($this->type?->value === 'staff', fn () => $this->is_super_admin ? ['*'] : $this->getAllPermissions()->pluck('name')->values()),
            'office' => ['name' => \App\Support\Settings::get('site_name') ?? tenant('name')],
        ];
    }
}
