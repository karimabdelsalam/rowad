<?php

namespace App\Filament\Admin\Resources\RentContracts\Schemas;

use App\Filament\Admin\Resources\Concerns\ContractInfolistParts;
use App\Models\RentContract;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Rent contract card: contract & period, parties, terms — with amounts and collection progress at the side. */
class RentContractInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Grid::make(1)->columnSpan(['lg' => 2])->schema([
                    Section::make(__('بيانات العقد'))->icon(Heroicon::OutlinedDocumentText)->columns(['default' => 2, 'md' => 4])->schema([
                        TextEntry::make('number')->label(__('رقم العقد'))->weight('bold')->placeholder('-'),
                        TextEntry::make('status')->label(__('الحالة'))->badge(),
                        ContractInfolistParts::unit()->columnSpan(2),
                        TextEntry::make('contract_date')->label(__('تاريخ العقد'))->date('Y-m-d'),
                        TextEntry::make('start_date')->label(__('تاريخ البداية'))->date('Y-m-d'),
                        TextEntry::make('end_date')->label(__('تاريخ النهاية'))->date('Y-m-d')
                            ->color(fn (RentContract $record) => $record->end_date?->isBefore(today()->addDays(30)) ? 'warning' : null),
                        TextEntry::make('period')->label(__('المدة'))
                            ->state(fn (RentContract $record) => $record->period_count.' '.$record->period_unit?->getLabel()),
                        TextEntry::make('usage')->label(__('الاستخدام'))->badge()->placeholder('-'),
                        TextEntry::make('ended_at')->label(__('تاريخ الإنهاء'))->date('Y-m-d')
                            ->visible(fn (RentContract $record) => $record->ended_at !== null),
                        TextEntry::make('cancelled_at')->label(__('تاريخ الإلغاء'))->date('Y-m-d')
                            ->visible(fn (RentContract $record) => $record->cancelled_at !== null),
                    ]),

                    ContractInfolistParts::parties(__('المستأجرون')),

                    Section::make(__('البنود والملاحظات'))->icon(Heroicon::OutlinedListBullet)->collapsible()
                        ->visible(fn ($record) => $record->terms->isNotEmpty() || filled(strip_tags((string) $record->extra_terms)) || filled($record->notes) || filled($record->meter_readings ?? null))
                        ->schema([
                        TextEntry::make('terms.title')->label(__('بنود العقد'))->listWithLineBreaks()->bulleted()->placeholder('-'),
                        TextEntry::make('extra_terms')->label(__('شروط إضافية'))->html()->prose()->placeholder('-'),
                        TextEntry::make('notes')->label(__('ملاحظات'))->placeholder('-'),
                        KeyValueEntry::make('meter_readings')->label(__('قراءات العدادات'))
                            ->keyLabel(__('رقم العداد'))->valueLabel(__('القراءة'))
                            ->visible(fn (RentContract $record) => filled($record->meter_readings)),
                    ]),
                ]),

                Grid::make(1)->columnSpan(['lg' => 1])->schema([
                    Section::make(__('المبالغ'))->icon(Heroicon::OutlinedBanknotes)->columns(2)->schema([
                        TextEntry::make('rent_amount')->label(__('قيمة الدفعة'))->numeric(2)->suffix(' '.__('ر.س'))
                            ->weight('bold')->size('lg')->columnSpanFull()
                            ->helperText(fn (RentContract $record) => $record->payment_cycle?->getLabel()),
                        TextEntry::make('yearly_rent')->label(__('الإيجار السنوي'))->numeric(2)
                            ->visible(fn (RentContract $record) => (float) $record->yearly_rent > 0),
                        TextEntry::make('contract_total')->label(__('إجمالي العقد'))->numeric(2)
                            ->state(fn (RentContract $record) => $record->installments->filter(fn ($i) => $i->type?->isRent())->sum('amount') ?: null)
                            ->placeholder('-'),
                        TextEntry::make('commission')->label(__('العمولة'))->numeric(2)->placeholder('-')
                            ->helperText(fn (RentContract $record) => $record->commission_cycle?->getLabel()),
                        TextEntry::make('vat_amount')->label(__('ضريبة القيمة المضافة'))->numeric(2)
                            ->helperText(fn (RentContract $record) => (float) $record->vat_percent > 0 ? $record->vat_percent.'%' : null)
                            ->visible(fn (RentContract $record) => $record->vat_applicable),
                    ]),

                    Section::make(__('الدفعة القادمة'))->icon(Heroicon::OutlinedCalendarDays)->columns(2)
                        ->visible(fn (RentContract $record) => $record->next_payment_date !== null)
                        ->schema([
                            TextEntry::make('next_payment_date')->label(__('التاريخ'))->date('Y-m-d')
                                ->color(fn (RentContract $record) => $record->next_payment_date?->isBefore(today()) ? 'danger' : 'primary')->weight('bold'),
                            TextEntry::make('next_payment_amount')->label(__('المبلغ'))->numeric(2)->weight('bold'),
                        ]),

                    ContractInfolistParts::progress(),

                    ContractInfolistParts::record(),
                ]),
            ]);
    }
}
