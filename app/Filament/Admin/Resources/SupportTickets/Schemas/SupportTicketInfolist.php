<?php

namespace App\Filament\Admin\Resources\SupportTickets\Schemas;

use App\Enums\TicketStatus;
use App\Filament\Admin\Resources\Clients\ClientResource;
use App\Filament\Admin\Resources\Owners\OwnerResource;
use App\Filament\Admin\Resources\Units\UnitResource;
use App\Filament\Infolists\Parts;
use App\Models\SupportTicket;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Support ticket: the request as a conversation thread, plus who sent it and about which unit. */
class SupportTicketInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Parts::header(Heroicon::OutlinedLifebuoy, fn (SupportTicket $r) => [
                'kicker' => __('تذكرة دعم').' · '.($r->type?->getLabel() ?? ''),
                'title' => $r->subject,
                'sub' => [$r->code, self::senderName($r), $r->unit?->title],
                'facts' => [
                    Parts::fact('الحالة', $r->status?->getLabel(), match ($r->status) {
                        TicketStatus::Open => 'warning', TicketStatus::Answered => 'info', TicketStatus::Closed => 'success', default => null,
                    }),
                    Parts::fact('الردود', $r->replies->count()),
                    Parts::fact('فُتحت', $r->created_at?->locale(app()->getLocale())->diffForHumans()),
                ],
            ]),
            Grid::make(1)->columnSpan(['lg' => 2])->schema([
                Section::make(__('المحادثة'))->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->description(fn (SupportTicket $r) => $r->status === TicketStatus::Closed ? __('التذكرة مغلقة') : __('الرد يظهر فورًا في بوابة :who', ['who' => $r->owner ? __('المالك') : __('العميل')]))
                    ->schema([
                        ViewEntry::make('thread')->hiddenLabel()->view('filament.infolists.ticket-thread'),
                    ]),
            ]),
            Grid::make(1)->columnSpan(['lg' => 1])->schema([
                Section::make(__('مقدّم الطلب'))->icon(Heroicon::OutlinedUser)->schema([
                    Parts::link('owner.full_name', 'المالك', fn (SupportTicket $r) => $r->owner ? OwnerResource::getUrl('view', ['record' => $r->owner]) : null)
                        ->visible(fn (SupportTicket $r) => $r->owner !== null),
                    Parts::link('client.full_name', 'العميل', fn (SupportTicket $r) => $r->client ? ClientResource::getUrl('view', ['record' => $r->client]) : null)
                        ->visible(fn (SupportTicket $r) => $r->client !== null),
                    Parts::phone('sender_mobile')->state(fn (SupportTicket $r) => $r->owner?->mobile ?? $r->client?->mobile),
                    Parts::email('sender_email')->state(fn (SupportTicket $r) => $r->owner?->email ?? $r->client?->email),
                    Parts::link('unit.title', 'الوحدة', fn (SupportTicket $r) => $r->unit ? UnitResource::getUrl('view', ['record' => $r->unit]) : null),
                ]),
                Section::make(__('التذكرة'))->icon(Heroicon::OutlinedTicket)->columns(2)->schema([
                    TextEntry::make('code')->label(__('الرقم'))->weight('bold')->copyable(),
                    TextEntry::make('type')->label(__('النوع'))->badge(),
                    TextEntry::make('status')->label(__('الحالة'))->badge(),
                    TextEntry::make('read_at')->label(__('قُرئت'))->dateTime('Y-m-d H:i')->placeholder(__('غير مقروءة')),
                    TextEntry::make('created_at')->label(__('فُتحت في'))->dateTime('Y-m-d H:i'),
                    TextEntry::make('updated_at')->label(__('آخر تحديث'))->dateTime('Y-m-d H:i'),
                ]),
            ]),
        ]);
    }

    public static function senderName(SupportTicket $r): ?string
    {
        return $r->owner?->full_name ?? $r->client?->displayName();
    }
}
