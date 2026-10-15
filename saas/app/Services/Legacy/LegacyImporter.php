<?php

namespace App\Services\Legacy;

use App\Enums\InstallmentType;
use App\Enums\NotificationEvent;
use App\Enums\PaymentCycle;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Owner;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\RentContract;
use App\Models\SaleContract;
use App\Models\Unit;
use App\Support\Settings;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * Copies an office's legacy MySQL database (Phantom Property Manager schema)
 * into its new tenant database. Legacy ids are preserved so every reference
 * (unit ↔ contract ↔ installment ↔ voucher ↔ ledger) stays intact.
 * Run inside tenancy: $tenant->run(fn () => app(LegacyImporter::class)->import($legacyConnection)).
 */
class LegacyImporter
{
    private Connection $legacy;

    /** @var array<string, int> table => imported rows */
    private array $report = [];

    /** @var list<string> */
    private array $warnings = [];

    /** @var array<string, int> legacy transaction type code => new id */
    private array $transactionTypes = [];

    /** Legacy `media` upload folder; attachments are skipped when null. */
    private ?string $mediaPath = null;

    /** @return array{counts: array<string, int>, warnings: list<string>} */
    public function import(Connection $legacy, ?string $mediaPath = null): array
    {
        $this->legacy = $legacy;
        $this->mediaPath = $mediaPath !== null ? rtrim($mediaPath, '/') : null;
        DB::connection()->disableQueryLog();

        $steps = ['settings', 'lookups', 'users', 'owners', 'clients', 'people', 'buildings', 'units', 'contractTerms',
            'rentContracts', 'saleContracts', 'bankAccounts', 'vouchers', 'invoices', 'installments', 'ownerTransactions',
            'bankTransactions', 'operations', 'templates', 'currentContracts', 'attachments'];

        foreach ($steps as $step) {
            DB::transaction(fn () => $this->{$step}());
        }

        return ['counts' => $this->report, 'warnings' => $this->warnings];
    }

    // ---------------------------------------------------------------- settings & lookups

    private function settings(): void
    {
        $map = [
            'sitename' => 'site_name', 'en_sitename' => 'site_name_en', 'address1' => 'address1', 'address2' => 'address2',
            'phone' => 'phone', 'mobile' => 'mobile', 'fax' => 'fax', 'email' => 'email', 'currency' => 'currency',
            'phone_code' => 'phone_code', 'timezone' => 'timezone', 'vat' => 'vat_percent', 'billing_info' => 'billing_info',
            'support_ticket_prefix' => 'support_ticket_prefix', 'gmap_key' => 'google_maps_key',
            'comm_tax_status' => 'commercial_tax_enabled', 'comm_tax_value' => 'commercial_tax_percent',
            'habit_tax_status' => 'residential_tax_enabled', 'habit_tax_value' => 'residential_tax_percent',
            'income_tax_status' => 'income_tax_enabled', 'income_tax_value' => 'income_tax_percent',
            'owner_tax_status' => 'owner_tax_enabled', 'owner_tax_value' => 'owner_tax_percent',
            'sms_provider' => 'sms_provider', 'sms_user' => 'sms_username', 'sms_pass' => 'sms_password', 'sms_sender' => 'sms_sender',
            'smslogin' => 'sms_login_staff', 'smslogin_clients' => 'sms_login_clients',
            'mail_sendtype' => 'mail_driver', 'smtp_server' => 'smtp_host', 'smtp_port' => 'smtp_port',
            'smtp_username' => 'smtp_username', 'smtp_password' => 'smtp_password',
        ];
        $legacy = $this->rows('setting')->pluck('value', 'varname');

        foreach ($map as $old => $new) {
            if (! $legacy->has($old)) {
                continue;
            }
            $value = $legacy[$old];
            $value = match (true) {
                str_ends_with($new, '_enabled'), str_starts_with($new, 'sms_login') => (string) $value === '1',
                str_ends_with($new, '_percent') => (float) $value,
                $new === 'mail_driver' => $value === 'smtp' ? 'smtp' : 'platform',
                default => $value,
            };
            Settings::set($new, $value);
        }
        foreach (['system_calendar', 'salary_calendar'] as $key) {
            if ($legacy->has($key)) {
                Settings::set($key, $this->calendar($legacy[$key]));
            }
        }
        $this->count('setting', $legacy->count());
    }

    private function lookups(): void
    {
        // The office's own lists replace the defaults seeded for new offices (ids must match the legacy references).
        $this->replaceDefaults('city', 'cities');
        $this->copy('city', 'cities', fn ($r) => ['id' => $r->id, 'name' => $r->name, 'country_code' => 'SA']);
        $this->replaceDefaults('district', 'districts');
        $this->copy('district', 'districts', fn ($r) => ['id' => $r->id, 'name' => $r->name, 'city_id' => null]);
        foreach ([
            'building_cats' => 'property_categories', 'buyer_cats' => 'client_categories', 'deed_types' => 'deed_types',
            'meter_types' => 'meter_types', 'banks' => 'banks', 'outgoings_types' => 'expense_types', 'gov_orgs' => 'government_organizations',
            'trade_orgs' => 'trade_organizations',
        ] as $old => $new) {
            $this->replaceDefaults($old, $new);
            $this->copy($old, $new, fn ($r) => ['id' => $r->id, 'title' => $r->title]);
        }
        $this->copy('building_reports_cats', 'unit_report_categories', fn ($r) => ['id' => $r->id, 'title' => $r->name ?? $r->title]);
        $this->copy('building_borders', 'border_types', fn ($r) => ['id' => $r->id, 'title' => $r->title, 'attributes' => $this->json($r->attrs ?? null)]);

        // Legacy transaction types are keyed by code (payment, tax, commission, mancost, ...).
        foreach ($this->rows('transaction_types') as $row) {
            $code = (string) $row->id;
            $existing = DB::table('transaction_types')->where('code', $code)->value('id');
            $this->transactionTypes[$code] = $existing ?? DB::table('transaction_types')->insertGetId($this->stamp([
                'code' => $code, 'title' => $row->type ?? $row->title ?? $code, 'is_income' => (bool) ($row->income ?? false), 'is_system' => false,
            ]));
        }
        foreach (DB::table('transaction_types')->pluck('id', 'code') as $code => $id) {
            $this->transactionTypes[$code] ??= $id;
        }
    }

