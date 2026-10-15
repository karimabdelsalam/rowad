<?php

namespace App\Filament\Admin\Resources\Owners\Schemas;

use App\Enums\IdType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Schema;

class OwnerForm
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
                Textarea::make('notes')
                    ->columnSpanFull(),
                SpatieMediaLibraryFileUpload::make('attachments')->label(__('المرفقات'))->collection('attachments')->multiple()->downloadable()->columnSpanFull(),
            ]);
    }
}
