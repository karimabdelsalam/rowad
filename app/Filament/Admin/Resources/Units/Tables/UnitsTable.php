<?php

namespace App\Filament\Admin\Resources\Units\Tables;

use App\Enums\OfferType;
use App\Enums\UnitStatus;
use App\Models\Unit;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Legacy building index: search by owner / tenant / buyer, activate-deactivate, all details on demand. */
class UnitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['building', 'category', 'owners', 'city', 'branch']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('branch.name')->label(__('الفرع'))->badge()->color('gray')->placeholder('-')->toggleable()
                    ->visible(fn () => \App\Support\Branches::inUse() && \App\Support\Branches::current() === null),
                TextColumn::make('title')->label(__('الوحدة'))->weight('bold')->searchable()->sortable()
                    ->description(fn ($record) => $record->building?->title ?? $record->city?->name),
                TextColumn::make('building.title')->label(__('العقار'))->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('number')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('category.title')->label(__('التصنيف'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('offer_type')->badge(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('owners.full_name')->label(__('الملاك'))->badge()->color('info')->limitList(2)->expandableLimitedList(),
                TextColumn::make('rent_amount')->numeric(2)->sortable()->toggleable(),
                TextColumn::make('rooms')->numeric()->sortable()->toggleable(),
                TextColumn::make('area')->numeric(2)->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city.name')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->boolean()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_published')->label(__('على الموقع'))->boolean()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('floor')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('district.name')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('street')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deed_number')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('plan_number')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('plot_number')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rent_payment_cycle')->badge()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('management_fee')->numeric(2)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('management_fee_next_date')->date('Y-m-d')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime('Y-m-d')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('branch_id')->label(__('الفرع'))
                    ->options(fn () => \App\Support\Branches::options())
                    ->visible(fn () => \App\Support\Branches::inUse() && \App\Support\Branches::current() === null),
                SelectFilter::make('status')->options(UnitStatus::class),
                SelectFilter::make('offer_type')->options(OfferType::class),
                SelectFilter::make('building_id')->label(__('العقار'))->relationship('building', 'title')->searchable()->preload(),
                SelectFilter::make('property_category_id')->label(__('التصنيف'))->relationship('category', 'title'),
                SelectFilter::make('city_id')->label(__('المدينة'))->relationship('city', 'name')->searchable(),
                SelectFilter::make('owner')->label(__('المالك'))
                    ->relationship('owners', 'full_name')->searchable(),
                Filter::make('party')
                    ->label(__('المستأجر / المشتري'))
                    ->schema([TextInput::make('name')->label(__('اسم أو جوال المستأجر / المشتري'))])
                    ->query(function (Builder $query, array $data) {
                        $term = trim((string) ($data['name'] ?? ''));
                        if ($term === '') {
                            return $query;
                        }
                        $match = fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('full_name', 'like', "%{$term}%")->orWhere('mobile', 'like', "%{$term}%"));

                        return $query->where(fn (Builder $q) => $q
                            ->whereHas('rentContracts.clients', $match)
                            ->orWhereHas('saleContracts.clients', $match));
                    })
                    ->indicateUsing(fn (array $data) => filled($data['name'] ?? null) ? __('المستأجر / المشتري').': '.$data['name'] : null),
                TernaryFilter::make('is_active'),
                TernaryFilter::make('is_published')->label(__('على الموقع')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                \Filament\Actions\ActionGroup::make([
                EditAction::make(),
                Action::make('print')->label(__('طباعة البطاقة'))->icon(Heroicon::OutlinedPrinter)
                    ->url(fn (Unit $record) => route('documents.print', ['type' => 'unit', 'id' => $record->getKey()]), true),
                \App\Filament\Support\DocumentActions::pdf('unit')->label(__('البطاقة PDF')),
                Action::make('toggleActive')
                    ->label(fn (Unit $record) => $record->is_active ? __('تعطيل') : __('تفعيل'))
                    ->icon(fn (Unit $record) => $record->is_active ? Heroicon::OutlinedPause : Heroicon::OutlinedPlay)
                    ->color('gray')
                    ->visible(function () {
                        $user = auth()->user();

                        return $user !== null && ($user->is_super_admin || $user->can('units.activate'));
                    })
                    ->action(fn (Unit $record) => $record->update(['is_active' => ! $record->is_active])),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    \App\Filament\Admin\Resources\Units\EjarRegisterAction::bulk(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
