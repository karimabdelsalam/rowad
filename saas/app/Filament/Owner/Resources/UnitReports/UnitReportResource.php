<?php

namespace App\Filament\Owner\Resources\UnitReports;

use App\Filament\Owner\Resources\UnitReports\Pages\ManageUnitReports;
use App\Filament\PortalResource;
use App\Models\UnitReport;
use App\Support\Portal;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Legacy ownerarea units/reports: condition reports the office attached to the owner's units. */
class UnitReportResource extends PortalResource
{
    protected static ?string $model = UnitReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'تقرير';

    protected static ?string $pluralModelLabel = 'تقارير عقاراتي';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('unit.owners', fn (Builder $q) => $q->whereKey(Portal::owner()->getKey()))
            ->with(['unit', 'category', 'media']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('issued_at', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('العنوان'))->searchable(),
                TextColumn::make('unit.title')->label(__('الوحدة')),
                TextColumn::make('category.title')->label(__('التصنيف')),
                TextColumn::make('issued_at')->label(__('تاريخ التقرير'))->date('Y-m-d'),
            ])
            ->filters([
                SelectFilter::make('unit_id')->label(__('الوحدة'))
                    ->options(fn () => Portal::ownerUnits(Portal::owner())->pluck('title', 'id')),
                SelectFilter::make('unit_report_category_id')->label(__('التصنيف'))->relationship('category', 'title'),
            ])
            ->recordActions([
                Action::make('download')->label(__('تنزيل'))->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn (UnitReport $record) => $record->getFirstMedia() !== null)
                    ->url(fn (UnitReport $record) => route('site.media', $record->getFirstMedia()), shouldOpenInNewTab: true),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUnitReports::route('/')];
    }
}
