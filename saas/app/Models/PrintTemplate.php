<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id'])]
class PrintTemplate extends Model
{
    public function versions(): HasMany
    {
        return $this->hasMany(PrintTemplateVersion::class);
    }

    /** Snapshot the current content before an edit (legacy sample/restore). */
    public function snapshot(?int $userId = null): PrintTemplateVersion
    {
        return $this->versions()->create([
            'header' => $this->getOriginal('header'),
            'content' => $this->getOriginal('content'),
            'footer' => $this->getOriginal('footer'),
            'created_by' => $userId,
        ]);
    }
}
