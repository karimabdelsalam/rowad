<?php

namespace App\Providers;

use App\Models;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Tables\Columns\Column;
use Filament\Schemas\Schema;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(\App\Services\Payments\PaymentGateway::class, \App\Services\Payments\MoyasarGateway::class);
        // Arabic plurals stay Arabic (see App\Support\Translation\Translator).
        $this->app->extend('translator', function (\Illuminate\Translation\Translator $base, $app) {
            $translator = new \App\Support\Translation\Translator($app['translation.loader'], $base->getLocale());
            $translator->setFallback($base->getFallback());

            return $translator;
        });

        //
    }

    public function boot(): void
    {
        // contract signing links: limits per link, so parties signing from one office network don't block each other
        \Illuminate\Support\Facades\RateLimiter::for('sign-code', fn (\Illuminate\Http\Request $request) => \Illuminate\Cache\RateLimiting\Limit::perMinutes(10, 5)
            ->by('sign-code|'.$request->route('token')));
        \Illuminate\Support\Facades\RateLimiter::for('sign', fn (\Illuminate\Http\Request $request) => \Illuminate\Cache\RateLimiting\Limit::perMinutes(10, 30)
            ->by('sign|'.$request->route('token').'|'.$request->ip()));
        if (filled($proxies = config('rowad.trusted_proxies'))) {
            \Illuminate\Http\Middleware\TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
        \Illuminate\Support\Facades\Auth::provider('legacy-aware', fn ($app, array $config) => new \App\Auth\LegacyAwareUserProvider($app['hash'], $config['model']));

        Model::shouldBeStrict(! $this->app->isProduction());

        // Field/column labels are derived from attribute names and translated through lang/*.json.
        Field::configureUsing(fn (Field $field) => $field->translateLabel());
        Column::configureUsing(fn (Column $column) => $column->translateLabel());
        Entry::configureUsing(fn (Entry $entry) => $entry->translateLabel());
        BaseFilter::configureUsing(fn (BaseFilter $filter) => $filter->translateLabel());

        // Same date look in every browser (native inputs follow the OS locale, e.g. mm/dd/yyyy); week starts on Saturday.
        \Filament\Forms\Components\DateTimePicker::configureUsing(fn (\Filament\Forms\Components\DateTimePicker $picker) => $picker::class === \Filament\Forms\Components\DateTimePicker::class
            ? $picker->native(false)->displayFormat("Y-m-d\u{200E} H:i")->seconds(false)->firstDayOfWeek(6) // LRM keeps date and time in order inside RTL
            : $picker);
        \Filament\Forms\Components\DatePicker::configureUsing(fn (\Filament\Forms\Components\DatePicker $picker) => $picker
            ->native(false)->displayFormat('Y-m-d')->firstDayOfWeek(6)->closeOnDateSelection());

        // Plan storage quota for every upload (legacy total_file_size check).
        \Filament\Forms\Components\BaseFileUpload::configureUsing(fn (\Filament\Forms\Components\BaseFileUpload $upload) => $upload->rule(new \App\Rules\WithinStoragePlan));

        // Arabic UI with Western digits (Saudi business convention, as in the legacy system).
        Table::configureUsing(fn (Table $table) => $table->defaultNumberLocale('en'));
        \App\Filament\Tables\TableExport::register();
        Schema::configureUsing(fn (Schema $schema) => $schema->defaultNumberLocale('en'));

        // Stable aliases stored in *_type columns (vouchers.party, installments.contract,
        // media, activity log, permissions) so class renames never break stored data.
        Relation::enforceMorphMap([
            'activity' => \Spatie\Activitylog\Models\Activity::class,
            'bank_account' => Models\BankAccount::class,
            'bank_transaction' => Models\BankTransaction::class,
            'branch' => Models\Branch::class,
            'building' => Models\Building::class,
            'campaign' => Models\Campaign::class,
            'citizen' => Models\Citizen::class,
            'city' => Models\City::class,
            'client' => Models\Client::class,
            'company_partner' => Models\CompanyPartner::class,
            'contract_signature' => Models\ContractSignature::class,
            'contract_term' => Models\ContractTerm::class,
            'office_file' => Models\OfficeFile::class,
            'custom_field' => Models\CustomField::class,
            'custom_field_group' => Models\CustomFieldGroup::class,
            'custom_field_value' => Models\CustomFieldValue::class,
            'device_token' => Models\DeviceToken::class,
            'district' => Models\District::class,
            'employee' => Models\Employee::class,
            'employee_advance' => Models\EmployeeAdvance::class,
            'employee_penalty' => Models\EmployeePenalty::class,
            'end_of_service_settlement' => Models\EndOfServiceSettlement::class,
            'leave_request' => Models\LeaveRequest::class,
            'payroll_item' => Models\PayrollItem::class,
            'payroll_run' => Models\PayrollRun::class,
            'government_license' => Models\GovernmentLicense::class,
            'inbox_message' => Models\InboxMessage::class,
            'installment' => Models\Installment::class,
            'installment_payment' => Models\InstallmentPayment::class,
            'invoice' => Models\Invoice::class,
            'letter' => Models\Letter::class,
            'login_activity' => Models\LoginActivity::class,
            'maintenance_request' => Models\MaintenanceRequest::class,
            'message_template' => Models\MessageTemplate::class,
            'newsletter_subscriber' => Models\NewsletterSubscriber::class,
            'note' => Models\Note::class,
            'online_payment' => Models\OnlinePayment::class,
            'otp_code' => Models\OtpCode::class,
            'outgoing_message' => Models\OutgoingMessage::class,
            'owner' => Models\Owner::class,
            'owner_transaction' => Models\OwnerTransaction::class,
            'page' => Models\Page::class,
            'payment_voucher' => Models\PaymentVoucher::class,
            'print_template' => Models\PrintTemplate::class,
            'print_template_version' => Models\PrintTemplateVersion::class,
            'receipt_voucher' => Models\ReceiptVoucher::class,
            'rent_contract' => Models\RentContract::class,
            'sale_contract' => Models\SaleContract::class,
            'setting' => Models\Setting::class,
            'support_ticket' => Models\SupportTicket::class,
            'support_ticket_reply' => Models\SupportTicketReply::class,
            'task' => Models\Task::class,
            'unit' => Models\Unit::class,
            'unit_meter' => Models\UnitMeter::class,
            'unit_deed' => Models\UnitDeed::class,
            'unit_plot' => Models\UnitPlot::class,
            'unit_report' => Models\UnitReport::class,
            'user' => Models\User::class,
            'bank' => Models\Lookups\Bank::class,
            'border_type' => Models\Lookups\BorderType::class,
            'client_category' => Models\Lookups\ClientCategory::class,
            'deed_type' => Models\Lookups\DeedType::class,
            'expense_type' => Models\Lookups\ExpenseType::class,
            'government_organization' => Models\Lookups\GovernmentOrganization::class,
            'meter_type' => Models\Lookups\MeterType::class,
            'property_category' => Models\Lookups\PropertyCategory::class,
            'trade_organization' => Models\Lookups\TradeOrganization::class,
            'transaction_type' => Models\Lookups\TransactionType::class,
            'unit_report_category' => Models\Lookups\UnitReportCategory::class,
        ]);
    }
}
