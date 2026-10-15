<?php

namespace App\Filament\Admin\Resources\Clients\Schemas;

use App\Enums\Calendar;
use App\Enums\ClientKind;
use App\Enums\IdType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name'),
                TextInput::make('client_category_id')
                    ->numeric(),
                Select::make('kind')
                    ->options(ClientKind::class)
                    ->default('individual')
                    ->required(),
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
                TextInput::make('company_name'),
                TextInput::make('company_registration_number'),
                TextInput::make('company_mobile'),
                TextInput::make('vat_number'),
                Textarea::make('billing_info')
                    ->columnSpanFull(),
                Select::make('calendar')
                    ->options(Calendar::class)
                    ->default('gregorian')
                    ->required(),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
