<?php

namespace App\Filament\Client\Resources\MaintenanceRequests;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus as S;
use App\Exceptions\BusinessRuleException;
use App\Filament\Client\Resources\MaintenanceRequests\Pages\ManageMaintenanceRequests;
use App\Filament\Concerns\PortalMaintenance;
use App\Filament\PortalResource;
use App\Models\MaintenanceRequest;
use App\Services\Maintenance\MaintenanceService;
use App\Support\Portal;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** The tenant reports a problem with photos, follows the visit and rates the work. */
class MaintenanceRequestResource extends PortalResource
{
    use PortalMaintenance;

    protected static ?string $model = MaintenanceRequest::class;

    protected static bool $portalCanCreate = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'طلب صيانة';

    protected static ?string $pluralModelLabel = 'الصيانة';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('client_id', Portal::client()->getKey())->with('unit');
    }

    protected static function showsCost(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Select::make('unit_id')->label(__('الوحدة'))->required()->native(false)
                ->options(fn () => Portal::clientUnits(Portal::client())->pluck('title', 'id'))
                ->default(fn () => Portal::clientUnits(Portal::client())->count() === 1 ? Portal::clientUnits(Portal::client())->value('id') : null),
            Select::make('category')->label(__('نوع المشكلة'))->options(MaintenanceCategory::class)->required()->native(false),
            TextInput::make('title')->label(__('المشكلة باختصار'))->required()->maxLength(150)->placeholder(__('مثال: تسريب ماء تحت مغسلة المطبخ')),
            Textarea::make('description')->label(__('التفاصيل'))->rows(3)->maxLength(3000),
            Radio::make('priority')->label(__('الأولوية'))->options(MaintenancePriority::class)->default('normal')->inline()->required(),
            TextInput::make('preferred_time')->label(__('الوقت المناسب لزيارة الفني'))->maxLength(120)->placeholder(__('مثال: بعد العصر يوميًا')),
            SpatieMediaLibraryFileUpload::make('photos')->label(__('صور المشكلة'))->collection('photos')->image()->multiple()->maxFiles(6)
                ->maxSize(8192)->helperText(__('الصور تساعد الفني على تجهيز القطع المطلوبة.')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns(self::portalColumns())
            ->emptyStateHeading(__('لا توجد طلبات صيانة'))
            ->emptyStateDescription(__('أي عطل في وحدتك؟ افتح طلبًا وأرفق صورة وسنتابعه معك حتى الإنجاز.'))
            ->recordActions([
                Action::make('rate')
                    ->label(__('قيّم الخدمة'))
                    ->icon(Heroicon::OutlinedStar)
                    ->color('warning')
                    ->visible(fn (MaintenanceRequest $record) => $record->status === S::Completed && $record->rating === null)
                    ->schema([
                        Radio::make('rating')->label(__('رضاك عن الخدمة'))->required()->inline()
                            ->options([5 => '★★★★★', 4 => '★★★★', 3 => '★★★', 2 => '★★', 1 => '★']),
                        Textarea::make('feedback')->label(__('ملاحظاتك'))->rows(2)->maxLength(1000),
                    ])
                    ->action(function (MaintenanceRequest $record, array $data) {
                        try {
                            app(MaintenanceService::class)->rate($record, Portal::client(), (int) $data['rating'], $data['feedback'] ?? null);
                            Notification::make()->success()->title(__('شكرًا لتقييمك'))->send();
                        } catch (BusinessRuleException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),
                ViewAction::make()->modalWidth('3xl'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageMaintenanceRequests::route('/')];
    }
}
