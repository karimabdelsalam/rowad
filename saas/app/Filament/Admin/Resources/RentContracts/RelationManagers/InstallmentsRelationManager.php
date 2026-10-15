<?php

namespace App\Filament\Admin\Resources\RentContracts\RelationManagers;

use App\Filament\Admin\Resources\Installments\Tables\InstallmentsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Contract payment schedule, with the same collect actions as the installments screen. */
class InstallmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'installments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('جدول الدفعات');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return InstallmentsTable::configure($table)->filters([])->defaultSort('due_date');
    }
}
