<?php

namespace App\Support;

/** Location map embeds: Google Maps when the organisation set an API key (legacy gmap_key), OpenStreetMap otherwise. */
class Maps
{
    public static function embedUrl(float|string|null $lat, float|string|null $lng): ?string
    {
        if (! is_numeric($lat) || ! is_numeric($lng) || ((float) $lat === 0.0 && (float) $lng === 0.0)) {
            return null;
        }
        [$lat, $lng] = [(float) $lat, (float) $lng];

        if (filled($key = Settings::get('google_maps_key'))) {
            return 'https://www.google.com/maps/embed/v1/place?'.http_build_query([
                'key' => $key, 'q' => "{$lat},{$lng}", 'zoom' => 15, 'language' => app()->getLocale(),
            ]);
        }

        $d = 0.006;

        return 'https://www.openstreetmap.org/export/embed.html?'.http_build_query([
            'bbox' => implode(',', [$lng - $d, $lat - $d / 2, $lng + $d, $lat + $d / 2]), 'layer' => 'mapnik', 'marker' => "{$lat},{$lng}",
        ]);
    }

    /** Link that opens the place in the visitor's maps app. */
    public static function directionsUrl(float|string $lat, float|string $lng): string
    {
        return 'https://www.google.com/maps/search/?api=1&query='.((float) $lat).','.((float) $lng);
    }
}
