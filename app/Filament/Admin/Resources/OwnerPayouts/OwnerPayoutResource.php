<?php

namespace App\Filament\Admin\Resources\OwnerPayouts;

use App\Enums\OwnerPayoutStatus;
use App\Filament\Admin\Resources\OwnerPayouts\Pages\ListOwnerPayouts;
use App\Filament\Admin\Resources\OwnerPayouts\Pages\PrepareOwnerPayout;
use App\Filament\Admin\Resources\OwnerPayouts\Pages\ViewOwnerPayout;
use App\Filament\Admin\Resources\OwnerPayouts\RelationManagers\ItemsRelationManager;
use App\Filament\Infolists\Parts;
use App\Filament\Resource;
use App\Models\OwnerPayout;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Payout runs: many owners' net dues paid by bank transfer in one go. */
class OwnerPayoutResource extends Resource
{
    protected static ?string $model = OwnerPayout::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|\UnitEnum|null $navigationGroup = 'المالية';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'دفعة توزيع';

    protected static ?string $pluralModelLabel = 'توزيع مستحقات الملاك';

    protected static ?string $permission = 'owner_payouts';

    protected static ?string $recordTitleAttribute = 'number';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Parts::header(Heroicon::OutlinedBuildingLibrary, fn (OwnerPayout $r) => [
                'kicker' => __('دفعة توزيع').' · '.$r->status->getLabel(),
                'title' => $r->number,
                'sub' => [
                    __('مستحقات حتى :d', ['d' => $r->dues_until->format('Y-m-d')]),
                    $r->bankAccount ? __('من حساب :a', ['a' => $r->bankAccount->holder_name]) : null,
                    $r->transferred_at ? __('حُوّلت :d', ['d' => $r->transferred_at->format('Y-m-d')]) : null,
                    $r->cancelled_at ? __('أُلغيت :d', ['d' => $r->cancelled_at->format('Y-m-d')]) : null,
                ],
                'facts' => [
                    Parts::fact('الملاك', $r->owners_count),
                    Parts::fact('المستحقات', Parts::amount($r->items()->sum('dues'))),
                    Parts::fact('الخصومات', Parts::amount($r->items()->sum('deductions')), 'danger'),
                    Parts::fact('صافي التحويل', Parts::amount($r->total), 'success'),
                ],
            ]),
            Section::make(__('ملاحظات'))->icon(Heroicon::OutlinedPencilSquare)->visible(fn (OwnerPayout $r) => filled($r->notes))->schema([
                TextEntry::make('notes')->hiddenLabel()->prose(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('bankAccount'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')->label(__('الرقم'))->weight('bold')->searchable()
                    ->description(fn (OwnerPayout $r) => $r->issued_at->format('Y-m-d')),
                TextColumn::make('dues_until')->label(__('مستحقات حتى'))->date('Y-m-d'),
                TextColumn::make('owners_count')->label(__('الملاك'))->numeric(),
                TextColumn::make('total')->label(__('صافي التحويل'))->numeric(2)->weight('bold')->color('success'),
                TextColumn::make('bankAccount.holder_name')->label(__('من حساب'))->placeholder('-')->toggleable(),
                TextColumn::make('status')->label(__('الحالة'))->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('الحالة'))->options(OwnerPayoutStatus::class),
            ])
            ->recordActions([ViewAction::make()->label(__('فتح'))]);
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOwnerPayouts::route('/'),
            'prepare' => PrepareOwnerPayout::route('/prepare'),
            'view' => ViewOwnerPayout::route('/{record}'),
        ];
    }
}
