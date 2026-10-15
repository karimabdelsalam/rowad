<?php

namespace App\Filament\Admin\Resources\Employees\Schemas;

use App\Models\Employee;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class EmployeeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.name')
                    ->label('User')
                    ->placeholder('-'),
                TextEntry::make('first_name'),
                TextEntry::make('father_name')
                    ->placeholder('-'),
                TextEntry::make('grandfather_name')
                    ->placeholder('-'),
                TextEntry::make('family_name')
                    ->placeholder('-'),
                TextEntry::make('full_name'),
                TextEntry::make('id_type')
                    ->badge()
                    ->placeholder('-'),
                TextEntry::make('id_number')
                    ->placeholder('-'),
                TextEntry::make('id_source')
                    ->placeholder('-'),
                TextEntry::make('id_issue_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('nationality')
                    ->placeholder('-'),
                TextEntry::make('mobile')
                    ->placeholder('-'),
                TextEntry::make('email')
                    ->label('Email address')
                    ->placeholder('-'),
                TextEntry::make('home_phone')
                    ->placeholder('-'),
                TextEntry::make('work')
                    ->placeholder('-'),
                TextEntry::make('work_phone')
                    ->placeholder('-'),
                TextEntry::make('address')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('id_expiry_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('mobile2')
                    ->placeholder('-'),
                TextEntry::make('address2')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('education')
                    ->placeholder('-'),
                TextEntry::make('marital_status')
                    ->placeholder('-'),
                TextEntry::make('salary')
                    ->numeric(),
                TextEntry::make('rent_commission')
                    ->numeric(),
                TextEntry::make('sale_commission')
                    ->numeric(),
                TextEntry::make('start_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('calendar')
                    ->badge(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Employee $record): bool => $record->trashed()),
            ]);
    }
}
