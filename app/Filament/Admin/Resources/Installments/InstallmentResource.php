<?php

namespace App\Filament\Admin\Resources\Installments;

use App\Filament\Admin\Resources\Installments\Pages\ListInstallments;
use App\Filament\Admin\Resources\Installments\Pages\ViewInstallment;
use App\Filament\Admin\Resources\Installments\Schemas\InstallmentForm;
use App\Filament\Admin\Resources\Installments\Schemas\InstallmentInfolist;
use App\Filament\Admin\Resources\Installments\Tables\InstallmentsTable;
use App\Models\Installment;
use BackedEnum;
use App\Filament\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InstallmentResource extends Resource
{
    protected static ?string $model = Installment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'المالية';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'قسط';

    protected static ?string $pluralModelLabel = 'الأقساط والمستحقات';

    protected static ?string $permission = 'installments';

    public static function form(Schema $schema): Schema
    {
        return InstallmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InstallmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InstallmentsTable::configure($table);
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
            'index' => ListInstallments::route('/'),
            'view' => ViewInstallment::route('/{record}'),
        ];
    }
}
