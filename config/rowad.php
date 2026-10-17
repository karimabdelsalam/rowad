<?php

/*
 * Application-level configuration for the Rowad property-management SaaS.
 * Permissions mirror the legacy per-module/per-action rights (manager module).
 */

$crud = ['view_any', 'view', 'create', 'update', 'delete'];

return [

    'permissions' => [
        'units' => [...$crud, 'activate', 'export'],
        'buildings' => $crud,
        'owners' => [...$crud, 'login_as', 'reset_credentials'],
        'clients' => [...$crud, 'login_as', 'reset_credentials'],
        'citizens' => $crud,
        'employees' => $crud,
        'employee_penalties' => $crud,
        'leave_requests' => [...$crud, 'approve'],
        'employee_advances' => $crud,
        'payroll' => ['view_any', 'view', 'create', 'update', 'approve', 'pay', 'delete', 'export'],
        'end_of_service' => [...$crud, 'pay'],
        'company_partners' => $crud,
        'government_licenses' => $crud,
        'rent_contracts' => [...$crud, 'end', 'cancel', 'archive', 'print'],
        'sale_contracts' => [...$crud, 'archive', 'print'],
        'contract_terms' => $crud,
        'letters' => [...$crud, 'print'],
        'installments' => ['view_any', 'view', 'update', 'collect', 'partial_collect', 'reset', 'invoice'],
        'owner_transactions' => ['view_any', 'view', 'settle', 'reverse'],
        'owner_payouts' => ['view_any', 'view', 'create', 'transfer', 'cancel'],
        'receipt_vouchers' => [...$crud, 'print'],
        'online_payments' => ['view_any', 'view'],
        'branches' => $crud,
        'payment_vouchers' => [...$crud, 'print'],
        'invoices' => ['view_any', 'view', 'create', 'print', 'credit_note'],
        'bank_accounts' => [...$crud, 'set_default'],
        'accounts' => $crud,
        'journal_entries' => $crud,
        'accounting' => ['trial_balance', 'ledger', 'statements', 'rebuild'],
        'reports' => ['income', 'expenses', 'profits', 'banking', 'taxes', 'corporate_taxes', 'units', 'unit', 'renter', 'rent'],
        'tasks' => $crud,
        'support_tickets' => ['view_any', 'view', 'reply', 'delete'],
        'maintenance_requests' => [...$crud, 'assign', 'complete', 'cancel'],
        'viewings' => $crud,
        'inbox_messages' => ['view_any', 'view', 'reply', 'delete'],
        'campaigns' => [...$crud, 'send'],
        'message_templates' => ['view_any', 'update'],
        'print_templates' => ['view_any', 'update', 'restore'],
        'custom_fields' => $crud,
        'lookups' => $crud,
        'pages' => $crud,
        'office_files' => ['view_any', 'create', 'update', 'delete'],
        'users' => [...$crud, 'manage_permissions'],
        'activity_log' => ['view_any'],
        'settings' => ['view', 'update'],
        'backups' => ['create', 'download'],
    ],

    // Default roles created for each new office. 'admin' receives every permission.
    'roles' => [
        'admin' => '*',
        'accountant' => [
            'payroll.*', 'employee_advances.*', 'end_of_service.*', 'leave_requests.view_any', 'leave_requests.view', 'employees.view_any', 'employees.view',
            'installments.*', 'online_payments.*', 'owner_transactions.*', 'owner_payouts.*', 'receipt_vouchers.*', 'payment_vouchers.*', 'invoices.*',
            'bank_accounts.*', 'reports.*', 'accounts.*', 'journal_entries.*', 'accounting.*', 'units.view_any', 'units.view', 'owners.view_any', 'owners.view',
            'clients.view_any', 'clients.view', 'rent_contracts.view_any', 'rent_contracts.view',
            'sale_contracts.view_any', 'sale_contracts.view', 'tasks.view_any',
            'maintenance_requests.view_any', 'maintenance_requests.view', 'maintenance_requests.complete',
        ],
        'agent' => [
            'units.*', 'buildings.*', 'owners.view_any', 'owners.view', 'owners.create', 'owners.update',
            'clients.*', 'rent_contracts.view_any', 'rent_contracts.view', 'rent_contracts.create', 'rent_contracts.print',
            'sale_contracts.view_any', 'sale_contracts.view', 'sale_contracts.create', 'sale_contracts.print',
            'installments.view_any', 'installments.view', 'support_tickets.*', 'tasks.view_any', 'letters.*',
            'maintenance_requests.*', 'viewings.*',
        ],
        'viewer' => ['*.view_any', '*.view'],
    ],

    'calendar' => [
        'hijri_locale' => 'ar_SA@calendar=islamic-umalqura',
    ],

    // First platform administrator, created by `php artisan db:seed` (DatabaseSeeder).
    'platform_admin' => [
        'email' => env('PLATFORM_ADMIN_EMAIL'),
        'password' => env('PLATFORM_ADMIN_PASSWORD'),
    ],

    // Log query count and time per request to storage/logs/perf.log (development only).
    'perf_log' => (bool) env('ROWAD_PERF_LOG', false),

    // Password of the legacy MySQL database for rowad:import-legacy (prompted when empty).
    'legacy_db_password' => env('LEGACY_DB_PASSWORD'),

    // AI assistant (Anthropic Messages API). Both the key and the model are chosen by the owner;
    // without them the assistant shows "not configured" instead of failing.
    'ai' => [
        // anthropic (Claude), openai, or gemini; each can also be set from the platform console
        'provider' => env('AI_PROVIDER', 'anthropic'),
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('AI_MODEL'),
        'base_url' => env('AI_BASE_URL', 'https://api.anthropic.com'),
        'openai_api_key' => env('OPENAI_API_KEY'),
        'openai_model' => env('OPENAI_MODEL'),
        'openai_base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com'), // any OpenAI-compatible endpoint
        'gemini_api_key' => env('GEMINI_API_KEY'),
        'gemini_model' => env('GEMINI_MODEL'),
        'gemini_base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'),
        'max_tokens' => (int) env('AI_MAX_TOKENS', 2048),
        'timeout' => (int) env('AI_TIMEOUT', 90),
        'max_tool_rounds' => 6,
    ],

    // cPanel hosting: office databases are created through cPanel's API (see App\Tenancy\CpanelDatabaseManager).
    'cpanel' => [
        // 'http' calls UAPI over HTTPS with an API token; 'cli' runs /usr/local/cpanel/bin/uapi locally
        // for hosts that disable API tokens (the CLI runs as the account user and needs no token).
        'driver' => env('CPANEL_UAPI_DRIVER', 'http'),
        'cli_binary' => env('CPANEL_UAPI_BINARY', '/usr/local/cpanel/bin/uapi'),
        'host' => env('CPANEL_HOST', 'localhost'),
        'port' => (int) env('CPANEL_PORT', 2083),
        'user' => env('CPANEL_USER'),
        'token' => env('CPANEL_API_TOKEN'),
        // localhost has no certificate for its own name; a real hostname should be verified
        'verify_ssl' => (bool) env('CPANEL_VERIFY_SSL', ! in_array(env('CPANEL_HOST', 'localhost'), ['localhost', '127.0.0.1'], true)),
    ],

    // Hosting without Supervisor (cPanel): the per-minute cron also works through the queue.
    'queue_via_scheduler' => env('QUEUE_WORKER') === 'scheduler',

    // Behind Cloudflare or another proxy: "*" so HTTPS and visitor IPs are read from the proxy headers.
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    // Contact details shown on the platform's public pages (aqarnova.com).
    'contact' => [
        'email' => env('PLATFORM_CONTACT_EMAIL'),
        'phone' => env('PLATFORM_CONTACT_PHONE'),
        'whatsapp' => env('PLATFORM_WHATSAPP'), // international digits, e.g. 9665XXXXXXXX
    ],

    // Rebuild the public demo office (demo.<base-domain>) every night at 04:00.
    'demo_reset_daily' => (bool) env('DEMO_RESET_DAILY', false),
    // Rebuild it after a visitor changed something and left (idle for App\Support\Demo::IDLE_MINUTES).
    'demo_auto_reset' => (bool) env('DEMO_AUTO_RESET', true),

    // Licensing. On aqarnova.com (mode "saas") the platform issues licenses and signs tokens with
    // LICENSE_SIGNING_KEY. A copy installed on a customer's server runs with mode "self_hosted":
    // it activates LICENSE_KEY against LICENSE_SERVER and verifies tokens with LICENSE_PUBLIC_KEY.
    'license' => [
        'mode' => env('LICENSE_MODE', 'saas'),
        'signing_key' => env('LICENSE_SIGNING_KEY'),
        'public_key' => env('LICENSE_PUBLIC_KEY'),
        'key' => env('LICENSE_KEY'),
        'server' => rtrim((string) env('LICENSE_SERVER', 'https://aqarnova.com'), '/'),
        'token_days' => 7,   // a token is good this long without contacting the server
        'grace_days' => 14,  // after that, keeps working this long if the server cannot be reached
    ],

    // Hosts without a Cron Jobs feature: an external pinger (cron-job.org etc.) hits
    // https://<domain>/system-cron/<token> every minute to run the scheduler.
    'cron_token' => env('CRON_TOKEN'),

    'tenancy' => [
        // Subdomains are created under this host, e.g. alrowad.aqarnova.com
        'base_domain' => env('TENANT_BASE_DOMAIN', 'localhost'),
    ],
];
