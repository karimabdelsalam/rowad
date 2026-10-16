<?php

namespace App\Filament\Platform\Resources\Plans;

use App\Filament\Platform\Resources\Plans\Pages\CreatePlan;
use App\Filament\Platform\Resources\Plans\Pages\EditPlan;
use App\Filament\Platform\Resources\Plans\Pages\ListPlans;
use App\Filament\Platform\Resources\Plans\Schemas\PlanForm;
use App\Filament\Platform\Resources\Plans\Tables\PlansTable;
use App\Models\Plan;
use BackedEnum;
use App\Filament\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|\UnitEnum|null $navigationGroup = \App\Filament\Platform\Nav::Billing;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'باقة';

    protected static ?string $pluralModelLabel = 'الباقات';

    public static function form(Schema $schema): Schema
    {
        return PlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlansTable::configure($table);
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
            'index' => ListPlans::route('/'),
            'create' => CreatePlan::route('/create'),
            'edit' => EditPlan::route('/{record}/edit'),
        ];
    }
}
