<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/** One installation (server) using a license. */
#[Guarded(['id'])]
class LicenseActivation extends Model
{
    use CentralConnection;

    protected function casts(): array
    {
        return ['activated_at' => 'datetime', 'last_seen_at' => 'datetime', 'deactivated_at' => 'datetime'];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
