<?php

namespace App\Filament\Concerns;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\SupportTicket;
use App\Services\DocumentNumberGenerator;
use App\Support\Settings;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Shared ticket UI for owner and client portals (legacy units/support, ccp/support). */
trait PortalSupportTickets
{
    /** @return array<int, string> unit id => title the portal user may reference */
    abstract protected static function ticketUnits(): array;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Select::make('type')->options(TicketType::class)->required()->default('request'),
            Select::make('unit_id')->label(__('الوحدة'))->options(fn () => static::ticketUnits()),
            TextInput::make('subject')->required()->maxLength(150),
            Textarea::make('message')->required()->rows(5)->maxLength(5000),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('code'),
            TextEntry::make('status')->badge(),
            TextEntry::make('subject'),
            TextEntry::make('message'),
            RepeatableEntry::make('replies')->label(__('الردود'))->schema([
                TextEntry::make('message')->hiddenLabel(),
                TextEntry::make('created_at')->hiddenLabel()->dateTime('Y-m-d H:i'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code'),
                TextColumn::make('type')->badge(),
                TextColumn::make('subject')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime('Y-m-d'),
            ])
            ->recordActions([ViewAction::make()]);
    }

    /** Fill server-side fields; never trust the portal user for ownership. */
    public static function prepareTicket(array $data, array $owner): array
    {
        if (filled($data['unit_id'] ?? null) && ! array_key_exists((int) $data['unit_id'], static::ticketUnits())) {
            $data['unit_id'] = null;
        }

        return $owner + $data + [
            'code' => Settings::get('support_ticket_prefix', 'TCK-').app(DocumentNumberGenerator::class)->sequence('support_ticket'),
            'status' => TicketStatus::Open,
        ];
    }
}
