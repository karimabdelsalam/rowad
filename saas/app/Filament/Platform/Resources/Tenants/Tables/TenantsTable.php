<?php

namespace App\Filament\Platform\Resources\Tenants\Tables;

use App\Filament\Platform\Resources\Tenants\Schemas\TenantForm;
use App\Models\Tenant;
use App\Services\Platform\OfficeProvisioner;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['plan', 'domains']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('اسم المؤسسة'))->searchable()->sortable(),
                TextColumn::make('domains.domain')->label(__('النطاقات'))->listWithLineBreaks()->copyable(),
                TextColumn::make('plan.name_ar')->label(__('الخطة'))->badge(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => TenantForm::statuses()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        Tenant::STATUS_ACTIVE => 'success',
                        Tenant::STATUS_TRIAL => 'info',
                        Tenant::STATUS_PAST_DUE => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('trial_ends_at')->dateTime('Y-m-d')->sortable(),
                TextColumn::make('email')->toggleable(),
                TextColumn::make('created_at')->dateTime('Y-m-d')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(TenantForm::statuses()),
                SelectFilter::make('plan_id')->relationship('plan', 'name_ar'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('open')
                    ->label(__('فتح لوحة المؤسسة'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Tenant $record) => request()->getScheme().'://'.$record->domains->first()?->domain.'/admin', shouldOpenInNewTab: true),
                Action::make('suspend')
                    ->label(__('إيقاف'))
                    ->color('danger')
                    ->icon(Heroicon::OutlinedPauseCircle)
                    ->requiresConfirmation()
                    ->visible(fn (Tenant $record) => $record->status !== Tenant::STATUS_SUSPENDED)
                    ->action(fn (Tenant $record) => app(OfficeProvisioner::class)->suspend($record)),
                Action::make('activate')
                    ->label(__('تفعيل'))
                    ->color('success')
                    ->icon(Heroicon::OutlinedPlayCircle)
                    ->requiresConfirmation()
                    ->visible(fn (Tenant $record) => $record->status !== Tenant::STATUS_ACTIVE)
                    ->action(fn (Tenant $record) => app(OfficeProvisioner::class)->activate($record)),
            ]);
    }
}
