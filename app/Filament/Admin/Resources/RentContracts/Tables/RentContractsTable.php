<?php

namespace App\Filament\Admin\Resources\RentContracts\Tables;

use App\Enums\ContractStatus;
use App\Enums\PaymentCycle;
use App\Exceptions\BusinessRuleException;
use App\Models\RentContract;
use App\Services\Contracts\ContractService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Legacy rent index/archive screens. */
class RentContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['unit', 'clients']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('unit.title')->searchable()->sortable(),
                TextColumn::make('clients.full_name')->label(__('المستأجرون'))->listWithLineBreaks()->searchable(),
                TextColumn::make('start_date')->date('Y-m-d')->sortable(),
                TextColumn::make('end_date')->date('Y-m-d')->sortable(),
                TextColumn::make('payment_cycle')->badge(),
                TextColumn::make('rent_amount')->numeric(2)->sortable(),
                TextColumn::make('next_payment_date')->date('Y-m-d')->sortable(),
                TextColumn::make('next_payment_amount')->numeric(2)->toggleable(),
                TextColumn::make('status')->badge()->color(fn (ContractStatus $state) => match ($state) {
                    ContractStatus::Active => 'success',
                    ContractStatus::Ended => 'gray',
                    default => 'danger',
                }),
                TextColumn::make('created_at')->dateTime('Y-m-d')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(ContractStatus::class),
                SelectFilter::make('payment_cycle')->options(PaymentCycle::class),
                TernaryFilter::make('archived')
                    ->label(__('الأرشيف'))
                    ->placeholder(__('العقود الحالية'))
                    ->trueLabel(__('المؤرشفة فقط'))
                    ->falseLabel(__('الكل'))
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('archived_at'),
                        false: fn (Builder $q) => $q,
                        blank: fn (Builder $q) => $q->whereNull('archived_at'),
                    ),
                SelectFilter::make('expiring')
                    ->label(__('ينتهي خلال'))
                    ->options(['30' => __('30 يوم'), '60' => __('60 يوم'), '90' => __('90 يوم')])
                    ->query(fn (Builder $query, array $data) => filled($data['value'])
                        ? $query->where('status', ContractStatus::Active)->whereBetween('end_date', [today(), today()->addDays((int) $data['value'])])
                        : $query),
                TrashedFilter::make(),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('print')
                    ->label(__('طباعة'))
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedPrinter)
                    ->url(fn ($record) => route('documents.print', ['type' => 'rent_contract', 'id' => $record->getKey()]))
                    ->openUrlInNewTab(),
                ViewAction::make(),
                EditAction::make()->visible(fn (RentContract $record) => $record->status === ContractStatus::Active),
                ActionGroup::make([
                    Action::make('end')
                        ->label(__('إنهاء العقد'))
                        ->icon(Heroicon::OutlinedFlag)
                        ->requiresConfirmation()
                        ->visible(fn (RentContract $record) => $record->status === ContractStatus::Active && self::allowed('end'))
                        ->action(fn (RentContract $record) => self::run(fn () => app(ContractService::class)->end($record), __('تم إنهاء العقد'))),
                    Action::make('cancel')
                        ->label(__('إلغاء العقد'))
                        ->icon(Heroicon::OutlinedXCircle)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription(__('سيتم حذف جميع الأقساط غير المحصلة لهذا العقد.'))
                        ->visible(fn (RentContract $record) => $record->status === ContractStatus::Active && self::allowed('cancel'))
                        ->action(fn (RentContract $record) => self::run(fn () => app(ContractService::class)->cancel($record), __('تم إلغاء العقد'))),
                    Action::make('archive')
                        ->label(fn (RentContract $record) => $record->archived_at ? __('إلغاء الأرشفة') : __('أرشفة'))
                        ->icon(Heroicon::OutlinedArchiveBox)
                        ->visible(fn () => self::allowed('archive'))
                        ->action(fn (RentContract $record) => self::run(
                            fn () => app(ContractService::class)->archive($record, $record->archived_at === null),
                            __('تم التحديث'),
                        )),
                ]),
            ]);
    }

    private static function allowed(string $ability): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can("rent_contracts.{$ability}"));
    }

    private static function run(callable $callback, string $success): void
    {
        try {
            $callback();
            Notification::make()->title($success)->success()->send();
        } catch (BusinessRuleException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }
}