    // ---------------------------------------------------------------- people

    private function users(): void
    {
        $types = [1 => 'staff', 2 => 'owner', 3 => 'client'];
        $this->copy('user', 'users', fn ($r) => [
            'id' => $r->id,
            'type' => $types[(int) ($r->groupid ?? 1)] ?? 'staff',
            'name' => $r->name ?: ($r->username ?? 'user'.$r->id),
            'username' => $r->username ?: null,
            'email' => filter_var($r->email ?? null, FILTER_VALIDATE_EMAIL) ? strtolower($r->email) : null,
            'mobile' => $r->mobile ?? null,
            'national_id' => $r->idnum ?? null,
            'password' => Hash::make(Str::random(32)),
            'legacy_password' => $r->password ?? null,
            'locale' => in_array($r->lang ?? null, ['ar', 'en'], true) ? $r->lang : 'ar',
            'is_active' => (int) ($r->active ?? 1) === 1,
            'is_super_admin' => (bool) ($r->super ?? false),
        ], unique: ['email', 'username']);

        foreach (DB::table('users')->where('type', 'staff')->pluck('id') as $id) {
            DB::table('model_has_roles')->insertOrIgnore(['role_id' => DB::table('roles')->where('name', 'admin')->value('id'), 'model_type' => 'user', 'model_id' => $id]);
        }
    }

    private function owners(): void
    {
        $this->copy('owner', 'owners', fn ($r) => $this->person($r) + ['id' => $r->id, 'user_id' => $this->userId($r->userid ?? null)]);
    }

    private function clients(): void
    {
        $this->copy('buyer', 'clients', fn ($r) => $this->person($r) + [
            'id' => $r->id,
            'user_id' => $this->userId($r->userid ?? null),
            'kind' => in_array($r->type ?? '', ['company', 'corp', '2'], true) ? 'company' : 'individual',
            'company_name' => $r->company ?? null,
            'company_registration_number' => $r->com_idnumber ?? null,
            'company_mobile' => $r->com_mobile ?? null,
            'billing_info' => $this->json($r->billing_info ?? null),
            'calendar' => $this->calendar($r->calendar ?? 1),
        ]);
    }

    private function people(): void
    {
        $this->copy('citizens', 'citizens', fn ($r) => [
            'id' => $r->id, 'name' => $r->title, 'id_type' => $this->idType($r->idtype ?? null), 'id_number' => $r->idnumber ?? null,
            'id_source' => $r->idsource ?? null, 'id_issue_date' => $this->date($r->iddate ?? null), 'nationality' => $r->nationality ?? null,
            'calendar' => $this->calendar($r->calendar ?? 1),
        ]);
        $this->copy('employees', 'employees', fn ($r) => $this->person($r, idNumber: 'idnum') + [
            'id' => $r->id, 'id_expiry_date' => $this->date($r->idexpire ?? null), 'mobile2' => $r->mobile2 ?? null,
            'address' => $r->address1 ?? null, 'address2' => $r->address2 ?? null, 'education' => $r->education ?? null,
            'marital_status' => $r->maritalstatus ?? null, 'salary' => (float) ($r->salary ?? 0), 'rent_commission' => (float) ($r->rentcomm ?? 0),
            'sale_commission' => (float) ($r->sellcomm ?? 0), 'start_date' => $this->date($r->startdate ?? null), 'calendar' => $this->calendar($r->calendar ?? 1),
        ]);
        $this->copy('company_owners', 'company_partners', fn ($r) => $this->person($r) + [
            'id' => $r->id, 'share_percent' => (float) ($r->share ?? 0), 'city' => $r->city ?? null, 'postal_code' => $r->postal ?? $r->zipcode ?? null,
        ]);
        $this->copy('gov_licenses', 'government_licenses', fn ($r) => [
            'id' => $r->id, 'title' => $r->title, 'number' => $r->number ?? null, 'expiry_date' => $this->date($r->expiredate ?? null),
            'calendar' => $this->calendar($r->calendar ?? 1), 'notify_before_expiry' => (bool) ($r->notify ?? true),
        ]);
    }

    // ---------------------------------------------------------------- properties

    private function buildings(): void
    {
        $this->copy('building_locs', 'buildings', fn ($r) => [
            'id' => $r->id, 'title' => $r->title ?? $r->location ?? ('#'.$r->id), 'property_category_id' => $this->ref('property_categories', $r->buildcat ?? null),
            'number' => $r->buildno ?? null, 'city_id' => $this->ref('cities', $r->citytid ?? null), 'street' => $r->street ?? null, 'zone' => $r->zone ?? null,
            'plan_number' => $r->planno ?? null, 'plot_number' => $r->platno ?? null, 'area' => $this->num($r->size ?? null), 'floors' => $this->int($r->floors ?? null),
            'address' => $r->location ?? null, 'address_details' => $r->loc_details ?? null,
        ] + $this->gps($r->gps_location ?? null));
    }

