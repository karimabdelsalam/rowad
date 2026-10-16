<?php

namespace App\Services\Platform;

use App\Exceptions\BusinessRuleException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PlatformSettings;
use App\Support\Settings;
use Illuminate\Support\Str;
use Throwable;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Creates a subscribing office: tenant row, its own database (migrated and
 * seeded by the TenantCreated pipeline), its subdomain, a trial or paid
 * subscription and the first administrator.
 */
class OfficeProvisioner
{
    public const RESERVED_SUBDOMAINS = ['www', 'admin', 'platform', 'api', 'app', 'mail', 'webmail', 'smtp', 'ftp', 'ns1', 'ns2', 'static', 'cdn', 'assets', 'media',
        'help', 'support', 'docs', 'blog', 'status', 'billing', 'pay', 'login', 'account', 'secure', 'dev', 'staging', 'aqarnova'];

    /**
     * @param array{name:string, email?:?string, phone?:?string, subdomain:string, plan_id?:?int,
     *              admin_name:string, admin_email:string, admin_password:string, locale?:string} $data
     */
    public function provision(array $data): Tenant
    {
        $subdomain = $this->normaliseSubdomain($data['subdomain']);
        $domain = $subdomain.'.'.config('rowad.tenancy.base_domain');
        if (Domain::where('domain', $domain)->exists()) {
            throw new BusinessRuleException(__('النطاق :domain مستخدم من قبل', ['domain' => $domain]));
        }

        $plan = isset($data['plan_id']) ? Plan::findOrFail($data['plan_id']) : null;

        // A self-hosted copy may hold only as many offices as its license allows.
        if (\App\Services\Licensing\LicenseClient::selfHosted()
            && ($max = app(\App\Services\Licensing\LicenseClient::class)->limit('offices')) !== null && Tenant::count() >= $max) {
            throw new BusinessRuleException(__('وصلت إلى عدد المؤسسات المسموح في الترخيص (:max). تواصل مع عقار نوفا لزيادته.', ['max' => $max]));
        }

        // A known id lets a half-created office be removed if any step fails (e.g. the host's database quota is full).
        $id = (string) Str::uuid();
        try {
            $tenant = Tenant::create([
                'id' => $id,
                'name' => $data['name'],
                'email' => $data['email'] ?? $data['admin_email'],
                'phone' => $data['phone'] ?? null,
                'plan_id' => $plan?->id,
                'status' => Tenant::STATUS_TRIAL,
                'trial_ends_at' => now()->addDays(PlatformSettings::trialDays($plan)),
            ]);
            $tenant->domains()->create(['domain' => $domain]);

            if ($plan !== null) {
                Subscription::create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $plan->id,
                    'status' => 'trialing',
                    'amount' => 0,
                    'starts_at' => now(),
                    'ends_at' => $tenant->trial_ends_at,
                    'gateway' => 'manual',
                ]);
            }

            $tenant->run(function () use ($data) {
                $admin = User::create([
                    'type' => 'staff',
                    'name' => $data['admin_name'],
                    'email' => $data['admin_email'],
                    'password' => $data['admin_password'],
                    'locale' => $data['locale'] ?? 'ar',
                    'email_verified_at' => now(),
                ]);
                $admin->assignRole('admin');
                Settings::set('site_name', $data['name'], 'identity');
                Settings::set('locale', $data['locale'] ?? 'ar', 'localization');
            });
        } catch (Throwable $e) {
            $this->discardFailed($id);

            throw $e;
        }

        return $tenant;
    }

    /** Remove what a failed signup left behind: the office row, its domain, trial and database if one was created. */
    private function discardFailed(string $id): void
    {
        $tenant = Tenant::find($id);
        if ($tenant === null) {
            return;
        }
        try {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            $database = $tenant->database();
            if ($database->manager()->databaseExists($database->getName())) {
                $tenant->delete(); // runs DeleteDatabase
            } else {
                $tenant->domains()->delete();
                $tenant->subscriptions()->delete();
                $tenant->deleteQuietly();
            }
        } catch (Throwable $cleanup) {
            report($cleanup);
        }
    }

    public function normaliseSubdomain(string $subdomain): string
    {
        $subdomain = Str::of($subdomain)->lower()->trim()->replaceMatches('/[^a-z0-9-]/', '')->trim('-')->toString();

        $min = PlatformSettings::subdomainMinLength();
        if (strlen($subdomain) < $min || strlen($subdomain) > 40) {
            throw new BusinessRuleException(__('النطاق الفرعي يجب أن يكون من :min إلى 40 حرفًا إنجليزيًا أو رقمًا', ['min' => $min]));
        }
        if (in_array($subdomain, PlatformSettings::reservedSubdomains(), true)) {
            throw new BusinessRuleException(__('هذا النطاق محجوز'));
        }

        return $subdomain;
    }

    public function suspend(Tenant $tenant): void
    {
        $tenant->update(['status' => Tenant::STATUS_SUSPENDED]);
    }

    public function activate(Tenant $tenant): void
    {
        $tenant->forceFill(['status' => Tenant::STATUS_ACTIVE, 'cancelled_at' => null, 'purge_after' => null, 'cancellation' => null])->save();
    }
}
