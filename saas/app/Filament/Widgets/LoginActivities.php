<?php

namespace App\Filament\Widgets;

use App\Models\LoginActivity;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Legacy dashboard "login activities": the signed-in user's last ten sign-ins, with a clear action. */
class LoginActivities extends TableWidget
{
    protected static ?int $sort = 90;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 8];

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('آخر نشاطات الدخول'))
            ->query(fn () => LoginActivity::query()->where('user_id', auth()->id())->latest('id')->limit(10))
            ->paginated(false)
            ->columns([
                TextColumn::make('created_at')->label(__('الوقت'))->dateTime('Y-m-d H:i'),
                TextColumn::make('ip_address')->label(__('عنوان IP')),
                TextColumn::make('user_agent')->label(__('الجهاز'))->limit(60)->tooltip(fn (LoginActivity $record) => $record->user_agent),
                IconColumn::make('successful')->label(__('ناجح'))->boolean(),
            ])
            ->headerActions([
                Action::make('clear')
                    ->label(__('مسح السجل'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function () {
                        LoginActivity::where('user_id', auth()->id())->delete();
                        Notification::make()->title(__('تم مسح سجل الدخول'))->success()->send();
                    }),
            ]);
    }
}
