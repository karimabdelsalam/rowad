<?php

namespace App\Filament\Admin\Resources\PrintTemplates;

use App\Filament\Admin\Resources\PrintTemplates\Pages\CreatePrintTemplate;
use App\Filament\Admin\Resources\PrintTemplates\Pages\EditPrintTemplate;
use App\Filament\Admin\Resources\PrintTemplates\Pages\ListPrintTemplates;
use App\Filament\Admin\Resources\PrintTemplates\Pages\ViewPrintTemplate;
use App\Filament\Admin\Resources\PrintTemplates\Schemas\PrintTemplateForm;
use App\Filament\Admin\Resources\PrintTemplates\Schemas\PrintTemplateInfolist;
use App\Filament\Admin\Resources\PrintTemplates\Tables\PrintTemplatesTable;
use App\Models\PrintTemplate;
use BackedEnum;
use App\Filament\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PrintTemplateResource extends Resource
{
    protected static ?string $model = PrintTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;

    protected static string|\UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'نموذج طباعة';

    protected static ?string $pluralModelLabel = 'نماذج الطباعة';

    protected static ?string $permission = 'print_templates';

    public static function form(Schema $schema): Schema
    {
        return PrintTemplateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PrintTemplateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PrintTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrintTemplates::route('/'),
            'create' => CreatePrintTemplate::route('/create'),
            'view' => ViewPrintTemplate::route('/{record}'),
            'edit' => EditPrintTemplate::route('/{record}/edit'),
        ];
    }
}
