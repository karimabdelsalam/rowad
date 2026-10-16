<?php

namespace App\Filament\Admin\Resources\Owners\Schemas;

use App\Filament\Admin\Resources\Concerns\PersonFormSections;
use App\Models\Lookups\Bank;
use App\Support\Iban;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class OwnerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                PersonFormSections::name(),
                PersonFormSections::identity(readFromPhoto: true),
                PersonFormSections::contact(),
                self::bankAccount(),
                PersonFormSections::notesAndFiles(),
                PersonFormSections::account(),
            ]);
    }

    /** Where the owner's dues are transferred; the bank is recognised from the IBAN. */
    public static function bankAccount(): Section
    {
        return Section::make(__('الحساب البنكي للتحويل'))->icon(Heroicon::OutlinedBuildingLibrary)->columns(3)->collapsible()
            ->description(__('يُستخدم في دفعات توزيع المستحقات وملف التحويل البنكي.'))
            ->schema([
                TextInput::make('iban')->label(__('الآيبان'))->maxLength(34)->placeholder('SA00 0000 0000 0000 0000 0000')
                    ->extraInputAttributes(['dir' => 'ltr'])->columnSpan(2)
                    ->live(onBlur: true)
                    ->formatStateUsing(fn (?string $state) => filled($state) ? Iban::format($state) : $state)
                    ->dehydrateStateUsing(fn (?string $state) => Iban::normalize($state))
                    ->rule(fn () => function (string $attribute, mixed $value, Closure $fail) {
                        if (filled($value) && ! Iban::valid($value)) {
                            $fail(__('رقم الآيبان غير صحيح؛ تأكد من الأرقام (24 خانة تبدأ بـ SA)'));
                        }
                    })
                    ->afterStateUpdated(function (?string $state, Set $set) {
                        if (Iban::valid($state) && ($name = Iban::bank($state)[0] ?? null) !== null) {
                            $set('bank_id', Bank::query()->firstOrCreate(['title' => $name])->id);
                        }
                    }),
                Select::make('bank_id')->label(__('البنك'))->relationship('bank', 'title')->searchable()->preload(),
                TextInput::make('bank_beneficiary')->label(__('اسم صاحب الحساب'))->maxLength(190)->columnSpanFull()
                    ->helperText(__('اتركه فارغًا إذا كان الحساب باسم المالك نفسه.')),
            ]);
    }
}
