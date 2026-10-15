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
    public function __invoke(Media $media, \Illuminate\Http\Request $request): BinaryFileResponse
    {
        $public = $media->collection_name === 'photos'
            && $media->model_type === (new Unit)->getMorphClass()
            && Unit::listed()->whereKey($media->model_id)->exists();

        abort_unless($public || auth()->check(), 404);
        $conversion = $request->query('size') === 'thumb' && $media->hasGeneratedConversion('thumb') ? 'thumb' : '';
        $path = $media->getPath($conversion);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => $conversion === '' ? $media->mime_type : (mime_content_type($path) ?: $media->mime_type),
            'Cache-Control' => $public ? 'public, max-age=86400' : 'private, max-age=0',
        ]);
    }
}
