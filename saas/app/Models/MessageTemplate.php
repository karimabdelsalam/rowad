<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

/** قالب رسالة لحدث إشعار (legacy email_templates) */
#[Guarded(['id'])]
class MessageTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
        ];
    }
}
