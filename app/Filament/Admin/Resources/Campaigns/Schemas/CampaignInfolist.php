<?php

namespace App\Filament\Admin\Resources\Campaigns\Schemas;

use App\Filament\Infolists\Parts;
use App\Models\Campaign;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Bulk campaign: the message, who it went to and how the delivery went. */
class CampaignInfolist
{
    public const AUDIENCES = ['owners' => 'الملاك', 'clients' => 'العملاء', 'renters' => 'المستأجرون', 'buyers' => 'المشترون', 'employees' => 'الموظفون', 'subscribers' => 'المشتركون في النشرة'];

    public const STATUSES = ['draft' => ['مسودة', 'gray'], 'scheduled' => ['مجدولة', 'info'], 'sending' => ['قيد الإرسال', 'warning'], 'sent' => ['أُرسلت', 'success'], 'failed' => ['فشلت', 'danger']];

    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Parts::header(Heroicon::OutlinedMegaphone, fn (Campaign $r) => [
                'kicker' => __('حملة').' · '.($r->channel?->getLabel() ?? ''),
                'title' => $r->subject ?: str($r->body)->limit(60),
                'sub' => [__('إلى :a', ['a' => self::audience($r->audience)]), $r->creator?->name, $r->created_at?->format('Y-m-d')],
                'facts' => [
                    Parts::fact('الحالة', self::status($r->status), self::STATUSES[$r->status][1] ?? null),
                    Parts::fact('المستلمون', $r->recipients_count),
                    Parts::fact('أُرسلت', $r->sent_count, 'success'),
                    Parts::fact('فشلت', $r->failed_count, $r->failed_count > 0 ? 'danger' : null),
                ],
            ]),
            Grid::make(1)->columnSpan(['lg' => 2])->schema([
                Section::make(__('الرسالة'))->icon(Heroicon::OutlinedEnvelope)->schema([
                    TextEntry::make('subject')->label(__('الموضوع'))->weight('bold')->placeholder('-'),
                    TextEntry::make('body')->label(__('النص'))->prose()->markdown(),
                ]),
                Section::make(__('نتيجة الإرسال'))->icon(Heroicon::OutlinedChartBar)->columns(['default' => 2, 'md' => 4])->schema([
                    TextEntry::make('recipients_count')->label(__('المستلمون'))->numeric(),
                    TextEntry::make('sent_count')->label(__('أُرسلت'))->numeric()->color('success'),
                    TextEntry::make('failed_count')->label(__('فشلت'))->numeric()->color(fn ($state) => $state > 0 ? 'danger' : null),
                    TextEntry::make('success_rate')->label(__('نسبة النجاح'))
                        ->state(fn (Campaign $r) => $r->recipients_count > 0 ? round($r->sent_count / $r->recipients_count * 100).' %' : '-'),
                ]),
            ]),
            Grid::make(1)->columnSpan(['lg' => 1])->schema([
                Section::make(__('الإعدادات'))->icon(Heroicon::OutlinedCog6Tooth)->columns(2)->schema([
                    TextEntry::make('channel')->label(__('القناة'))->badge(),
                    TextEntry::make('audience')->label(__('الجمهور'))->badge()->color('info')->formatStateUsing(fn ($state) => self::audience($state)),
                    TextEntry::make('status')->label(__('الحالة'))->badge()->formatStateUsing(fn ($state) => self::status($state))
                        ->color(fn ($state) => self::STATUSES[$state][1] ?? 'gray'),
                    TextEntry::make('scheduled_at')->label(__('موعد الإرسال'))->dateTime('Y-m-d H:i')->placeholder(__('فوري')),
                ]),
                Parts::record(),
            ]),
        ]);
    }

    public static function audience(?string $key): string
    {
        return isset(self::AUDIENCES[$key]) ? __(self::AUDIENCES[$key]) : (string) $key;
    }

    public static function status(?string $key): string
    {
        return isset(self::STATUSES[$key]) ? __(self::STATUSES[$key][0]) : (string) $key;
    }
}
