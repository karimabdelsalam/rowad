<?php

namespace App\Filament\Admin\Resources\Campaigns\Schemas;

use App\Enums\MessageChannel;
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
        $isSms = fn (Get $get) => ($get('channel') instanceof MessageChannel ? $get('channel')->value : $get('channel')) === 'sms';

        return $schema->columns(2)->components([
            Select::make('channel')->options(MessageChannel::class)->default('mail')->required()->live(),
            Select::make('audience')->options(self::audiences())->required(),
            TextInput::make('subject')->required(fn (Get $get) => ! $isSms($get))->hidden($isSms)->columnSpanFull(),
            RichEditor::make('body')->required()->hidden($isSms)->columnSpanFull(),
            Textarea::make('body')->required()->visible($isSms)->maxLength(612)->rows(4)->columnSpanFull()
                ->helperText(__('الرسالة العربية الواحدة 70 حرفًا')),
        ]);
    }
}
