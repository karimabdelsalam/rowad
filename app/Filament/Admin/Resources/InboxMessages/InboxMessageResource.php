<?php

namespace App\Filament\Admin\Resources\InboxMessages;

use App\Filament\Admin\Resources\InboxMessages\Pages\ListInboxMessages;
use App\Filament\Admin\Resources\InboxMessages\Pages\ViewInboxMessage;
use App\Filament\Admin\Resources\InboxMessages\Schemas\InboxMessageForm;
use App\Filament\Admin\Resources\InboxMessages\Schemas\InboxMessageInfolist;
use App\Filament\Admin\Resources\InboxMessages\Tables\InboxMessagesTable;
use App\Models\InboxMessage;
use BackedEnum;
use App\Filament\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InboxMessageResource extends Resource
{
    protected static ?string $model = InboxMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|\UnitEnum|null $navigationGroup = 'التواصل';

    protected static ?int $navigationSort = 0;

    protected static ?string $modelLabel = 'رسالة واردة';

    protected static ?string $pluralModelLabel = 'صندوق الوارد';

    protected static ?string $permission = 'inbox_messages';

    public static function form(Schema $schema): Schema
    {
        return InboxMessageForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InboxMessageInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InboxMessagesTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = \App\Models\InboxMessage::whereNull('read_at')->count();

        return $count > 0 ? (string) $count : null;
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
            'index' => ListInboxMessages::route('/'),
            'view' => ViewInboxMessage::route('/{record}'),
        ];
    }
}
