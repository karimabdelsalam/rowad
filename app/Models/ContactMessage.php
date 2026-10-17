<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/** A message from the official site's contact form (central database). */
#[Guarded(['id'])]
class ContactMessage extends Model
{
    use CentralConnection;

    public const TOPICS = [
        'demo' => 'أريد عرضًا تجريبيًا',
        'pricing' => 'الأسعار والباقات',
        'migration' => 'نقل بياناتي من نظام آخر',
        'support' => 'دعم فني لمؤسستي',
        'partnership' => 'شراكة',
        'general' => 'استفسار عام',
    ];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function topicLabel(): string
    {
        return __(self::TOPICS[$this->topic] ?? self::TOPICS['general']);
    }
}
