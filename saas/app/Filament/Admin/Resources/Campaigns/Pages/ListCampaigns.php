<?php

namespace App\Filament\Admin\Resources\Campaigns\Pages;

use App\Enums\MessageChannel;
use App\Filament\Admin\Resources\Campaigns\CampaignResource;
use App\Services\Messaging\Notifier;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListCampaigns extends ListRecords
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            // legacy newsletter/onesms
            Action::make('singleSms')
                ->label(__('رسالة SMS مفردة'))
                ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                ->color('gray')
                ->visible(function () {
                    $user = auth()->user();

                    return $user !== null && ($user->is_super_admin || $user->can('campaigns.send'));
                })
                ->schema([
                    TextInput::make('mobile')->label(__('رقم الجوال'))->tel()->required()->maxLength(20),
                    Textarea::make('message')->label(__('الرسالة'))->required()->rows(4)->maxLength(700),
                ])
                ->action(function (array $data) {
                    app(Notifier::class)->direct(MessageChannel::Sms, $data['mobile'], null, $data['message'], 'single_sms');
                    Notification::make()->title(__('تم إرسال الرسالة إلى الطابور'))->success()->send();
                }),
        ];
    }
}