    private function units(): void
    {
        $this->copy('building', 'units', function ($r) {
            $sale = in_array($r->type ?? 'rent', ['sell', 'sale'], true);

            return [
                'id' => $r->id, 'building_id' => $this->ref('buildings', $r->locid ?? null), 'title' => $r->title ?: ('#'.$r->id),
                'offer_type' => $sale ? 'sale' : 'rent',
                'status' => (int) ($r->available ?? 1) === 1 ? 'available' : ($sale ? 'sold' : 'rented'),
                'is_active' => (int) ($r->active ?? 1) === 1,
                'property_category_id' => $this->ref('property_categories', $r->buildcat ?? null),
                'client_category_id' => $this->ref('client_categories', $r->buyercat ?? null),
                'number' => $r->buildno ?? null, 'floor' => $r->floor ?? null, 'rooms' => $this->int($r->rooms ?? null),
                'area' => $this->num($r->size ?? null), 'is_furnished' => (bool) ($r->furnished ?? false), 'max_occupants' => $this->int($r->max ?? null),
                'deed_number' => $r->deed ?? null, 'plan_number' => $r->planno ?? null, 'plot_number' => $r->platno ?? null, 'plots' => $r->plots ?? null,
                'zone' => $r->zone ?? null, 'street' => $r->street ?? null, 'city_id' => $this->ref('cities', $r->citytid ?? null),
                'district_id' => $this->ref('districts', $r->districtid ?? null), 'address' => $r->location ?? null,
                'address_details' => $r->loc_details ?? null, 'details' => $r->details ?? null,
                'rent_amount' => $this->num($r->rentvalue ?? null), 'daily_rent' => $this->num($r->rent_day ?? null),
                'monthly_rent' => $this->num($r->rent_month ?? null), 'rent_period' => $this->periodUnit($r->period ?? null),
                'rent_payment_cycle' => $this->cycle($r->payment ?? null), 'rent_commission' => $this->num($r->rentcomm ?? null),
                'sale_commission' => $this->num($r->salecomm ?? null), 'vat_applicable' => (bool) ($r->tax ?? false),
                'management_fee' => $this->num($r->mcost ?? null), 'management_fee_cycle' => $this->cycle($r->mcost_cycle ?? null),
                'management_fee_start_date' => $this->date($r->mcost_start_date ?? null), 'management_fee_next_date' => $this->date($r->mcost_next_date ?? null),
                'calendar' => $this->calendar($r->calendar ?? 1),
            ] + $this->gps($r->gps_location ?? null);
        });

        foreach ($this->rows('building') as $r) {
            $owners = $this->json($r->owner ?? null) ?? [];
            foreach (array_values((array) ($owners['id'] ?? [])) as $i => $ownerId) {
                if (! DB::table('owners')->where('id', $ownerId)->exists()) {
                    $this->warn("unit {$r->id}: owner {$ownerId} not found");

                    continue;
                }
                DB::table('owner_unit')->insertOrIgnore($this->stamp([
                    'unit_id' => $r->id, 'owner_id' => $ownerId,
                    'share_percent' => (float) (array_values((array) ($owners['share'] ?? []))[$i] ?? 100),
                    'expenses_percent' => (float) (array_values((array) ($owners['outgoings'] ?? []))[$i] ?? 0),
                ]));
            }
            foreach ((array) ($this->json($r->meters ?? null) ?? []) as $typeId => $number) {
                if (is_array($number)) {
                    [$typeId, $number] = [$number['type'] ?? $typeId, $number['number'] ?? null];
                }
                if (filled($number) && DB::table('meter_types')->where('id', $typeId)->exists()) {
                    DB::table('unit_meters')->insert($this->stamp(['unit_id' => $r->id, 'meter_type_id' => $typeId, 'number' => (string) $number]));
                }
            }
        }

        $this->copy('custom_fields_cats', 'custom_field_groups', fn ($r) => ['id' => $r->id, 'title' => $r->title, 'property_category_id' => $this->ref('property_categories', $r->build_catid ?? null)]);
        $this->copy('custom_fields', 'custom_fields', fn ($r) => ['id' => $r->id, 'custom_field_group_id' => $r->catid, 'type' => $r->type === 'checkbox' ? 'checkbox' : 'text',
            'title' => $r->title, 'description' => $r->description ?? null, 'suffix' => $r->suffix ?? null, 'icon' => $r->icon ?? null, 'is_required' => (bool) ($r->required ?? false)]);
        $this->copy('custom_fields_data', 'custom_field_values', fn ($r) => ['custom_field_id' => $r->field_id, 'unit_id' => $r->build_id, 'value' => $r->data]);
        $this->copy('building_reports', 'unit_reports', fn ($r) => ['id' => $r->id, 'unit_id' => $r->buildid,
            'unit_report_category_id' => $this->ref('unit_report_categories', $r->catid ?? null), 'title' => $r->title, 'issued_at' => $this->date($r->issued_date ?? null)]);
    }

    // ---------------------------------------------------------------- contracts

    private function contractTerms(): void
    {
        $this->copy('contract_rights', 'contract_terms', fn ($r) => ['id' => $r->id, 'title' => $r->title, 'content' => $r->content ?? '', 'cycle' => $r->cycle ?? null]);
    }

