<?php

namespace App\Filament\Owner\Resources\MaintenanceRequests;

use App\Enums\MaintenanceStatus as S;
use App\Exceptions\BusinessRuleException;
use App\Filament\Concerns\PortalMaintenance;
use App\Filament\Owner\Resources\MaintenanceRequests\Pages\ManageMaintenanceRequests;
use App\Filament\PortalResource;
use App\Models\MaintenanceRequest;
use App\Services\Maintenance\MaintenanceService;
use App\Support\Portal;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Repairs on the owner's units; estimates above the office's limit wait for the owner's yes or no. */
class MaintenanceRequestResource extends PortalResource
{
    use PortalMaintenance;

    protected static ?string $model = MaintenanceRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'طلب صيانة';

    protected static ?string $pluralModelLabel = 'الصيانة';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('unit_id', Portal::ownerUnits(Portal::owner())->select('units.id'))->with('unit');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = static::getEloquentQuery()->where('status', S::AwaitingApproval)->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                ...self::portalColumns(),
                TextColumn::make('amount')->label(__('التكلفة'))->numeric(2)->placeholder('-')
                    ->state(fn (MaintenanceRequest $r) => $r->cost ?? $r->estimated_cost)
                    ->description(fn (MaintenanceRequest $r) => $r->cost === null && $r->estimated_cost !== null ? __('تقديرية') : null),
            ])
            ->emptyStateHeading(__('لا توجد طلبات صيانة على وحداتك'))
            ->recordActions([
                self::decision(true),
                self::decision(false),
                ViewAction::make()->modalWidth('3xl'),
            ]);
    }

    private static function decision(bool $approve): Action
    {
        return Action::make($approve ? 'approve' : 'reject')
            ->label($approve ? __('موافقة') : __('رفض'))
            ->icon($approve ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedXCircle)
            ->color($approve ? 'success' : 'danger')
            ->visible(fn (MaintenanceRequest $record) => $record->status === S::AwaitingApproval)
            ->modalHeading(fn (MaintenanceRequest $record) => ($approve ? __('الموافقة على صيانة') : __('رفض صيانة')).' '.$record->code)
            ->modalDescription(fn (MaintenanceRequest $record) => __(':title — التكلفة التقديرية :amount', [
                'title' => $record->title, 'amount' => number_format((float) $record->estimated_cost, 2),
            ]))
            ->schema([Textarea::make('note')->label(__('ملاحظة للمكتب'))->rows(2)->maxLength(250)->required(! $approve)])
            ->action(function (MaintenanceRequest $record, array $data) use ($approve) {
                try {
                    app(MaintenanceService::class)->decide($record, Portal::owner(), $approve, $data['note'] ?? null);
                    Notification::make()->success()->title($approve ? __('تم إبلاغ المكتب بموافقتك') : __('تم إبلاغ المكتب برفضك'))->send();
                } catch (BusinessRuleException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                }
            });
    }

    public static function getPages(): array
    {
        return ['index' => ManageMaintenanceRequests::route('/')];
    }
}
