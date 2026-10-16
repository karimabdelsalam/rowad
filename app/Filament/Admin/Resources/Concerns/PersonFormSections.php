<?php

namespace App\Filament\Admin\Resources\Concerns;

use App\Enums\IdType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

/** The person sections shared by owners, clients and employees (legacy four-part Arabic name, ID, contact). */
class PersonFormSections
{
    /** full_name is composed from the four parts when saving (HasPersonName). */
    public static function name(): Section
    {
        return Section::make(__('الاسم'))->icon(Heroicon::OutlinedIdentification)
            ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
            ->schema([
                TextInput::make('first_name')->required()->maxLength(190),
                TextInput::make('father_name')->maxLength(190),
                TextInput::make('grandfather_name')->maxLength(190),
                TextInput::make('family_name')->maxLength(190),
            ]);
    }

    /** @param  list<\Filament\Schemas\Components\Component>  $extra */
    public static function identity(array $extra = [], bool $readFromPhoto = false): Section
    {
        return Section::make(__('الهوية'))->icon(Heroicon::OutlinedCreditCard)
            ->key('identity')
            ->afterHeader($readFromPhoto ? [ReadDocumentAction::make('id')] : [])
            ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
            ->schema([
                Select::make('id_type')->options(IdType::class),
                TextInput::make('id_number')->maxLength(50),
                TextInput::make('nationality')->maxLength(100),
                TextInput::make('id_source')->maxLength(190),
                DatePicker::make('id_issue_date'),
                DatePicker::make('birth_date')->label(__('تاريخ الميلاد'))->maxDate(today()),
                ...$extra,
            ]);
    }

    /** @param  list<\Filament\Schemas\Components\Component>  $extra */
    public static function contact(array $extra = []): Section
    {
        return Section::make(__('التواصل'))->icon(Heroicon::OutlinedPhone)
            ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
            ->schema([
                TextInput::make('mobile')->tel()->maxLength(30),
                TextInput::make('email')->label(__('البريد الإلكتروني'))->email()->maxLength(190),
                TextInput::make('home_phone')->tel()->maxLength(30),
                TextInput::make('work')->maxLength(190),
                TextInput::make('work_phone')->tel()->maxLength(30),
                Textarea::make('address')->rows(2)->columnSpanFull(),
                ...$extra,
            ]);
    }

    public static function notesAndFiles(bool $notes = true): Section
    {
        return Section::make(__('ملاحظات ومرفقات'))->icon(Heroicon::OutlinedPaperClip)->collapsible()
            ->schema(array_values(array_filter([
                $notes ? Textarea::make('notes')->rows(3) : null,
                SpatieMediaLibraryFileUpload::make('attachments')->label(__('المرفقات'))->collection('attachments')->multiple()->downloadable(),
            ])));
    }

    /** Linking a login is normally done with the "portal account" action; kept for manual fixes. */
    public static function account(): Section
    {
        return Section::make(__('حساب الدخول'))->icon(Heroicon::OutlinedKey)->collapsed()
            ->schema([Select::make('user_id')->label(__('المستخدم'))->relationship('user', 'name')->searchable()->preload()]);
    }
}
