<?php

namespace App\Filament\Owner\Resources\RentContracts;

use App\Filament\Owner\Resources\RentContracts\Pages\ManageRentContracts;
use App\Filament\PortalResource;
use App\Models\RentContract;
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

/** Rent contracts on the owner's units (legacy ownerarea units/rent). */
class RentContractResource extends PortalResource
{
    protected static ?string $model = RentContract::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'عقد إيجار';

    protected static ?string $pluralModelLabel = 'عقود الإيجار';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('unit.owners', fn (Builder $q) => $q->whereKey(Portal::owner()->getKey()))
            ->with(['unit', 'clients']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            Parts::header(Heroicon::OutlinedDocumentText, fn (RentContract $r) => [
                'kicker' => __('عقد إيجار').' · '.$r->number,
                'title' => $r->unit?->title ?? '-',
                'sub' => [$r->clients->pluck('full_name')->implode('، '), $r->start_date?->format('Y-m-d').' ← '.$r->end_date?->format('Y-m-d')],
                'facts' => [
                    Parts::fact('الحالة', $r->status?->getLabel(), $r->status === ContractStatus::Active ? 'success' : null),
                    Parts::fact('الإيجار السنوي', Parts::amount($r->rent_amount)),
                    Parts::fact('الدفعة القادمة', $r->next_payment_date?->format('Y-m-d') ?? '-', $r->next_payment_date?->isPast() ? 'danger' : 'info'),
                ],
            ]),
            Section::make(__('المستأجرون'))->icon(Heroicon::OutlinedUsers)->schema([
                TextEntry::make('clients.full_name')->hiddenLabel()->badge()->color('info'),
            ]),
            Section::make(__('المدة والسداد'))->icon(Heroicon::OutlinedCalendarDays)->columns(2)->schema([
                Parts::date('start_date', 'بداية العقد'),
                Parts::date('end_date', 'نهاية العقد'),
                TextEntry::make('payment_cycle')->label(__('دورة السداد'))->badge()->color('gray'),
                Parts::money('rent_amount', 'الإيجار السنوي')->weight('bold'),
                Parts::date('next_payment_date', 'الدفعة القادمة'),
                Parts::money('next_payment_amount', 'مبلغها'),
                ...Parts::ejar(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('start_date', 'desc')
            ->columns([
                TextColumn::make('unit.title')->label(__('الوحدة'))->weight('bold')->searchable()
                    ->description(fn (RentContract $r) => __('عقد :n', ['n' => $r->number])),
                TextColumn::make('clients.full_name')->label(__('المستأجرون'))->badge()->color('info')->searchable(),
                TextColumn::make('start_date')->label(__('من'))->date('Y-m-d'),
                TextColumn::make('end_date')->label(__('إلى'))->date('Y-m-d')
                    ->color(fn (RentContract $r) => $r->status === ContractStatus::Active && $r->end_date?->diffInDays(today(), false) > -60 ? 'warning' : null),
                TextColumn::make('rent_amount')->label(__('الإيجار السنوي'))->numeric(2)->weight('bold'),
                TextColumn::make('status')->label(__('الحالة'))->badge(),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('print')
                    ->label(__('طباعة'))
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedPrinter)
                    ->url(fn ($record) => route('documents.print', ['type' => 'rent_contract', 'id' => $record->getKey()]))
                    ->openUrlInNewTab(),
ViewAction::make()->label(__('التفاصيل'))->modalWidth('5xl')]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRentContracts::route('/')];
    }
}
