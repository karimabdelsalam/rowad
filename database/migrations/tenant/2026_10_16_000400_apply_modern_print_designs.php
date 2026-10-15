<?php

use App\Models\PrintTemplate;
use Database\Seeders\Tenant\TemplateSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Switch existing offices to the modern document designs. The previous content is kept as a version
 * (restorable from the template screen); templates imported from the legacy system are not touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (TemplateSeeder::printTemplates() as $key => $design) {
            $template = PrintTemplate::where('key', $key)->where('legacy_syntax', false)->first();
            if ($template === null) {
                PrintTemplate::firstOrCreate(['key' => $key], $design);

                continue;
            }
            $template->snapshot();
            $template->update(['header' => $design['header'], 'content' => $design['content'], 'footer' => $design['footer']]);
        }
    }

    public function down(): void
    {
        // versions keep the previous designs; restore from the template screen if needed
    }
};
