<?php

namespace App\Filament\Admin\Resources\Owners\Tables;

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

class OwnersTable
{
    use \App\Filament\Concerns\PortalAccountActions;

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')->label(__('الاسم'))->weight('bold')->searchable(['full_name', 'first_name', 'family_name'])->sortable()
                    ->description(fn ($record) => $record->id_type ? $record->id_type->getLabel().' '.$record->id_number : null),
                TextColumn::make('mobile')->label(__('الجوال'))->searchable()->icon(Heroicon::OutlinedPhone)->iconColor('gray')->extraAttributes(['dir' => 'ltr'])->placeholder('-'),
                TextColumn::make('email')->label(__('البريد الإلكتروني'))->searchable()->placeholder('-')->toggleable()->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('nationality')->label(__('الجنسية'))->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('units_count')->label(__('الوحدات'))->counts('units')->badge()->color('gray')->sortable()->alignCenter(),
                TextColumn::make('balance')->label(__('الرصيد المستحق له'))->numeric(2)->state(fn ($record) => $record->outstandingBalance())
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success')->weight('bold'),
                IconColumn::make('user_id')->label(__('بوابة'))->boolean()->trueIcon(Heroicon::OutlinedKey)->falseIcon(Heroicon::OutlinedMinus)->trueColor('success')->falseColor('gray')->alignCenter()->tooltip(__('حساب دخول للبوابة')),
                TextColumn::make('created_at')->label(__('أُضيف في'))->date('Y-m-d')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()->label(__('عرض')),
                \Filament\Actions\ActionGroup::make([
                    EditAction::make(),
                    \Filament\Actions\Action::make('print')
                        ->label(__('طباعة كشف الحساب'))
                        ->icon(\Filament\Support\Icons\Heroicon::OutlinedPrinter)
                        ->url(fn ($record) => route('documents.print', ['type' => 'owner_statement', 'id' => $record->getKey()]))
                        ->openUrlInNewTab(),
                    \App\Filament\Support\DocumentActions::pdf('owner_statement')->label(__('كشف الحساب PDF')),
                    self::portalAccountAction('owners'),
                    self::loginAsAction('owners', 'owner'),
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
