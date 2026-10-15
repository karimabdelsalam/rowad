<?php

namespace App\Filament\Owner\Resources\Installments;

use App\Filament\Owner\Resources\Installments\Pages\ManageInstallments;
use App\Filament\PortalResource;
use App\Models\Installment;
use App\Support\Portal;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Rent/installment collection status on the owner's units (legacy units/payments). */
class InstallmentResource extends PortalResource
{
    protected static ?string $model = Installment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'قسط';

    protected static ?string $pluralModelLabel = 'التحصيلات';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('unit.owners', fn (Builder $q) => $q->whereKey(Portal::owner()->getKey()))
            ->with('unit');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_date')
            ->columns([
                TextColumn::make('due_date')->date('Y-m-d')->sortable(),
                TextColumn::make('unit.title'),
                TextColumn::make('type')->badge(),
                TextColumn::make('amount')->numeric(2),
                TextColumn::make('paid_amount')->state(fn (\App\Models\Installment $record) => $record->paidTotal())->numeric(2),
                TextColumn::make('remaining')->label(__('المتبقي'))->state(fn (\App\Models\Installment $record) => $record->remainingAmount())->numeric(2),
                IconColumn::make('is_collected')->boolean(),
            ])
            // legacy owner "my payments": filter by property and by tenant
            ->filters([
                TernaryFilter::make('is_collected'),
                \Filament\Tables\Filters\SelectFilter::make('unit_id')->label(__('الوحدة'))
                    ->options(fn () => \App\Models\Unit::whereHas('owners', fn (Builder $q) => $q->whereKey(Portal::owner()->getKey()))->orderBy('title')->pluck('title', 'id')->all()),
                \Filament\Tables\Filters\SelectFilter::make('building')->label(__('العقار'))
                    ->options(fn () => \App\Models\Building::whereHas('units.owners', fn (Builder $q) => $q->whereKey(Portal::owner()->getKey()))->orderBy('title')->pluck('title', 'id')->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? $query->whereHas('unit', fn (Builder $q) => $q->where('building_id', $data['value'])) : $query),
                \Filament\Tables\Filters\Filter::make('tenant')->label(__('المستأجر'))
                    ->schema([\Filament\Forms\Components\TextInput::make('name')->label(__('اسم المستأجر'))])
                    ->query(fn (Builder $query, array $data) => filled($term = trim((string) ($data['name'] ?? '')))
                        ? $query->whereHasMorph('contract', [\App\Models\RentContract::class, \App\Models\SaleContract::class],
                            fn (Builder $q) => $q->whereHas('clients', fn (Builder $q) => $q->where('full_name', 'like', "%{$term}%")))
                        : $query)
                    ->indicateUsing(fn (array $data) => filled($data['name'] ?? null) ? __('المستأجر').': '.$data['name'] : null),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageInstallments::route('/')];
    }
}
