<?php

namespace App\Filament\Admin\Resources\InboxMessages\Schemas;

use App\Filament\Admin\Resources\Units\UnitResource;
use App\Filament\Infolists\Parts;
use App\Models\InboxMessage;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Website inquiry: the message, who sent it, the unit they asked about, and our reply. */
class InboxMessageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Parts::header(Heroicon::OutlinedInboxArrowDown, fn (InboxMessage $r) => [
                'kicker' => $r->source === 'unit_inquiry' ? __('استفسار عن وحدة') : __('رسالة من الموقع'),
                'title' => $r->subject ?: ($r->unit?->title ?? __('رسالة من :name', ['name' => $r->name])),
                'sub' => [$r->name, $r->mobile, $r->email],
                'facts' => [
                    Parts::fact('الحالة', $r->replied_at ? __('تم الرد') : ($r->read_at ? __('مقروءة') : __('جديدة')), $r->replied_at ? 'success' : ($r->read_at ? null : 'warning')),
                    Parts::fact('وصلت', $r->created_at?->locale(app()->getLocale())->diffForHumans()),
                ],
            ]),
            Grid::make(1)->columnSpan(['lg' => 2])->schema([
                Section::make(__('الرسالة'))->icon(Heroicon::OutlinedChatBubbleBottomCenterText)->schema([
                    TextEntry::make('message')->hiddenLabel()->prose(),
                ]),
                Section::make(__('ردّنا'))->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->description(fn (InboxMessage $r) => $r->replied_at ? __('أُرسل في :d', ['d' => $r->replied_at->format('Y-m-d H:i')]) : __('لم يُرَدّ بعد — استخدم زر «رد» في أعلى الصفحة.'))
                    ->schema([
                        TextEntry::make('reply')->hiddenLabel()->prose()->placeholder('-'),
                    ]),
            ]),
            Grid::make(1)->columnSpan(['lg' => 1])->schema([
                Section::make(__('المرسل'))->icon(Heroicon::OutlinedUser)->schema([
                    TextEntry::make('name')->label(__('الاسم'))->weight('bold'),
                    Parts::phone('mobile'),
                    Parts::email(),
                    Parts::link('unit.title', 'الوحدة المطلوبة', fn (InboxMessage $r) => $r->unit ? UnitResource::getUrl('view', ['record' => $r->unit]) : null),
                ]),
                Section::make(__('تفاصيل'))->icon(Heroicon::OutlinedInformationCircle)->columns(2)->schema([
                    TextEntry::make('source')->label(__('المصدر'))->badge()->color('gray')
                        ->formatStateUsing(fn ($state) => $state === 'unit_inquiry' ? __('استفسار عن وحدة') : __('نموذج التواصل')),
                    TextEntry::make('locale')->label(__('اللغة'))->badge()->color('gray')->formatStateUsing(fn ($state) => $state === 'ar' ? __('العربية') : __('English')),
                    TextEntry::make('read_at')->label(__('قُرئت'))->dateTime('Y-m-d H:i')->placeholder(__('غير مقروءة')),
                    TextEntry::make('created_at')->label(__('وصلت في'))->dateTime('Y-m-d H:i'),
                    TextEntry::make('ip_address')->label('IP')->placeholder('-')->fontFamily('mono')->size('xs')->columnSpanFull(),
                ]),
            ]),
        ]);
    }
}
