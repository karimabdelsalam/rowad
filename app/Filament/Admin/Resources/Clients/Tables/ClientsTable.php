<?php

namespace App\Filament\Admin\Resources\Clients\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Tables\Columns\IconColumn;
use Filament\Support\Icons\Heroicon;

class ClientsTable
{
    use \App\Filament\Concerns\PortalAccountActions;

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')->label(__('الاسم'))->weight('bold')->state(fn ($record) => $record->displayName())
                    ->searchable(query: fn ($query, $search) => $query->where(fn ($q) => $q->where('full_name', 'like', "%{$search}%")->orWhere('company_name', 'like', "%{$search}%")))
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('full_name', $direction))
                    ->description(fn ($record) => $record->kind?->value === 'company' && $record->company_name ? $record->full_name : ($record->id_type ? $record->id_type->getLabel().' '.$record->id_number : null)),
                TextColumn::make('kind')->label(__('النوع'))->badge()->color('gray'),
                TextColumn::make('category.title')->label(__('الفئة'))->badge()->color('info')->placeholder('-')->toggleable(),
                TextColumn::make('mobile')->label(__('الجوال'))->searchable()->icon(Heroicon::OutlinedPhone)->iconColor('gray')->extraAttributes(['dir' => 'ltr'])->placeholder('-'),
                TextColumn::make('email')->label(__('البريد الإلكتروني'))->searchable()->placeholder('-')->toggleable(isToggledHiddenByDefault: true)->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('rent_contracts_count')->label(__('إيجار'))->counts('rentContracts')->badge()->color('gray')->alignCenter()->toggleable(),
                TextColumn::make('sale_contracts_count')->label(__('بيع'))->counts('saleContracts')->badge()->color('gray')->alignCenter()->toggleable(),
                IconColumn::make('user_id')->label(__('بوابة'))->boolean()->trueIcon(Heroicon::OutlinedKey)->falseIcon(Heroicon::OutlinedMinus)->trueColor('success')->falseColor('gray')->alignCenter()->tooltip(__('حساب دخول للبوابة')),
                TextColumn::make('created_at')->label(__('أُضيف في'))->date('Y-m-d')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('kind')->label(__('النوع'))->options(\App\Enums\ClientKind::class),
                \Filament\Tables\Filters\SelectFilter::make('client_category_id')->label(__('الفئة'))->relationship('category', 'title'),
                \Filament\Tables\Filters\TernaryFilter::make('user_id')->label(__('لديه حساب بوابة'))->nullable(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()->label(__('عرض')),
                \Filament\Actions\ActionGroup::make([
                    EditAction::make(),
                    self::portalAccountAction('clients'),
                    self::loginAsAction('clients', 'client'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
