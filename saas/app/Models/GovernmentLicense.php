<?php

namespace App\Models;

use App\Enums\Calendar;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Guarded(['id'])]
class GovernmentLicense extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $attributes = ['calendar' => 'gregorian'];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'calendar' => Calendar::class,
            'notify_before_expiry' => 'boolean',
        ];
    }
}
