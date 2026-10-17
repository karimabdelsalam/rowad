<?php

namespace App\Filament\Admin\Resources\RentContracts\Schemas;

use App\Enums\ContractStatus;
use App\Enums\EjarStatus;
use App\Filament\Admin\Resources\Concerns\ContractInfolistParts;
use App\Filament\Admin\Resources\RentContracts\SignatureActions;
use App\Filament\Infolists\Parts;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
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
            ->columns(1)
            ->components([
                self::header(),

                Tabs::make('contract')->contained(false)->persistTabInQueryString()->tabs([
                    Tab::make(__('نظرة عامة'))->icon(Heroicon::OutlinedDocumentText)->schema([
                        Grid::make(['default' => 1, 'lg' => 3])->schema([
                            Grid::make(1)->columnSpan(['lg' => 2])->schema([
                                Section::make(__('بيانات العقد'))->icon(Heroicon::OutlinedDocumentText)->columns(['default' => 2, 'md' => 4])->schema([
                                    TextEntry::make('number')->label(__('رقم العقد'))->weight('bold')->copyable()->placeholder('-'),
                                    ContractInfolistParts::unit()->columnSpan(['default' => 1, 'md' => 2]),
                                    TextEntry::make('usage')->label(__('الاستخدام'))->badge()->color('gray')->placeholder('-'),
                                    TextEntry::make('contract_date')->label(__('تاريخ العقد'))->date('Y-m-d'),
                                    TextEntry::make('start_date')->label(__('تاريخ البداية'))->date('Y-m-d'),
                                    TextEntry::make('end_date')->label(__('تاريخ النهاية'))->date('Y-m-d')
                                        ->color(fn (RentContract $record) => $record->end_date?->isBefore(today()->addDays(30)) ? 'warning' : null),
                                    TextEntry::make('period')->label(__('المدة'))
                                        ->state(fn (RentContract $record) => $record->period_count.' '.$record->period_unit?->getLabel()),
                                    TextEntry::make('ended_at')->label(__('تاريخ الإنهاء'))->date('Y-m-d')
                                        ->visible(fn (RentContract $record) => $record->ended_at !== null),
                                    TextEntry::make('cancelled_at')->label(__('تاريخ الإلغاء'))->date('Y-m-d')
                                        ->visible(fn (RentContract $record) => $record->cancelled_at !== null),
                                ]),
                                ContractInfolistParts::parties(__('الأطراف')),
                            ]),
                            Grid::make(1)->columnSpan(['lg' => 1])->schema([
                                Section::make(__('المبالغ'))->icon(Heroicon::OutlinedBanknotes)->columns(2)->schema([
                                    TextEntry::make('rent_amount')->label(__('قيمة الدفعة'))->numeric(2)->suffix(' '.__('ر.س'))
                                        ->weight('bold')->size('lg')->columnSpanFull()
                                        ->helperText(fn (RentContract $record) => $record->payment_cycle?->getLabel()),
                                    TextEntry::make('contract_total')->label(__('إجمالي العقد'))->numeric(2)
                                        ->state(fn (RentContract $record) => $record->installments->filter(fn ($i) => $i->type?->isRent())->sum('amount') ?: null)
                                        ->placeholder('-'),
                                    TextEntry::make('commission')->label(__('العمولة'))->numeric(2)->placeholder('-')
                                        ->helperText(fn (RentContract $record) => $record->commission_cycle?->getLabel()),
                                    TextEntry::make('vat_amount')->label(__('ضريبة القيمة المضافة'))->numeric(2)
                                        ->helperText(fn (RentContract $record) => (float) $record->vat_percent > 0 ? $record->vat_percent.'%' : null)
                                        ->visible(fn (RentContract $record) => $record->vat_applicable),
                                ]),
                                ContractInfolistParts::progress(),
                            ]),
                        ]),
                    ]),

                    Tab::make(__('التوثيق والتوقيع'))->icon(Heroicon::OutlinedShieldCheck)
                        ->badge(fn (RentContract $record) => $record->ejar_status === EjarStatus::Pending ? '!' : null)->badgeColor('danger')
                        ->schema([
                            Grid::make(['default' => 1, 'lg' => 2])->schema([
                                self::ejar(),
                                SignatureActions::section()->visible(true),
                            ]),
                        ]),

                    Tab::make(__('البنود والملاحظات'))->icon(Heroicon::OutlinedListBullet)->schema([
                        Section::make()->schema([
                            TextEntry::make('terms.title')->label(__('بنود العقد'))->listWithLineBreaks()->bulleted()->placeholder(__('لا توجد بنود مختارة')),
                            TextEntry::make('extra_terms')->label(__('شروط إضافية'))->html()->prose()->placeholder('-'),
                            TextEntry::make('notes')->label(__('ملاحظات'))->placeholder('-'),
                            KeyValueEntry::make('meter_readings')->label(__('قراءات العدادات'))
                                ->keyLabel(__('رقم العداد'))->valueLabel(__('القراءة'))
                                ->visible(fn (RentContract $record) => filled($record->meter_readings)),
                        ]),
                    ]),

                    Tab::make(__('السجل'))->icon(Heroicon::OutlinedClock)->schema([
                        ContractInfolistParts::record()->collapsed(false),
                    ]),
                ]),
            ]);
    }

    /** The contract at a glance: unit, tenants and the figures staff look for first. */
    private static function header(): \Filament\Infolists\Components\ViewEntry
    {
        return Parts::header(Heroicon::OutlinedDocumentText, function (RentContract $r) {
            $installments = $r->installments;
            $total = (float) $installments->sum('amount');
            $paid = (float) $installments->sum(fn ($i) => $i->paidTotal());
            $overdue = (float) $installments->filter(fn ($i) => ! $i->is_collected && $i->due_date?->isBefore(today()))->sum(fn ($i) => $i->remainingAmount());
            $signatures = $r->signatures;

            return [
                'kicker' => __('عقد إيجار').' · '.$r->number,
                'title' => $r->unit?->title ?? '-',
                'sub' => [$r->clients->pluck('full_name')->join('، '), $r->unit?->building?->title,
                    $r->start_date?->format('Y-m-d').' ← '.$r->end_date?->format('Y-m-d')],
                'facts' => array_values(array_filter([
                    Parts::fact('الحالة', $r->status?->getLabel(), $r->status === ContractStatus::Active ? 'success' : null),
                    Parts::fact('قيمة الدفعة', number_format((float) $r->rent_amount, 2).' · '.$r->payment_cycle?->getLabel()),
                    $overdue > 0 ? Parts::fact('متأخرات', number_format($overdue, 2), 'danger')
                        : Parts::fact('الدفعة القادمة', $r->next_payment_date?->format('Y-m-d') ?? '-'),
                    Parts::fact('التحصيل', $total > 0 ? round($paid / $total * 100).'%' : '-', 'success'),
                    Parts::fact('إيجار', $r->ejar_status?->getLabel(), $r->ejar_status === EjarStatus::Documented ? 'success' : 'warning'),
                    $signatures->isNotEmpty() ? Parts::fact('التوقيع', $r->signed_at ? __('مكتمل') : __(':n من :t', [
                        'n' => $signatures->where('status', 'signed')->count(), 't' => $signatures->count()]), $r->signed_at ? 'success' : 'warning') : null,
                ])),
            ];
        });
    }

    private static function ejar(): Section
    {
        return Section::make(__('منصة إيجار'))->icon(Heroicon::OutlinedBuildingLibrary)->columns(2)->schema([
            TextEntry::make('ejar_status')->label(__('التوثيق'))->badge()->columnSpanFull(),
            TextEntry::make('ejar_number')->label(__('رقم العقد في إيجار'))->copyable()->weight('bold')
                ->visible(fn (RentContract $record) => filled($record->ejar_number)),
            TextEntry::make('ejar_documented_at')->label(__('تاريخ التوثيق'))->date('Y-m-d')
                ->visible(fn (RentContract $record) => $record->ejar_documented_at !== null),
            TextEntry::make('ejar_state')->label(__('الحالة في إيجار'))
                ->state(fn (RentContract $record) => $record->ejar_state.($record->ejar_synced_at ? ' · '.__('مزامنة :d', ['d' => $record->ejar_synced_at->format('Y-m-d H:i')]) : ''))
                ->visible(fn (RentContract $record) => filled($record->ejar_state)),
            TextEntry::make('ejar_error')->label(__('توقف الإرسال إلى إيجار'))->columnSpanFull()->color('danger')->icon(Heroicon::OutlinedExclamationTriangle)
                ->extraAttributes(['style' => 'white-space:pre-line'])
                ->visible(fn (RentContract $record) => filled($record->ejar_error)),
            TextEntry::make('ejar_notes')->label(__('ملاحظة'))->columnSpanFull()
                ->visible(fn (RentContract $record) => filled($record->ejar_notes) && $record->ejar_status === EjarStatus::NotRequired),
            TextEntry::make('ejar_pdf')->label(__('العقد الموثق'))->columnSpanFull()->color('primary')->icon(Heroicon::OutlinedDocumentArrowDown)
                ->state(fn (RentContract $record) => $record->getFirstMedia('ejar')?->file_name)
                ->url(fn (RentContract $record) => ($media = $record->getFirstMedia('ejar')) ? route('site.media', ['media' => $media]) : null, true)
                ->visible(fn (RentContract $record) => $record->getFirstMedia('ejar') !== null),
            ViewEntry::make('ejar_readiness')->hiddenLabel()->columnSpanFull()
                ->view('filament.admin.ejar-readiness')
                ->visible(fn (RentContract $record) => in_array($record->ejar_status, [EjarStatus::Pending, EjarStatus::AwaitingParties], true)),
        ]);
    }
}
