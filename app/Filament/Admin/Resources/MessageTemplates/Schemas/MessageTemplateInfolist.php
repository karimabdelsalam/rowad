<?php

namespace App\Filament\Admin\Resources\MessageTemplates\Schemas;

use App\Enums\NotificationEvent;
use App\Filament\Infolists\Parts;
use App\Models\MessageTemplate;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Notification template: which event fires it and what the email / SMS say. */
class MessageTemplateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Parts::header(Heroicon::OutlinedBellAlert, fn (MessageTemplate $r) => [
                'kicker' => __('قالب إشعار'),
                'title' => $r->title,
                'sub' => [self::eventLabel($r->event), $r->event],
                'facts' => [
                    Parts::fact('البريد', $r->email_enabled ? __('مفعل') : __('متوقف'), $r->email_enabled ? 'success' : null),
                    Parts::fact('SMS', $r->sms_enabled ? __('مفعل') : __('متوقف'), $r->sms_enabled ? 'success' : null),
                ],
            ]),
            Grid::make(1)->columnSpan(['lg' => 2])->schema([
                Section::make(__('رسالة البريد الإلكتروني'))->icon(Heroicon::OutlinedEnvelope)->schema([
                    TextEntry::make('email_subject')->label(__('الموضوع'))->weight('bold')->placeholder('-'),
                    TextEntry::make('email_body')->label(__('النص'))->placeholder('-')->html()->prose(),
                ]),
                Section::make(__('الرسالة النصية'))->icon(Heroicon::OutlinedDevicePhoneMobile)->schema([
                    TextEntry::make('sms_body')->hiddenLabel()->placeholder('-')->prose()
                        ->helperText(fn (MessageTemplate $r) => $r->sms_body ? trans_choice('{1} حرف واحد|[2,*] :count حرفًا', mb_strlen($r->sms_body)) : null),
                ]),
            ]),
            Grid::make(1)->columnSpan(['lg' => 1])->schema([
                Section::make(__('الحدث'))->icon(Heroicon::OutlinedBolt)->schema([
                    TextEntry::make('event')->label(__('يُرسل عند'))->weight('bold')->formatStateUsing(fn ($state) => self::eventLabel($state)),
                    TextEntry::make('event_key')->label(__('المعرّف'))->state(fn (MessageTemplate $r) => $r->event)->fontFamily('mono')->size('xs')->copyable(),
                    IconEntry::make('email_enabled')->label(__('إرسال بريد'))->boolean(),
                    IconEntry::make('sms_enabled')->label(__('إرسال SMS'))->boolean(),
                ]),
                Section::make(__('المتغيرات المتاحة'))->icon(Heroicon::OutlinedVariable)->schema([
                    TextEntry::make('placeholders')->hiddenLabel()->badge()->color('gray')->fontFamily('mono')
                        ->state(fn (MessageTemplate $r) => collect(['{name}', '{amount}', '{date}', '{unit}', '{contract}', '{office}'])
                            ->merge(preg_match_all('/\{[a-z_]+\}/', (string) $r->email_body.' '.$r->sms_body, $m) ? $m[0] : [])->unique()->values()->all()),
                ]),
                Parts::record(false),
            ]),
        ]);
    }

    public static function eventLabel(?string $event): string
    {
        return NotificationEvent::tryFrom((string) $event)?->getLabel() ?? (string) $event;
    }
}
