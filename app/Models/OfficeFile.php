<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** A file in the office's shared file cloud (legacy home/upload); visible to staff only. */
#[Guarded(['id'])]
class OfficeFile extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')->singleFile();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function file(): ?Media
    {
        return $this->getFirstMedia('file');
    }

    public function url(): ?string
    {
        $media = $this->file();

        return $media ? route('site.media', ['media' => $media]) : null;
    }
}
