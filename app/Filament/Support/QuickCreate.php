<?php

namespace App\Filament\Support;

use App\Enums\ClientKind;
use App\Enums\IdType;
use App\Models\Client;
use App\Models\Owner;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/**
 * "+" next to every owner / client picker: add the person in a small window without leaving the form,
 * and the new person is selected straight away. Only the essentials are asked; the rest is completed
 * later from the person's own page. Shown only to users allowed to add owners / clients.
 */
final class QuickCreate
{
    public static function owner(Select $select): Select
    {
        return $select
            ->createOptionForm(fn () => self::personFields())
            ->createOptionUsing(fn (array $data) => Owner::create(self::clean($data))->getKey())
            ->createOptionAction(fn (Action $action) => self::style($action, __('إضافة مالك جديد'), 'owners.create'));
    }

    public static function client(Select $select): Select
    {
        return $select
            ->createOptionForm(fn () => self::clientFields())
            ->createOptionUsing(fn (array $data) => Client::create(self::clean($data))->getKey())
            ->createOptionAction(fn (Action $action) => self::style($action, __('إضافة عميل جديد'), 'clients.create'));
    }

    /** A voucher's party: "+" adds an owner or a client, following the party type chosen beside it. */
    public static function party(Select $select, string $typeField = 'party_type'): Select
    {
        return $select
            ->createOptionForm(fn (Get $get) => $get($typeField) === 'client' ? self::clientFields() : self::personFields())
            ->createOptionUsing(fn (array $data, Get $get) => ($get($typeField) === 'client'
                ? Client::create(self::clean($data)) : Owner::create(self::clean($data)))->getKey())
            ->createOptionAction(fn (Action $action, Get $get) => self::style(
                $action->modalHeading(fn () => $get($typeField) === 'client' ? __('إضافة عميل جديد') : __('إضافة مالك جديد')),
                __('إضافة جديد'),
                fn () => $get($typeField) === 'client' ? 'clients.create' : 'owners.create',
                fn () => in_array($get($typeField), ['owner', 'client'], true),
            ));
    }

    /** @return list<\Filament\Schemas\Components\Component> */
    private static function clientFields(): array
    {
        return [
            ToggleButtons::make('kind')->label(__('نوع العميل'))->options(ClientKind::class)->default(ClientKind::Individual->value)
                ->inline()->grouped()->required()->live(),
            TextInput::make('company_name')->label(__('اسم المنشأة'))->maxLength(190)->required(fn (Get $get) => self::company($get))
                ->visible(fn (Get $get) => self::company($get)),
            TextInput::make('company_registration_number')->label(__('رقم السجل التجاري'))->maxLength(50)
                ->visible(fn (Get $get) => self::company($get)),
            ...self::personFields(),
        ];
    }

    /** @return list<\Filament\Schemas\Components\Component> */
    private static function personFields(): array
    {
        return [
            Grid::make(['default' => 2, 'md' => 4])->schema([
                TextInput::make('first_name')->label(__('الاسم الأول'))->required()->maxLength(190)->autofocus(),
                TextInput::make('father_name')->label(__('اسم الأب'))->maxLength(190),
                TextInput::make('grandfather_name')->label(__('اسم الجد'))->maxLength(190),
                TextInput::make('family_name')->label(__('العائلة'))->maxLength(190),
            ]),
            Grid::make(['default' => 1, 'md' => 3])->schema([
                TextInput::make('mobile')->label(__('الجوال'))->tel()->maxLength(30),
                Select::make('id_type')->label(__('نوع الهوية'))->options(IdType::class)->default(IdType::NationalId->value),
                TextInput::make('id_number')->label(__('رقم الهوية'))->maxLength(50),
                TextInput::make('nationality')->label(__('الجنسية'))->maxLength(100),
                DatePicker::make('birth_date')->label(__('تاريخ الميلاد'))->maxDate(today()),
            ]),
        ];
    }

    private static function style(Action $action, string $heading, string|\Closure $permission, ?\Closure $when = null): Action
    {
        return $action->icon(Heroicon::OutlinedUserPlus)->tooltip($heading)
            ->when($when === null, fn (Action $action) => $action->modalHeading($heading))->modalWidth('3xl')
            ->modalDescription(__('البيانات الأساسية فقط؛ تكمل الباقي لاحقًا من صفحته.'))
            ->modalSubmitActionLabel(__('إضافة واختيار'))
            ->visible(function () use ($permission, $when) {
                $user = auth()->user();
                $permission = $permission instanceof \Closure ? $permission() : $permission;

                return $user !== null && ($when === null || $when()) && ($user->is_super_admin || $user->can($permission));
            });
    }

    private static function company(Get $get): bool
    {
        $kind = $get('kind');

        return ($kind instanceof ClientKind ? $kind : ClientKind::tryFrom((string) $kind)) === ClientKind::Company;
    }

    /** @param array<string, mixed> $data */
    private static function clean(array $data): array
    {
        return array_filter($data, fn ($value) => $value !== null && $value !== '');
    }
}
