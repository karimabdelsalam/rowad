<?php

namespace App\Filament\Platform\Resources\Licenses\RelationManagers;

use App\Models\LicenseActivation;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** The servers this license is active on. */
class ActivationsRelationManager extends RelationManager
{
    protected static string $relationship = 'activations';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('السيرفرات المفعّلة');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_seen_at', 'desc')
            ->emptyStateHeading(__('لم يُفعَّل هذا الترخيص على أي سيرفر بعد'))
            ->columns([
                TextColumn::make('domain')->label(__('النطاق'))->weight('bold')->description(fn (LicenseActivation $r) => $r->ip),
                TextColumn::make('status')->label(__('الحالة'))->badge()
                    ->formatStateUsing(fn ($state) => $state === 'active' ? __('مفعّل') : __('ملغى التفعيل'))
                    ->color(fn ($state) => $state === 'active' ? 'success' : 'gray'),
                TextColumn::make('app_version')->label(__('الإصدار'))->placeholder('-'),
                TextColumn::make('php_version')->label('PHP')->placeholder('-'),
                TextColumn::make('activated_at')->label(__('فُعّل في'))->date('Y-m-d'),
                TextColumn::make('last_seen_at')->label(__('آخر اتصال'))->since()
                    ->color(fn ($state) => $state && Carbon::parse($state)->lt(now()->subDays(3)) ? 'warning' : null),
                TextColumn::make('instance_id')->label(__('معرّف السيرفر'))->limit(12)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('deactivate')->label(__('إلغاء التفعيل'))->icon(Heroicon::OutlinedXCircle)->color('danger')
                    ->visible(fn (LicenseActivation $r) => $r->status === 'active')->requiresConfirmation()
                    ->modalDescription(__('يتوقف النظام على هذا السيرفر عند تحققه التالي، ويُفرَّغ مكانه لسيرفر آخر.'))
                    ->action(fn (LicenseActivation $r) => $r->update(['status' => 'deactivated', 'deactivated_at' => now()])),
            ]);
    }
}