    private function rentContracts(): void
    {
        $this->copy('rent_contracts', 'rent_contracts', fn ($r) => [
            'id' => $r->id, 'number' => 'RC-'.$r->id, 'unit_id' => $r->buildid, 'calendar' => $this->calendar($r->calendar ?? 1),
            'contract_date' => $this->date($r->postdate ?? null) ?? $this->date($r->startdate), 'start_date' => $this->date($r->startdate),
            'end_date' => $this->date($r->enddate), 'period_unit' => $this->periodUnit($r->period ?? 'year') ?? 'year',
            'period_count' => max(1, (int) ($r->periodnum ?? 1)), 'payment_cycle' => $this->cycle($r->payment ?? 'month') ?? 'month',
            'rent_amount' => (float) ($r->rentvalue ?? 0), 'yearly_rent' => $this->num($r->yearlyrent ?? null), 'commission' => (float) ($r->commission ?? 0),
            'commission_cycle' => $this->cycle($r->comm_cycle ?? null), 'vat_applicable' => (float) ($r->tax ?? 0) > 0,
            'vat_percent' => (float) ($r->tax_percent ?? 0), 'vat_amount' => (float) ($r->tax ?? 0), 'usage' => $this->usage($r->usein ?? null),
            'meter_readings' => $this->json($r->meters ?? null), 'extra_terms' => $r->extrarights ?? null, 'notes' => $r->notes ?? null,
            'next_payment_date' => $this->date($r->nextpaymentdate ?? null), 'next_payment_amount' => $this->num($r->nextpaymentmoney ?? null),
            'status' => (int) ($r->ended ?? 0) === 1 ? 'ended' : 'active', 'archived_at' => (int) ($r->archive ?? 0) === 1 ? now() : null,
        ], skipWhen: fn ($r) => ! DB::table('units')->where('id', $r->buildid)->exists());

        $this->contractLinks('rent_contracts', 'rent_contract');
    }

    private function saleContracts(): void
    {
        $this->copy('sell_contracts', 'sale_contracts', function ($r) {
            $type = in_array($r->paytype ?? 'once', ['once', 'parts', 'installment'], true) ? $r->paytype : 'once';

            return [
                'id' => $r->id, 'number' => 'SC-'.$r->id, 'unit_id' => $r->buildid, 'calendar' => $this->calendar($r->calendar ?? 1),
                'contract_date' => $this->date($r->postdate ?? null) ?? now()->toDateString(), 'total_amount' => (float) ($r->total ?? 0),
                'payment_type' => $type, 'payment_plan' => json_encode($this->salePlan($type, $r), JSON_UNESCAPED_UNICODE),
                'extra_terms' => $r->extrarights ?? null, 'status' => 'active', 'archived_at' => (int) ($r->archive ?? 0) === 1 ? now() : null,
            ];
        }, skipWhen: fn ($r) => ! DB::table('units')->where('id', $r->buildid)->exists());

        $this->contractLinks('sell_contracts', 'sale_contract');
    }

    /** Legacy JSON {id[], share[]} parties and rightsid clause lists. */
    private function contractLinks(string $legacyTable, string $morph): void
    {
        $table = $morph === 'rent_contract' ? 'rent_contracts' : 'sale_contracts';
        foreach ($this->rows($legacyTable) as $r) {
            if (! DB::table($table)->where('id', $r->id)->exists()) {
                continue;
            }
            $buyers = $this->json($r->buyer ?? null) ?? [];
            $ids = array_values((array) ($buyers['id'] ?? []));
            foreach ($ids as $i => $clientId) {
                if (DB::table('clients')->where('id', $clientId)->exists()) {
                    DB::table('contract_clients')->insertOrIgnore(['contract_type' => $morph, 'contract_id' => $r->id, 'client_id' => $clientId,
                        'share_percent' => (float) (array_values((array) ($buyers['share'] ?? []))[$i] ?? round(100 / max(count($ids), 1), 2))]);
                } else {
                    $this->warn("{$morph} {$r->id}: client {$clientId} not found");
                }
            }
            $terms = is_string($r->rightsid ?? null) ? ($this->json($r->rightsid) ?? explode(',', $r->rightsid)) : [];
            foreach (array_filter((array) $terms) as $termId) {
                if (DB::table('contract_terms')->where('id', $termId)->exists()) {
                    DB::table('contract_term_selections')->insertOrIgnore(['contract_type' => $morph, 'contract_id' => $r->id, 'contract_term_id' => $termId]);
                }
            }
        }
    }

    // ---------------------------------------------------------------- finance

    private function bankAccounts(): void
    {
        $this->copy('bank_accounts', 'bank_accounts', fn ($r) => ['id' => $r->id, 'bank_id' => $this->ref('banks', $r->bankid ?? null),
            'holder_name' => $r->fullname ?? ('#'.$r->id), 'account_number' => $r->accno ?? null, 'iban' => $r->iban ?? null,
            'branch_number' => $r->branchno ?? null, 'opening_balance' => (float) ($r->balance ?? 0), 'balance' => (float) ($r->balance ?? 0),
            'is_default' => (bool) ($r->isdefault ?? $r->default ?? false)]);
    }

    private function vouchers(): void
    {
        foreach (['statement_in' => 'receipt_vouchers', 'statement_out' => 'payment_vouchers'] as $old => $new) {
            $this->copy($old, $new, function ($r) use ($new) {
                [$partyType, $partyId] = $this->party($r->from_type ?? null, $r->from_id ?? null);
                $cheque = $this->json($r->cheque ?? null) ?? [];
                $bank = $this->json($r->bank ?? null) ?? [];
                $method = match ($r->pay_method ?? 'cash') { 'cheque' => 'cheque', 'bank', 'transfer' => 'transfer', 'card', 'pos' => 'card', default => 'cash' };

                return [
                    'id' => $r->id, 'number' => ($new === 'receipt_vouchers' ? 'RV-' : 'PV-').$r->id, 'calendar' => $this->calendar($r->calendar ?? 1),
                    'issued_at' => $this->date($r->issueddate ?? null) ?? now()->toDateString(), 'party_type' => $partyType, 'party_id' => $partyId,
                    'party_name' => $partyType === null ? (is_string($r->from ?? null) ? $r->from : null) : null,
                    'unit_id' => $this->ref('units', $r->buildid ?? null), 'amount' => (float) ($r->amount ?? 0),
                    'payment_method' => $method, 'bank_name' => $bank['name'] ?? $cheque['bank'] ?? null,
                    'cheque_number' => $cheque['number'] ?? null, 'cheque_date' => $this->date($cheque['issuedate'] ?? null),
                    'bank_account_id' => $this->ref('bank_accounts', $r->accid ?? null),
                    $new === 'receipt_vouchers' ? 'transaction_type_id' : 'expense_type_id' => $new === 'receipt_vouchers' ? null : $this->ref('expense_types', $r->typeid ?? null),
                    'reason' => isset($r->reason) ? Str::limit((string) $r->reason, 250, '') : null, 'notes' => $r->notes ?? null,
                ];
            });
        }
    }

