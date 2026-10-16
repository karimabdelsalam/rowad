<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use App\Enums\UserType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options(UserType::class)
                    ->default('staff')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('username'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('mobile'),
                Select::make('branch_id')->label(__('الفرع'))
                    ->options(fn () => \App\Support\Branches::options())
                    ->placeholder(__('كل الفروع'))
                    ->helperText(__('اتركه فارغًا ليرى المستخدم كل الفروع.'))
                    ->visible(fn () => \App\Support\Branches::inUse() && \App\Support\Branches::current() === null),
                TextInput::make('national_id'),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required(),
                TextInput::make('locale')
                    ->required()
                    ->default('ar'),
                Toggle::make('is_active')
                    ->required(),
                Toggle::make('is_super_admin')
                    ->required(),
                Toggle::make('sms_login_enabled')
                    ->required(),
                DateTimePicker::make('last_login_at'),
            ]);
    }
}
