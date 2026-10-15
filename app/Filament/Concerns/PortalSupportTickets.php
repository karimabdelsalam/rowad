<?php

namespace App\Filament\Concerns;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\SupportTicket;
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
        return $schema->columns(1)->components([
            \App\Filament\Infolists\Parts::header(\Filament\Support\Icons\Heroicon::OutlinedLifebuoy, fn (SupportTicket $r) => [
                'kicker' => $r->code.' · '.($r->type?->getLabel() ?? ''),
                'title' => $r->subject,
                'sub' => [$r->unit?->title, __('فُتحت :when', ['when' => $r->created_at?->locale(app()->getLocale())->diffForHumans()])],
                'facts' => [
                    \App\Filament\Infolists\Parts::fact('الحالة', $r->status?->getLabel(), match ($r->status) {
                        TicketStatus::Open => 'warning', TicketStatus::Answered => 'info', TicketStatus::Closed => 'success', default => null,
                    }),
                    \App\Filament\Infolists\Parts::fact('الردود', $r->replies->count()),
                ],
            ]),
            \Filament\Schemas\Components\Section::make(__('المحادثة'))->icon(\Filament\Support\Icons\Heroicon::OutlinedChatBubbleLeftRight)->schema([
                \Filament\Infolists\Components\ViewEntry::make('thread')->hiddenLabel()->view('filament.infolists.ticket-thread'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('subject')->label(__('الموضوع'))->weight('bold')->searchable()
                    ->description(fn (SupportTicket $record) => $record->code.($record->unit ? ' · '.$record->unit->title : '')),
                TextColumn::make('type')->label(__('النوع'))->badge(),
                TextColumn::make('status')->label(__('الحالة'))->badge(),
                TextColumn::make('replies_count')->label(__('الردود'))->counts('replies')->badge()->color('gray')->alignCenter(),
                TextColumn::make('created_at')->label(__('فُتحت'))->since()->sortable(),
            ])
            ->recordActions([ViewAction::make()->label(__('المحادثة'))->modalWidth('4xl')]);
    }

    /** Fill server-side fields; never trust the portal user for ownership. */
    public static function prepareTicket(array $data, array $owner): array
    {
        if (filled($data['unit_id'] ?? null) && ! array_key_exists((int) $data['unit_id'], static::ticketUnits())) {
            $data['unit_id'] = null;
        }

        return $owner + $data + SupportTicket::newTicketDefaults();
    }
}
