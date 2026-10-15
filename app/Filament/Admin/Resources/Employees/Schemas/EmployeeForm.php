<?php

namespace App\Filament\Admin\Resources\Employees\Schemas;

use App\Enums\Calendar;
use App\Filament\Admin\Resources\Concerns\PersonFormSections;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                PersonFormSections::name(),
                PersonFormSections::identity([
                    DatePicker::make('id_expiry_date')->helperText(__('يصل تنبيه قبل انتهاء الهوية بـ 15 يومًا')),
                ]),
                PersonFormSections::contact([
                    TextInput::make('mobile2')->tel()->maxLength(30),
                    Textarea::make('address2')->rows(2)->columnSpanFull(),
                ]),
                Section::make(__('الوظيفة والعمولات'))->icon(Heroicon::OutlinedBriefcase)->columns(['default' => 1, 'sm' => 2, 'lg' => 3])->schema([
                    DatePicker::make('start_date'),
                    TextInput::make('salary')->required()->numeric()->minValue(0)->default(0),
                    Select::make('calendar')->options(Calendar::class)->default('gregorian')->required(),
                    TextInput::make('rent_commission')->required()->numeric()->minValue(0)->default(0),
                    TextInput::make('sale_commission')->required()->numeric()->minValue(0)->default(0),
                    TextInput::make('education')->maxLength(190),
                    TextInput::make('marital_status')->maxLength(50),
                ]),
                PersonFormSections::notesAndFiles(notes: false),
                PersonFormSections::account(),
            ]);
    }
}