    private function invoices(): void
    {
        $this->copy('invoices', 'invoices', function ($r) {
            $items = $this->json($r->items ?? null) ?? [];

            return ['id' => $r->id, 'number' => 'INV-'.$r->id, 'type' => (float) ($r->totaltaxs ?? 0) > 0 ? 'standard' : 'simplified',
                'client_id' => $this->ref('clients', $r->buyerid ?? null), 'customer_name' => $r->name ?? null, 'calendar' => $this->calendar($r->calendar ?? 1),
                'issue_date' => $this->date($r->created_date ?? null) ?? now()->toDateString(), 'items' => json_encode(array_values((array) $items), JSON_UNESCAPED_UNICODE),
                'subtotal' => round((float) ($r->total ?? 0) - (float) ($r->totaltaxs ?? 0), 2), 'vat_total' => (float) ($r->totaltaxs ?? 0),
                'total' => (float) ($r->total ?? 0), 'paid_amount' => (float) ($r->paid ?? 0), 'notes' => $r->notes ?? null];
        });
    }

    private function installments(): void
    {
        $this->copy('payments', 'installments', function ($r) {
            $morph = ($r->module ?? 'rent') === 'rent' ? 'rent_contract' : 'sale_contract';
            $paid = (int) ($r->gone ?? 0) === 1;

            return [
                'id' => $r->id, 'unit_id' => $r->buildid, 'contract_type' => $morph, 'contract_id' => $r->contractid,
                'type' => $this->installmentType($r->type ?? null), 'calendar' => $this->calendar($r->calendar ?? 1),
                'due_date' => $this->date($r->paydate), 'amount' => (float) $r->amount, 'seller_commission' => (float) ($r->commission ?? 0),
                'buyer_commission' => (float) ($r->commission2 ?? 0), 'commission_type' => ($r->comm_type ?? 'money') === 'percent' ? 'percent' : 'money',
                'vat_amount' => (float) ($r->tax ?? 0), 'vat_percent' => (float) ($r->tax_percent ?? 0), 'paid_amount' => (float) ($r->paidamount ?? 0),
                'is_collected' => $paid, 'collected_at' => $paid ? ($this->date($r->gone_date ?? null) ?? $this->date($r->paydate)) : null,
                'reminder_stage' => match ((int) ($r->alert ?? 0)) { 15 => 15, 1 => 1, -1 => 2, default => 0 },
                'receipt_voucher_id' => $this->ref('receipt_vouchers', $r->statementid ?? null),
                'payment_voucher_id' => $this->ref('payment_vouchers', $r->statoutid ?? null),
                'invoice_id' => $this->ref('invoices', $r->invoiceid ?? null), 'reason' => $r->reason ?? null,
            ];
        }, skipWhen: function ($r) {
            $table = ($r->module ?? 'rent') === 'rent' ? 'rent_contracts' : 'sale_contracts';

            return ! DB::table($table)->where('id', $r->contractid)->exists() || ! DB::table('units')->where('id', $r->buildid)->exists();
        });
    }

    private function ownerTransactions(): void
    {
        $this->copy('transactions', 'owner_transactions', function ($r) {
            $settled = (int) ($r->gone ?? 0) === 1;
            $morph = match ($r->module ?? null) { 'rent' => 'rent_contract', 'sell' => 'sale_contract', default => null };

            return [
                'id' => $r->id, 'owner_id' => $this->ref('owners', $r->ownerid ?? null), 'unit_id' => $this->ref('units', $r->buildid ?? null),
                'transaction_type_id' => $this->transactionType((string) $r->typeid), 'installment_id' => $this->ref('installments', $r->paymentid ?? null),
                'contract_type' => $morph && ! empty($r->contractid) ? $morph : null, 'contract_id' => $morph && ! empty($r->contractid) ? $r->contractid : null,
                'source' => $r->module ?? null, 'direction' => ($r->direction ?? 'credit') === 'debit' ? 'debit' : 'credit',
                'amount' => (float) $r->amount, 'vat_amount' => (float) ($r->tax ?? 0), 'due_date' => $this->date($r->paydate) ?? now()->toDateString(),
                'is_settled' => $settled, 'settled_at' => $settled ? ($this->date($r->gone_date ?? null) ?? $this->date($r->paydate)) : null,
                'receipt_voucher_id' => $this->ref('receipt_vouchers', $r->statinid ?? null),
                'payment_voucher_id' => $this->ref('payment_vouchers', $r->statoutid ?? null), 'parent_id' => null,
            ];
        });
        foreach ($this->rows('transactions')->filter(fn ($r) => ! empty($r->parent)) as $r) {
            DB::table('owner_transactions')->where('id', $r->id)->update(['parent_id' => $this->ref('owner_transactions', $r->parent)]);
        }
    }

    private function bankTransactions(): void
    {
        $this->copy('bankacc_transactions', 'bank_transactions', fn ($r) => [
            'id' => $r->id, 'bank_account_id' => $r->bankid, 'type' => ($r->type ?? 'credit') === 'credit' ? 'deposit' : 'withdrawal',
            'amount' => (float) $r->amount,
            'voucher_type' => ($r->type ?? 'credit') === 'credit' ? 'receipt_voucher' : 'payment_voucher', 'voucher_id' => $r->statementid ?? null,
            'occurred_at' => $r->createdtime ?? now(),
        ], skipWhen: fn ($r) => ! DB::table('bank_accounts')->where('id', $r->bankid)->exists());
    }

