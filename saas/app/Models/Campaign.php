<?php

namespace App\Models;

use App\Enums\MessageChannel;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id'])]
class Campaign extends Model
{
    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'scheduled_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OutgoingMessage::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
