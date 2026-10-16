<?php

use App\Enums\NotificationEvent as E;
use App\Models\MessageTemplate;
use Illuminate\Database\Migrations\Migration;

/** Reminder templates of offices already running get the {pay_line} placeholder (empty until e-collection is on). */
return new class extends Migration
{
    public function up(): void
    {
        $events = [E::RentDueIn15Days, E::SaleInstallmentDueIn15Days, E::SaleInstallmentDueTomorrow, E::SaleInstallmentDueToday, E::SaleInstallmentOverdue];
        foreach (MessageTemplate::whereIn('event', array_map(fn ($e) => $e->value, $events))->get() as $template) {
            $changes = [];
            if (filled($template->sms_body) && ! str_contains($template->sms_body, '{pay_line}')) {
                $changes['sms_body'] = rtrim($template->sms_body).' {pay_line}';
            }
            if (filled($template->email_body) && ! str_contains($template->email_body, '{pay_line}')) {
                $changes['email_body'] = $template->email_body.'<p>{pay_line}</p>';
            }
            if ($changes) {
                $template->forceFill($changes)->save();
            }
        }
        (new \Database\Seeders\Tenant\RolesAndPermissionsSeeder)->run(); // online_payments.* permissions
    }

    public function down(): void {}
};