    // ---------------------------------------------------------------- operations

    private function operations(): void
    {
        $this->copy('task', 'tasks', fn ($r) => ['id' => $r->id, 'created_by' => $this->userId($r->managerid ?? null),
            'assigned_to' => $this->userId($r->tomanagerid ?? null), 'content' => $r->content ?? '', 'completed_at' => ! empty($r->done) ? now() : null,
            'created_at' => $this->timestamp($r->timepost ?? null)]);
        $this->copy('notes', 'notes', fn ($r) => ['id' => $r->id, 'user_id' => $r->userid, 'content' => $r->content ?? '', 'created_at' => $this->timestamp($r->timepost ?? null)],
            skipWhen: fn ($r) => ! DB::table('users')->where('id', $r->userid)->exists());
        $this->copy('support', 'support_tickets', fn ($r) => ['id' => $r->id, 'code' => $r->code ?: 'TCK-'.$r->id,
            'type' => in_array($r->type ?? '', ['request', 'enquiry', 'problem', 'suggestion'], true) ? $r->type : 'request',
            'owner_id' => $this->ref('owners', $r->ownerid ?? null), 'client_id' => $this->ref('clients', $r->clientid ?? null),
            'unit_id' => $this->ref('units', $r->buildid ?? null), 'subject' => $r->subject ?? '', 'message' => $r->message ?? '',
            'status' => (int) ($r->read_state ?? 0) === 1 ? 'answered' : 'open', 'read_at' => (int) ($r->read_state ?? 0) === 1 ? now() : null]);
        $this->copy('letters', 'letters', fn ($r) => ['id' => $r->id, 'type' => (string) ($r->type ?? 'general'), 'unit_id' => $this->ref('units', $r->buildid ?? null),
            'client_id' => $this->ref('clients', $r->renterid ?? null), 'calendar' => $this->calendar($r->calendar ?? 1),
            'letter_date' => $this->date($r->postdate ?? null) ?? now()->toDateString(), 'is_official' => (bool) ($r->official ?? true),
            'rent_raise_amount' => $this->num($r->rentraise ?? null),
            'data' => json_encode(['review' => $r->review ?? null, 'cancel' => $r->cancel ?? null], JSON_UNESCAPED_UNICODE)]);
        $this->copy('punish', 'employee_penalties', fn ($r) => ['id' => $r->id, 'employee_id' => $r->employeeid, 'amount' => (float) $r->amount,
            'reason' => $r->reason ?? null, 'reason_text' => $r->reasontext ?? null, 'for_month' => (int) ($r->formonth ?? 1), 'for_year' => (int) ($r->foryear ?? date('Y')),
            'calendar' => $this->calendar($r->calendar ?? 1), 'issued_at' => $this->date($r->createddate ?? null) ?? now()->toDateString()],
            skipWhen: fn ($r) => ! DB::table('employees')->where('id', $r->employeeid)->exists());
        $this->copy('pages', 'pages', fn ($r) => ['id' => $r->id, 'title' => $r->title ?? '', 'content' => $r->content ?? null,
            'type' => match ($r->type ?? 'content') { 'linkin' => 'internal_link', 'linkout' => 'external_link', default => 'content' },
            'link' => $r->link ?? null, 'sort_order' => (int) ($r->listorder ?? 0), 'is_active' => (bool) ($r->active ?? true),
            'position' => (string) ($r->positionid ?? 'header')]);
        $this->copy('email_list', 'newsletter_subscribers', fn ($r) => ['email' => $r->email ?? null, 'mobile' => $r->mobile ?? null, 'name' => $r->name ?? null]);
        $this->copy('device_tokens', 'device_tokens', fn ($r) => ['user_id' => $r->userid, 'platform' => $r->platform ?? 'android', 'token' => $r->token,
            'token_hash' => hash('sha256', (string) $r->token)], skipWhen: fn ($r) => ! DB::table('users')->where('id', $r->userid)->exists(), unique: ['token_hash']);
    }

    /** Office-edited print templates and message templates replace the defaults. */
    private function templates(): void
    {
        $keys = [];
        foreach ($this->rows('print_samples') as $r) {
            $key = Str::slug((string) ($r->name ?? $r->title ?? 'sample'), '_') ?: 'sample_'.$r->id;
            $key = isset($keys[$key]) ? $key.'_'.$r->id : $key;
            $keys[$key] = true;
            DB::table('print_templates')->updateOrInsert(['key' => 'legacy_'.$key], $this->stamp([
                'title' => $r->title ?? $key, 'header' => $r->header ?? null, 'content' => $r->content ?? '', 'footer' => $r->footer ?? null,
            ]));
        }
        $this->count('print_samples', count($keys));

        $count = 0;
        foreach ($this->rows('email_templates') as $r) {
            try {
                $event = NotificationEvent::fromLegacy((string) ($r->action ?? ''));
            } catch (Throwable) {
                $this->warn('email template for unknown action '.($r->action ?? '?'));

                continue;
            }
            DB::table('message_templates')->where('event', $event->value)->update(array_filter([
                'email_subject' => $r->subject ?? null, 'email_body' => $r->content ?? null, 'sms_body' => $r->sms ?? null,
                'email_enabled' => isset($r->active) ? (bool) $r->active : null, 'sms_enabled' => isset($r->sms_active) ? (bool) $r->sms_active : null,
                'updated_at' => now(),
            ], fn ($v) => $v !== null));
            $count++;
        }
        $this->count('email_templates', $count);
    }

