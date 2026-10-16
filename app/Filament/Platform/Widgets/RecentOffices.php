<?php

namespace App\Filament\Platform\Widgets;

use App\Filament\Platform\Resources\Tenants\Schemas\TenantForm;
use App\Filament\Platform\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentOffices extends TableWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('أحدث المؤسسات'))
            ->query(fn () => Tenant::query()->with(['plan', 'domains'])->latest()->limit(5))
            ->paginated(false)
            ->recordUrl(fn (Tenant $record) => TenantResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')->label(__('المؤسسة'))->weight('bold')->description(fn (Tenant $record) => $record->domains->first()?->domain),
                TextColumn::make('plan.name_ar')->label(__('الباقة'))->placeholder('-'),
                TextColumn::make('status')->label(__('الحالة'))->badge()->formatStateUsing(fn (string $state) => TenantForm::statuses()[$state] ?? $state),
                TextColumn::make('created_at')->label(__('تاريخ التسجيل'))->date('Y-m-d'),
            ]);
    }
}
