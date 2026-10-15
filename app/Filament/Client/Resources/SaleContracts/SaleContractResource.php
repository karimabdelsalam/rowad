<?php

namespace App\Filament\Client\Resources\SaleContracts;

use App\Filament\Client\Resources\SaleContracts\Pages\ManageSaleContracts;
use App\Filament\PortalResource;
use App\Models\SaleContract;
use App\Support\Portal;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use App\Filament\Infolists\Parts;
use App\Enums\ContractStatus;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Legacy ccp/sell: the client's purchase contracts. */
class SaleContractResource extends PortalResource
{
    protected static ?string $model = SaleContract::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'عقد بيع';

    protected static ?string $pluralModelLabel = 'عقود الشراء';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('clients', fn (Builder $q) => $q->whereKey(Portal::client()->getKey()))
            ->with('unit.building');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            Parts::header(Heroicon::OutlinedDocumentCheck, fn (SaleContract $r) => [
                'kicker' => __('عقد بيع').' · '.$r->number,
                'title' => $r->unit?->title ?? '-',
                'sub' => [$r->unit?->building?->title, __('بتاريخ :date', ['date' => $r->contract_date?->format('Y-m-d')])],
                'facts' => [
                    Parts::fact('الحالة', $r->status?->getLabel(), $r->status === ContractStatus::Active ? 'success' : null),
                    Parts::fact('قيمة العقد', Parts::amount($r->total_amount)),
                    Parts::fact('طريقة السداد', $r->payment_type?->getLabel() ?? '-'),
                ],
            ]),
            Section::make(__('العقد'))->icon(Heroicon::OutlinedDocumentText)->columns(2)->columnSpanFull()->schema([
                TextEntry::make('number')->label(__('الرقم'))->weight('bold'),
                Parts::date('contract_date', 'تاريخ العقد'),
                Parts::money('total_amount', 'قيمة العقد')->weight('bold'),
                TextEntry::make('payment_type')->label(__('طريقة السداد'))->badge()->color('gray'),
                TextEntry::make('status')->label(__('الحالة'))->badge(),
                TextEntry::make('extra_terms')->label(__('شروط إضافية'))->placeholder('-')->columnSpanFull()->prose(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('unit.title')->label(__('الوحدة'))->weight('bold')
                    ->description(fn (SaleContract $r) => __('عقد :n', ['n' => $r->number])),
                TextColumn::make('contract_date')->label(__('تاريخ العقد'))->date('Y-m-d'),
                TextColumn::make('total_amount')->label(__('قيمة العقد'))->numeric(2)->weight('bold'),
                TextColumn::make('payment_type')->label(__('طريقة السداد'))->badge()->color('gray'),
                TextColumn::make('status')->label(__('الحالة'))->badge(),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('print')
                    ->label(__('طباعة'))
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedPrinter)
                    ->url(fn ($record) => route('documents.print', ['type' => 'sale_contract', 'id' => $record->getKey()]))
                    ->openUrlInNewTab(),
ViewAction::make()->label(__('التفاصيل'))->modalWidth('5xl')]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSaleContracts::route('/')];
    }
}
