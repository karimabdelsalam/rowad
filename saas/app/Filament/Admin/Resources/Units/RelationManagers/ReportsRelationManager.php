<?php

namespace App\Filament\Admin\Resources\Units\RelationManagers;

use App\Models\UnitReport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Legacy building/reports: condition reports per unit, by category, with the report file. Visible to the owners. */
class ReportsRelationManager extends RelationManager
{
    protected static string $relationship = 'reports';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('تقارير الوحدة');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label(__('العنوان'))->required()->maxLength(190),
            Select::make('unit_report_category_id')->label(__('التصنيف'))->relationship('category', 'title')->preload(),
            DatePicker::make('issued_at')->label(__('تاريخ التقرير'))->default(today()),
            SpatieMediaLibraryFileUpload::make('file')->label(__('ملف التقرير'))->columnSpanFull()->downloadable()->maxSize(20480),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('issued_at', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('العنوان'))->searchable(),
                TextColumn::make('category.title')->label(__('التصنيف')),
                TextColumn::make('issued_at')->label(__('تاريخ التقرير'))->date('Y-m-d')->sortable(),
            ])
            ->filters([SelectFilter::make('unit_report_category_id')->label(__('التصنيف'))->relationship('category', 'title')])
            ->headerActions([CreateAction::make()])
            ->recordActions([
                Action::make('download')->label(__('تنزيل'))->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn (UnitReport $record) => $record->getFirstMedia() !== null)
                    ->url(fn (UnitReport $record) => route('site.media', $record->getFirstMedia()), shouldOpenInNewTab: true),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
