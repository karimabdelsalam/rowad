<?php

namespace App\Filament\Admin\Resources\Owners\Schemas;

use App\Enums\UnitStatus;
use App\Filament\Admin\Resources\OwnerTransactions\OwnerTransactionResource;
use App\Filament\Admin\Resources\Units\UnitResource;
use App\Filament\Infolists\Parts;
use App\Models\Owner;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Owner card: who they are, their units and shares, what the office owes them, and how to reach them. */
class OwnerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Parts::header(Heroicon::OutlinedUserCircle, function (Owner $r) {
                $balance = $r->outstandingBalance();

                return [
                    'kicker' => __('مالك'),
                    'title' => $r->full_name,
                    'sub' => [$r->id_type?->getLabel().($r->id_number ? ' '.$r->id_number : ''), $r->mobile, $r->nationality],
                    'facts' => [
                        Parts::fact('الوحدات', $r->units->count()),
                        Parts::fact('مؤجرة', $r->units->where('status', UnitStatus::Rented)->count(), 'success'),
                        Parts::fact('الرصيد المستحق له', Parts::amount($balance), $balance > 0 ? 'warning' : 'success'),
                    ],
                ];
            }),
            Tabs::make('owner')->contained(false)->persistTabInQueryString()->tabs([
                Tab::make(__('الوحدات والحساب'))->icon(Heroicon::OutlinedHomeModern)->schema([
                    Grid::make(['default' => 1, 'lg' => 3])->schema([
                        Grid::make(1)->columnSpan(['lg' => 2])->schema([
                            Section::make(__('الوحدات المملوكة'))->icon(Heroicon::OutlinedHomeModern)
                                ->description(fn (Owner $r) => trans_choice('{0} لا توجد وحدات|{1} وحدة واحدة|{2} وحدتان|[3,*] :count وحدات', $r->units->count()))
                                ->headerActions([
                                    \Filament\Actions\Action::make('statement')->label(__('كشف الحساب'))->icon(Heroicon::OutlinedScale)->link()
                                        ->url(fn (Owner $r) => OwnerTransactionResource::getUrl(parameters: ['tableFilters' => ['owner_id' => ['value' => $r->getKey()]]])),
                                ])
                                ->schema([
                                    RepeatableEntry::make('units')->hiddenLabel()->state(fn (Owner $r) => $r->units()->with('building')->get())->columns(['default' => 2, 'md' => 5])
                                        ->placeholder(__('لم تُسجَّل وحدات لهذا المالك بعد'))
                                        ->schema([
                                            Parts::link('title', 'الوحدة', fn ($record) => UnitResource::getUrl('view', ['record' => $record]))->columnSpan(2),
                                            TextEntry::make('status')->label(__('الحالة'))->badge(),
                                            TextEntry::make('pivot.share_percent')->label(__('نسبة الملكية'))->suffix(' %')->numeric(2),
                                            TextEntry::make('pivot.expenses_percent')->label(__('نسبة المصروفات'))->suffix(' %')->numeric(2)->placeholder('-'),
                                        ]),
                                ]),
                        ]),
                        Grid::make(1)->columnSpan(['lg' => 1])->schema([
                            Section::make(__('الحساب المالي'))->icon(Heroicon::OutlinedBanknotes)->schema([
                                TextEntry::make('balance')->label(__('الرصيد المستحق له'))->weight('bold')->size('lg')
                                    ->state(fn (Owner $r) => Parts::amount($r->outstandingBalance()))
                                    ->color(fn (Owner $r) => $r->outstandingBalance() > 0 ? 'warning' : 'success'),
                                TextEntry::make('pending_count')->label(__('حركات معلّقة'))->numeric()
                                    ->state(fn (Owner $r) => $r->transactions()->where('is_settled', false)->count()),
                                TextEntry::make('last_payment')->label(__('آخر سند صرف له'))->placeholder(__('لا يوجد'))
                                    ->state(fn (Owner $r) => $r->paymentVouchers()->latest('issued_at')->first()?->issued_at?->format('Y-m-d')),
                            ]),
                        ]),
                    ]),
                ]),
                Tab::make(__('البيانات الشخصية'))->icon(Heroicon::OutlinedIdentification)->schema([
                    Parts::identity(),
                    Parts::contact(),
                    Section::make(__('ملاحظات'))->icon(Heroicon::OutlinedPencilSquare)->visible(fn (Owner $r) => filled($r->notes))->schema([
                        TextEntry::make('notes')->hiddenLabel()->prose(),
                    ]),
                ]),
                Tab::make(__('البوابة والسجل'))->icon(Heroicon::OutlinedKey)->schema([
                    Grid::make(['default' => 1, 'lg' => 2])->schema([
                        Parts::portalAccount(),
                        Parts::record(false)->collapsed(false),
                    ]),
                ]),
            ]),
        ]);
    }
}
