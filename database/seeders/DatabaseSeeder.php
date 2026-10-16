<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Central database: subscription plans and the first platform administrator. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['basic', 'الأساسية', 'Basic', 199, 1990, 50, 3, 1024, 200, 100, ['owner_portal', 'landing_page', 'ai']],
            ['professional', 'الاحترافية', 'Professional', 449, 4490, 300, 10, 10240, 1000, 600, ['owner_portal', 'client_portal', 'zatca', 'campaigns', 'landing_page', 'ai', 'online_payments']],
            ['enterprise', 'المؤسسات', 'Enterprise', 999, 9990, null, null, 51200, 5000, 3000, ['owner_portal', 'client_portal', 'zatca', 'campaigns', 'api', 'custom_domain', 'landing_page', 'ai', 'online_payments', 'branches']],
        ] as $i => [$slug, $ar, $en, $monthly, $yearly, $units, $users, $storage, $sms, $ai, $features]) {
            Plan::updateOrCreate(['slug' => $slug], [
                'name_ar' => $ar, 'name_en' => $en, 'monthly_price' => $monthly, 'yearly_price' => $yearly,
                'max_units' => $units, 'max_users' => $users, 'max_storage_mb' => $storage, 'max_sms_per_month' => $sms, 'max_ai_messages_per_month' => $ai,
                'features' => $features, 'trial_days' => 14, 'sort_order' => $i,
            ]);
        }

        if ($email = config('rowad.platform_admin.email')) {
            User::firstOrCreate(['email' => $email], [
                'name' => 'Platform Admin',
                'password' => config('rowad.platform_admin.password') ?: str()->password(16),
                'type' => 'staff',
                'is_super_admin' => true,
            ]);
        }
    }
}
