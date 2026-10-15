<?php

namespace App\Filament\Admin\Resources\Employees\Schemas;

use App\Enums\Calendar;
use App\Enums\IdType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name'),
                TextInput::make('first_name')
                    ->required(),
                TextInput::make('father_name'),
                TextInput::make('grandfather_name'),
                TextInput::make('family_name'),
                TextInput::make('full_name')
                    ->required(),
                Select::make('id_type')
                    ->options(IdType::class),
                TextInput::make('id_number'),
                TextInput::make('id_source'),
                DatePicker::make('id_issue_date'),
                TextInput::make('nationality'),
                TextInput::make('mobile'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('home_phone')
                    ->tel(),
                TextInput::make('work'),
                TextInput::make('work_phone')
                    ->tel(),
                Textarea::make('address')
                    ->columnSpanFull(),
                DatePicker::make('id_expiry_date'),
                TextInput::make('mobile2'),
                Textarea::make('address2')
                    ->columnSpanFull(),
                TextInput::make('education'),
                TextInput::make('marital_status'),
                TextInput::make('salary')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('rent_commission')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('sale_commission')
                    ->required()
                    ->numeric()
                    ->default(0),
                DatePicker::make('start_date'),
                Select::make('calendar')
                    ->options(Calendar::class)
                    ->default('gregorian')
                    ->required(),
                SpatieMediaLibraryFileUpload::make('attachments')->label(__('المرفقات'))->collection('attachments')->multiple()->downloadable()->columnSpanFull(),
            ]);
    }
}
