<?php

namespace App\Filament\Admin\Resources\Clients\Schemas;

use App\Enums\Calendar;
use App\Enums\ClientKind;
use App\Filament\Admin\Resources\Concerns\PersonFormSections;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        $isCompany = fn (Get $get) => ($get('kind') instanceof ClientKind ? $get('kind') : ClientKind::tryFrom((string) $get('kind'))) === ClientKind::Company;

        return $schema
            ->columns(1)
            ->components([
                Section::make(__('نوع العميل'))->icon(Heroicon::OutlinedTag)->columns(['default' => 1, 'md' => 3])->schema([
                    Select::make('kind')->options(ClientKind::class)->default('individual')->required()->live(),
                    Select::make('client_category_id')->label(__('التصنيف'))->relationship('category', 'title')->preload(),
                    Select::make('calendar')->options(Calendar::class)->default('gregorian')->required(),
                ]),
                Section::make(__('بيانات المنشأة'))->icon(Heroicon::OutlinedBuildingOffice2)->columns(['default' => 1, 'md' => 2])
                    ->visible($isCompany)
                    ->schema([
                        TextInput::make('company_name')->maxLength(190),
                        TextInput::make('company_registration_number')->maxLength(50),
                        TextInput::make('company_mobile')->tel()->maxLength(30),
                        TextInput::make('vat_number')->maxLength(30),
                        Textarea::make('billing_info')->rows(2)->columnSpanFull(),
                        \Filament\Schemas\Components\Fieldset::make(__('العنوان الوطني (مطلوب للفاتورة الضريبية)'))->columns(['default' => 1, 'md' => 5])->columnSpanFull()->schema([
                            TextInput::make('national_address.street')->label(__('اسم الشارع'))->maxLength(127),
                            TextInput::make('national_address.building')->label(__('رقم المبنى'))->regex('/^\d{4}$/')->maxLength(4),
                            TextInput::make('national_address.district')->label(__('الحي'))->maxLength(127),
                            TextInput::make('national_address.city')->label(__('المدينة'))->maxLength(127),
                            TextInput::make('national_address.postal')->label(__('الرمز البريدي'))->regex('/^\d{5}$/')->maxLength(5),
                        ]),
                    ]),
                PersonFormSections::name()->heading(fn (Get $get) => $isCompany($get) ? __('اسم المفوض') : __('الاسم')),
                PersonFormSections::identity(),
                PersonFormSections::contact(),
                PersonFormSections::notesAndFiles(),
                PersonFormSections::account(),
            ]);
    }
}