    /** Legacy building.contractid points at the active contract of each unit. */
    private function currentContracts(): void
    {
        foreach ($this->rows('building') as $r) {
            if (empty($r->contractid)) {
                continue;
            }
            $sale = in_array($r->type ?? 'rent', ['sell', 'sale'], true);
            $morph = $sale ? 'sale_contract' : 'rent_contract';
            if (DB::table($sale ? 'sale_contracts' : 'rent_contracts')->where('id', $r->contractid)->exists()) {
                DB::table('units')->where('id', $r->id)->update(['current_contract_type' => $morph, 'current_contract_id' => $r->contractid]);
            }
        }
        foreach (['rent_contract' => 'RC', 'sale_contract' => 'SC', 'receipt_voucher' => 'RV', 'payment_voucher' => 'PV', 'invoice' => 'INV'] as $key => $p) {
            // continue numbering after the imported documents
            $table = Str::plural($key);
            $max = (int) DB::table($table)->max('id');
            DB::table('document_sequences')->updateOrInsert(['key' => $key, 'year' => (int) now()->format('Y')], ['last_number' => $max, 'updated_at' => now(), 'created_at' => now()]);
        }
    }

    /** Legacy attachments(moduleid → cp_module.name, objectid, path) → media library. */
    private function attachments(): void
    {
        if ($this->mediaPath === null) {
            return;
        }
        $modules = $this->rows('cp_module')->pluck('name', 'id');
        $models = [
            'building' => Unit::class, 'rent' => RentContract::class, 'sell' => SaleContract::class, 'owner' => Owner::class,
            'buyer' => Client::class, 'employees' => Employee::class, 'statementsin' => ReceiptVoucher::class, 'statementsout' => PaymentVoucher::class,
        ];
        $count = 0;
        foreach ($this->rows('attachments') as $r) {
            $class = $models[$modules[$r->moduleid ?? 0] ?? ''] ?? null;
            if ($class === null || empty($r->objectid)) {
                continue;
            }
            $model = $class::query()->find($r->objectid);
            $file = $this->mediaPath.'/'.ltrim((string) $r->path, '/');
            if ($model === null || ! is_file($file)) {
                $this->warn("attachment {$r->id}: ".($model === null ? 'record' : 'file '.$r->path).' not found');

                continue;
            }
            $collection = $model instanceof Unit && preg_match('/\.(jpe?g|png|gif|webp)$/i', $file) ? 'photos' : 'attachments';
            try {
                $model->addMedia($file)->preservingOriginal()->usingName((string) ($r->name ?: basename($file)))->toMediaCollection($collection);
                $count++;
            } catch (Throwable $e) {
                $this->warn("attachment {$r->id}: ".Str::limit($e->getMessage(), 160));
            }
        }
        $this->count('attachments', $count);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  callable(object): array<string, mixed>  $map
     * @param  callable(object): bool|null  $skipWhen
     * @param  list<string>  $unique  columns that must stay unique (later duplicates are nulled or skipped)
     */
    private function copy(string $from, string $to, callable $map, ?callable $skipWhen = null, array $unique = []): void
    {
        $count = 0;
        $seen = [];
        foreach ($this->rows($from) as $row) {
            if ($skipWhen && $skipWhen($row)) {
                $this->warn("{$from} #".($row->id ?? '?').' skipped: missing reference');

                continue;
            }
            try {
                $data = $map($row);
                foreach ($unique as $column) {
                    $value = $data[$column] ?? null;
                    if ($value === null) {
                        continue;
                    }
                    if (isset($seen[$column][$value]) || DB::table($to)->where($column, $value)->exists()) {
                        if ($column === 'token_hash') {
                            continue 2;
                        }
                        $this->warn("{$from} #".($row->id ?? '?')." duplicate {$column} {$value} cleared");
                        $data[$column] = null;
                    }
                    $seen[$column][$value] = true;
                }
                DB::table($to)->insert($this->stamp($data));
                $count++;
            } catch (Throwable $e) {
                $this->warn("{$from} #".($row->id ?? '?').': '.Str::limit($e->getMessage(), 160));
            }
        }
        $this->count($from, $count);
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private function rows(string $table)
    {
        if (! $this->legacy->getSchemaBuilder()->hasTable($table)) {
            return collect();
        }

        return $this->legacy->table($table)->orderBy($this->legacy->getSchemaBuilder()->hasColumn($table, 'id') ? 'id' : DB::raw('1'))->get();
    }

    private function replaceDefaults(string $legacyTable, string $table): void
    {
        if ($this->rows($legacyTable)->isNotEmpty()) {
            DB::table($table)->delete();
        }
    }

    private function stamp(array $data): array
    {
        return $data + ['created_at' => now(), 'updated_at' => now()];
    }

    private function person(object $r, string $idNumber = 'idnumber'): array
    {
        $parts = [$r->fname ?? ($r->name ?? ''), $r->fathname ?? null, $r->lname ?? null, $r->famname ?? null];

        return [
            'first_name' => $parts[0] ?: '-', 'father_name' => $parts[1], 'grandfather_name' => $parts[2], 'family_name' => $parts[3],
            'full_name' => (function () use ($parts) {
                return trim(preg_replace('/\s+/u', ' ', implode(' ', array_filter($parts, fn ($p) => filled($p)))));
            })() ?: '-',
            'id_type' => $this->idType($r->idtype ?? null), 'id_number' => $r->{$idNumber} ?? null, 'id_source' => $r->idsource ?? null,
            'id_issue_date' => $this->date($r->iddate ?? null), 'nationality' => $r->nationality ?? null, 'mobile' => $r->mobile ?? null,
            'email' => filter_var($r->email ?? null, FILTER_VALIDATE_EMAIL) ? $r->email : null, 'home_phone' => $r->homephone ?? null,
            'work' => $r->work ?? null, 'work_phone' => $r->workphone ?? null, 'address' => $r->address ?? null,
        ];
    }

    /** Legacy from_type → morph alias + id. */
    private function party(?string $type, mixed $id): array
    {
        $id = is_string($id) && str_contains($id, ',') ? (int) explode(',', $id)[0] : (int) $id;
        $map = ['owner' => ['owner', 'owners'], 'buildowner' => ['owner', 'owners'], 'buildrenter' => ['client', 'clients'], 'buildbuyer' => ['client', 'clients'],
            'employee' => ['employee', 'employees'], 'citizen' => ['citizen', 'citizens'], 'govorg' => ['government_organization', 'government_organizations'],
            'comorg' => ['trade_organization', 'trade_organizations']];
        if (! isset($map[$type]) || $id <= 0 || ! DB::table($map[$type][1])->where('id', $id)->exists()) {
            return [null, null];
        }

        return [$map[$type][0], $id];
    }

    private function salePlan(string $type, object $r): array
    {
        if ($type === 'once') {
            $once = $this->json($r->once ?? null) ?? [];

            return ['commission' => (float) ($once['comission'] ?? 0), 'commission_type' => ($once['comissiontype'] ?? 'money') === 'money' ? 'money' : 'percent',
                'commission_payer' => $this->payer($once['comissionmethod'] ?? null)];
        }
        if ($type === 'parts') {
            $p = $this->json($r->parts ?? null) ?? [];
            $parts = [];
            foreach (array_values((array) ($p['total'] ?? [])) as $i => $amount) {
                $deferred = (int) (array_values((array) ($p['cheque_notnow'] ?? []))[$i] ?? 0) === 1;
                $parts[] = ['amount' => (float) $amount, 'commission' => (float) (array_values((array) ($p['cash_commvalue'] ?? []))[$i] ?? 0),
                    'received_date' => $this->date(array_values((array) ($p['cheque_recdate'] ?? []))[$i] ?? null), 'deferred' => $deferred,
                    'due_date' => $this->date(array_values((array) ($p['cheque_todate'] ?? []))[$i] ?? null)];
            }

            return ['commission_payer' => $this->payer(array_values((array) ($p['comissionmethod'] ?? []))[0] ?? null), 'parts' => $parts];
        }
        $inst = $this->json($r->installment ?? null) ?? [];

        return ['first_payment' => (float) ($inst['firstvalue'] ?? 0), 'count' => (int) ($inst['installscount'] ?? 1),
            'cycle' => $this->cycle($inst['period'] ?? 'month') ?? 'month', 'start_date' => $this->date($inst['startdate'] ?? null)];
    }

    private function payer(?string $legacy): ?string
    {
        return match ($legacy) { 'buyer' => 'buyer', 'seller' => 'seller', 'fifty' => 'split', default => null };
    }

    private function installmentType(?string $legacy): string
    {
        try {
            return InstallmentType::fromLegacy((string) $legacy)->value;
        } catch (Throwable) {
            $this->warn("unknown payment type {$legacy}, imported as monthly rent");

            return InstallmentType::MonthRent->value;
        }
    }

    private function transactionType(string $code): int
    {
        if (! isset($this->transactionTypes[$code])) {
            $this->transactionTypes[$code] = DB::table('transaction_types')->insertGetId($this->stamp(['code' => $code, 'title' => $code, 'is_income' => false, 'is_system' => false]));
            $this->warn("transaction type {$code} was not defined; created");
        }

        return $this->transactionTypes[$code];
    }

    private function cycle(?string $legacy): ?string
    {
        if (blank($legacy)) {
            return null;
        }
        try {
            return PaymentCycle::fromLegacy($legacy)->value;
        } catch (Throwable) {
            return null;
        }
    }

    private function periodUnit(?string $legacy): ?string
    {
        return in_array($legacy, ['day', 'month', 'year'], true) ? $legacy : null;
    }

    private function usage(?string $legacy): ?string
    {
        return match ($legacy) { 'habit', 'residential', 'سكني' => 'residential', 'comm', 'commercial', 'تجاري' => 'commercial', default => null };
    }

    private function idType(?string $legacy): ?string
    {
        return match ($legacy) { 'national', 'national_id', '1' => 'national_id', 'iqama', 'residence', '2' => 'iqama', 'passport', '3' => 'passport',
            'gcc', 'gcc_id' => 'gcc_id', 'cr', 'commercial_register' => 'commercial_register', default => null };
    }

    private function calendar(mixed $legacy): string
    {
        return in_array((string) $legacy, ['2', 'hijri'], true) ? 'hijri' : 'gregorian';
    }

    private function date(mixed $value): ?string
    {
        if (blank($value) || str_starts_with((string) $value, '0000')) {
            return null;
        }
        try {
            return \Carbon\CarbonImmutable::parse((string) $value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function timestamp(mixed $value): string
    {
        return is_numeric($value) && $value > 0 ? date('Y-m-d H:i:s', (int) $value) : (string) ($this->date($value) ?? now());
    }

    private function json(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function gps(?string $value): array
    {
        if (! is_string($value) || ! preg_match('/(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/', $value, $m)) {
            return ['latitude' => null, 'longitude' => null];
        }

        return ['latitude' => (float) $m[1], 'longitude' => (float) $m[2]];
    }

    private function num(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function ref(string $table, mixed $id): ?int
    {
        return ! empty($id) && is_numeric($id) && DB::table($table)->where('id', $id)->exists() ? (int) $id : null;
    }

    private function userId(mixed $id): ?int
    {
        return $this->ref('users', $id);
    }

    private function count(string $table, int $n): void
    {
        $this->report[$table] = $n;
    }

    private function warn(string $message): void
    {
        $this->warnings[] = $message;
    }
}
