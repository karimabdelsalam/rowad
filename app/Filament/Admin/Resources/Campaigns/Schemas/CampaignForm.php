<?php

namespace App\Filament\Admin\Resources\Campaigns\Schemas;

use App\Enums\MessageChannel;
use App\Filament\Forms\AiWriteAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CampaignForm
{
    public static function audiences(): array
    {
        return [
            'owners' => __('كل الملاك'),
            'clients' => __('كل العملاء'),
            'renters' => __('المستأجرون الحاليون'),
            'buyers' => __('المشترون'),
            'employees' => __('الموظفون'),
            'subscribers' => __('القائمة البريدية'),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        // SMS and WhatsApp both carry short plain text to a mobile number
        $isSms = fn (Get $get) => in_array($get('channel') instanceof MessageChannel ? $get('channel')->value : $get('channel'), ['sms', 'whatsapp'], true);

        return $schema->columns(2)->components([
            Select::make('channel')->options(fn () => collect(MessageChannel::cases())
                ->reject(fn (MessageChannel $c) => $c === MessageChannel::WhatsApp && ! \App\Services\Messaging\WhatsAppGateway::enabled())
                ->mapWithKeys(fn (MessageChannel $c) => [$c->value => $c->getLabel()])->all())->default('mail')->required()->live(),
            Select::make('audience')->options(self::audiences())->required(),
            TextInput::make('subject')->required(fn (Get $get) => ! $isSms($get))->hidden($isSms)->columnSpanFull(),
            RichEditor::make('body')->required()->hidden($isSms)->columnSpanFull()
                ->hintAction(AiWriteAction::make('email', subjectField: 'subject')),
            Textarea::make('body')->required()->visible($isSms)->maxLength(612)->rows(4)->columnSpanFull()
                ->helperText(__('الرسالة العربية الواحدة 70 حرفًا'))
                ->hintAction(AiWriteAction::make('sms', name: 'aiWriteSms')),
        ]);
    }
}
