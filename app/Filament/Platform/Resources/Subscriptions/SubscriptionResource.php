<?php

namespace App\Filament\Platform\Resources\Subscriptions;

use App\Filament\Platform\Resources\Subscriptions\Pages\CreateSubscription;
use App\Filament\Platform\Resources\Subscriptions\Pages\EditSubscription;
use App\Filament\Platform\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Filament\Platform\Resources\Subscriptions\Schemas\SubscriptionForm;
use App\Filament\Platform\Resources\Subscriptions\Tables\SubscriptionsTable;
use App\Models\Subscription;
use BackedEnum;
use App\Filament\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|\UnitEnum|null $navigationGroup = \App\Filament\Platform\Nav::Offices;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'اشتراك';

    protected static ?string $pluralModelLabel = 'الاشتراكات';

    public static function form(Schema $schema): Schema
    {
        return SubscriptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubscriptionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptions::route('/'),
            'create' => CreateSubscription::route('/create'),
            'edit' => EditSubscription::route('/{record}/edit'),
        ];
    }
}
