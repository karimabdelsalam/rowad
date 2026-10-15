<?php

namespace App\Filament\Admin\Resources\SaleContracts\Schemas;

use App\Enums\SalePaymentType;
use App\Filament\Admin\Resources\Concerns\ContractInfolistParts;
use App\Models\SaleContract;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Sale contract card: contract, buyers, terms — with the price, payment plan and collection progress at the side. */
class SaleContractInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Grid::make(1)->columnSpan(['lg' => 2])->schema([
                    Section::make(__('بيانات العقد'))->icon(Heroicon::OutlinedDocumentCheck)->columns(['default' => 2, 'md' => 4])->schema([
                        TextEntry::make('number')->label(__('رقم العقد'))->weight('bold')->placeholder('-'),
                        TextEntry::make('status')->label(__('الحالة'))->badge(),
                        ContractInfolistParts::unit()->columnSpan(2),
                        TextEntry::make('contract_date')->label(__('تاريخ العقد'))->date('Y-m-d'),
                        TextEntry::make('payment_type')->label(__('طريقة السداد'))->badge(),
                    ]),

                    ContractInfolistParts::parties(__('المشترون')),

                    Section::make(__('البنود والملاحظات'))->icon(Heroicon::OutlinedListBullet)->collapsible()
                        ->visible(fn ($record) => $record->terms->isNotEmpty() || filled(strip_tags((string) $record->extra_terms)) || filled($record->notes) || filled($record->meter_readings ?? null))
                        ->schema([
                        TextEntry::make('terms.title')->label(__('بنود العقد'))->listWithLineBreaks()->bulleted()->placeholder('-'),
                        TextEntry::make('extra_terms')->label(__('شروط إضافية'))->html()->prose()->placeholder('-'),
                        TextEntry::make('notes')->label(__('ملاحظات'))->placeholder('-'),
                    ]),
                ]),

                Grid::make(1)->columnSpan(['lg' => 1])->schema([
                    Section::make(__('المبالغ'))->icon(Heroicon::OutlinedBanknotes)->columns(2)->schema([
                        TextEntry::make('total_amount')->label(__('قيمة البيع'))->numeric(2)->suffix(' '.__('ر.س'))
                            ->weight('bold')->size('lg')->columnSpanFull(),
                        TextEntry::make('plan_commission')->label(__('العمولة'))->numeric(2)
                            ->state(fn (SaleContract $record) => $record->payment_plan['commission'] ?? null)
                            ->visible(fn (SaleContract $record) => $record->payment_type === SalePaymentType::Once),
                        TextEntry::make('plan_payer')->label(__('يدفع العمولة'))
                            ->state(fn (SaleContract $record) => \App\Enums\CommissionPayer::tryFrom((string) ($record->payment_plan['commission_payer'] ?? ''))?->getLabel())
                            ->visible(fn (SaleContract $record) => $record->payment_type !== SalePaymentType::Installment),
                        TextEntry::make('plan_first')->label(__('الدفعة الأولى'))->numeric(2)
                            ->state(fn (SaleContract $record) => $record->payment_plan['first_payment'] ?? null)
                            ->visible(fn (SaleContract $record) => $record->payment_type === SalePaymentType::Installment),
                        TextEntry::make('plan_count')->label(__('عدد الأقساط'))
                            ->state(fn (SaleContract $record) => $record->payment_plan['count'] ?? null)
                            ->visible(fn (SaleContract $record) => $record->payment_type === SalePaymentType::Installment),
                    ]),

                    ContractInfolistParts::progress(),

                    ContractInfolistParts::record(),
                ]),
            ]);
    }
}
