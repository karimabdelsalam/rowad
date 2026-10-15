<?php

namespace App\Http\Controllers\Site;

use App\Enums\UserType;
use App\Models\Client;
use App\Models\Owner;
use App\Models\Unit;
use App\Models\UnitReport;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves files from the office's own (tenant-suffixed) storage.
 * Unit photos of listed units are public; other files are for staff, or for the
 * owner/client the file belongs to (their own record, their units' photos and reports).
 */
class MediaController
{
    public function __invoke(Media $media, Request $request): BinaryFileResponse
    {
        $public = $media->collection_name === 'photos'
            && $media->model_type === (new Unit)->getMorphClass()
            && Unit::listed()->whereKey($media->model_id)->exists();

        abort_unless($public || $this->allowed($request->user(), $media), 404);

        $conversion = $request->query('size') === 'thumb' && $media->hasGeneratedConversion('thumb') ? 'thumb' : '';
        $path = $media->getPath($conversion);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => $conversion === '' ? $media->mime_type : (mime_content_type($path) ?: $media->mime_type),
            'Cache-Control' => $public ? 'public, max-age=86400' : 'private, max-age=0',
        ]);
    }

    private function allowed(?User $user, Media $media): bool
    {
        if ($user === null || ! $user->is_active) {
            return false;
        }
        if ($user->type === UserType::Staff) {
            return true;
        }

        $model = $media->model;
        $owner = $user->type === UserType::Owner ? $user->owner : null;
        $client = $user->type === UserType::Client ? $user->client : null;

        return match (true) {
            $model instanceof Owner => $owner !== null && $owner->is($model),
            $model instanceof Client => $client !== null && $client->is($model),
            $model instanceof Unit => $media->collection_name === 'photos' && $this->ownsOrRents($model, $owner, $client),
            $model instanceof UnitReport => $owner !== null && $model->unit?->owners()->whereKey($owner->getKey())->exists(),
            default => false,
        };
    }

    private function ownsOrRents(Unit $unit, ?Owner $owner, ?Client $client): bool
    {
        if ($owner !== null) {
            return $unit->owners()->whereKey($owner->getKey())->exists();
        }

        return $client !== null && (
            $unit->rentContracts()->whereHas('clients', fn ($q) => $q->whereKey($client->getKey()))->exists()
            || $unit->saleContracts()->whereHas('clients', fn ($q) => $q->whereKey($client->getKey()))->exists());
    }
}
