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
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Auth::provider('legacy-aware', fn ($app, array $config) => new \App\Auth\LegacyAwareUserProvider($app['hash'], $config['model']));

        Model::shouldBeStrict(! $this->app->isProduction());

        // Field/column labels are derived from attribute names and translated through lang/*.json.
        Field::configureUsing(fn (Field $field) => $field->translateLabel());
        Column::configureUsing(fn (Column $column) => $column->translateLabel());
        Entry::configureUsing(fn (Entry $entry) => $entry->translateLabel());
        BaseFilter::configureUsing(fn (BaseFilter $filter) => $filter->translateLabel());

        // Arabic UI with Western digits (Saudi business convention, as in the legacy system).
        Table::configureUsing(fn (Table $table) => $table->defaultNumberLocale('en'));
        Schema::configureUsing(fn (Schema $schema) => $schema->defaultNumberLocale('en'));

        // Stable aliases stored in *_type columns (vouchers.party, installments.contract,
        // media, activity log, permissions) so class renames never break stored data.
        Relation::enforceMorphMap([
            'bank_account' => Models\BankAccount::class,
            'bank_transaction' => Models\BankTransaction::class,
            'building' => Models\Building::class,
            'campaign' => Models\Campaign::class,
            'citizen' => Models\Citizen::class,
            'city' => Models\City::class,
            'client' => Models\Client::class,
            'company_partner' => Models\CompanyPartner::class,
            'contract_term' => Models\ContractTerm::class,
            'custom_field' => Models\CustomField::class,
            'custom_field_group' => Models\CustomFieldGroup::class,
            'custom_field_value' => Models\CustomFieldValue::class,
            'device_token' => Models\DeviceToken::class,
            'district' => Models\District::class,
            'employee' => Models\Employee::class,
            'employee_penalty' => Models\EmployeePenalty::class,
            'government_license' => Models\GovernmentLicense::class,
            'inbox_message' => Models\InboxMessage::class,
            'installment' => Models\Installment::class,
            'installment_payment' => Models\InstallmentPayment::class,
            'invoice' => Models\Invoice::class,
            'letter' => Models\Letter::class,
            'login_activity' => Models\LoginActivity::class,
            'message_template' => Models\MessageTemplate::class,
            'newsletter_subscriber' => Models\NewsletterSubscriber::class,
            'note' => Models\Note::class,
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
