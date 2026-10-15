<?php

namespace App\Http\Controllers\Site;

use App\Models\Unit;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves files from the office's own (tenant-suffixed) storage.
 * Unit photos of listed units are public; everything else needs a signed-in user.
 */
class MediaController
{
    public function __invoke(Media $media): BinaryFileResponse
    {
        $public = $media->collection_name === 'photos'
            && $media->model_type === (new Unit)->getMorphClass()
            && Unit::listed()->whereKey($media->model_id)->exists();

        abort_unless($public || auth()->check(), 404);
        abort_unless(is_file($media->getPath()), 404);

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => $public ? 'public, max-age=86400' : 'private, max-age=0',
        ]);
    }
}
