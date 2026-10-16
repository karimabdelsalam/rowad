<?php

use App\Models\PrintTemplate;
use Illuminate\Database\Migrations\Migration;

/** Offices still on the default rent contract design get the electronic signature placeholders. */
return new class extends Migration
{
    public function up(): void
    {
        $template = PrintTemplate::where('key', 'rent_contract')->first();
        if ($template === null || str_contains((string) $template->content, '{owner_signature}')) {
            return;
        }
        $content = str_replace(
            ['<b>الطرف الأول (المؤجر) أو من يمثله</b><span>', '<b>الطرف الثاني (المستأجر) أو من يمثله</b><span>'],
            ['<b>الطرف الأول (المؤجر) أو من يمثله</b>{owner_signature}<span>', '<b>الطرف الثاني (المستأجر) أو من يمثله</b>{renter_signature}<span>'],
            (string) $template->content,
        );
        if ($content !== $template->content) {
            $template->forceFill(['content' => $content])->saveQuietly();
        }
    }

    public function down(): void {}
};
