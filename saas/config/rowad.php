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
        'company_partners' => $crud,
        'government_licenses' => $crud,
        'rent_contracts' => [...$crud, 'end', 'cancel', 'archive', 'print'],
        'sale_contracts' => [...$crud, 'archive', 'print'],
        'contract_terms' => $crud,
        'letters' => [...$crud, 'print'],
        'installments' => ['view_any', 'view', 'update', 'collect', 'partial_collect', 'reset', 'invoice'],
        'owner_transactions' => ['view_any', 'view', 'settle', 'reverse'],
        'receipt_vouchers' => [...$crud, 'print'],
        'payment_vouchers' => [...$crud, 'print'],
        'invoices' => ['view_any', 'view', 'create', 'print'],
        'bank_accounts' => [...$crud, 'set_default'],
        'reports' => ['income', 'expenses', 'profits', 'banking', 'taxes', 'corporate_taxes', 'units', 'unit', 'renter', 'rent'],
        'tasks' => $crud,
        'support_tickets' => ['view_any', 'view', 'reply', 'delete'],
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
            'installments.*', 'owner_transactions.*', 'receipt_vouchers.*', 'payment_vouchers.*', 'invoices.*',
            'bank_accounts.*', 'reports.*', 'units.view_any', 'units.view', 'owners.view_any', 'owners.view',
            'clients.view_any', 'clients.view', 'rent_contracts.view_any', 'rent_contracts.view',
            'sale_contracts.view_any', 'sale_contracts.view', 'tasks.view_any',
        ],
        'agent' => [
            'units.*', 'buildings.*', 'owners.view_any', 'owners.view', 'owners.create', 'owners.update',
            'clients.*', 'rent_contracts.view_any', 'rent_contracts.view', 'rent_contracts.create', 'rent_contracts.print',
            'sale_contracts.view_any', 'sale_contracts.view', 'sale_contracts.create', 'sale_contracts.print',
            'installments.view_any', 'installments.view', 'support_tickets.*', 'tasks.view_any', 'letters.*',
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

    // Password of the legacy MySQL database for rowad:import-legacy (prompted when empty).
    'legacy_db_password' => env('LEGACY_DB_PASSWORD'),

    'tenancy' => [
        // Subdomains are created under this host, e.g. office1.rowad.app
        'base_domain' => env('TENANT_BASE_DOMAIN', 'localhost'),
    ],
];
