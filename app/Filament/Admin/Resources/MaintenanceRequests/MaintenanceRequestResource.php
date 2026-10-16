<?php

namespace App\Filament\Admin\Resources\MaintenanceRequests;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus as S;
use App\Filament\Admin\Resources\Clients\ClientResource;
use App\Filament\Admin\Resources\MaintenanceRequests\Pages\ListMaintenanceRequests;
use App\Filament\Admin\Resources\MaintenanceRequests\Pages\ViewMaintenanceRequest;
use App\Filament\Admin\Resources\PaymentVouchers\PaymentVoucherResource;
use App\Filament\Admin\Resources\Units\UnitResource;
use App\Filament\Infolists\Parts;
use App\Filament\Resource;
use App\Models\MaintenanceRequest;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** طلبات الصيانة: بلاغات المستأجرين والمكتب حتى الإنجاز وتحميل التكلفة. */
class MaintenanceRequestResource extends Resource
{
    protected static ?string $model = MaintenanceRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|\UnitEnum|null $navigationGroup = 'التشغيل';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'طلب صيانة';

    protected static ?string $pluralModelLabel = 'طلبات الصيانة';

    protected static ?string $permission = 'maintenance_requests';

    protected static ?string $recordTitleAttribute = 'code';

    public static function getNavigationBadge(): ?string
    {
        $n = MaintenanceRequest::query()->whereIn('status', [S::New->value, S::AwaitingApproval->value])->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'md' => 2])->schema([
                Select::make('unit_id')->label(__('الوحدة'))->relationship('unit', 'title')->searchable()->preload()->required()
                    ->columnSpanFull(),
                Select::make('category')->label(__('النوع'))->options(MaintenanceCategory::class)->default('other')->required()->native(false),
                Select::make('priority')->label(__('الأولوية'))->options(MaintenancePriority::class)->default('normal')->required()->native(false),
                TextInput::make('title')->label(__('المشكلة باختصار'))->required()->maxLength(150)->columnSpanFull(),
                Textarea::make('description')->label(__('التفاصيل'))->rows(3)->maxLength(3000)->columnSpanFull(),
                TextInput::make('preferred_time')->label(__('الوقت المناسب للزيارة'))->maxLength(120)->columnSpanFull(),
                SpatieMediaLibraryFileUpload::make('photos')->label(__('الصور'))->collection('photos')->image()->multiple()->maxFiles(6)
                    ->maxSize(8192)->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Parts::header(Heroicon::OutlinedWrenchScrewdriver, fn (MaintenanceRequest $r) => [
                'kicker' => $r->code.' · '.$r->category->getLabel(),
                'title' => $r->title,
                'sub' => [$r->unit?->title, $r->client?->displayName(), __('فُتح :when', ['when' => $r->created_at?->locale(app()->getLocale())->diffForHumans()])],
                'facts' => array_values(array_filter([
                    Parts::fact('الحالة', $r->status->getLabel(), match ($r->status) {
                        S::Completed => 'success', S::AwaitingApproval, S::New => 'warning', S::Cancelled => null, default => 'info',
                    }),
                    Parts::fact('الأولوية', $r->priority->getLabel(), $r->priority === MaintenancePriority::Normal ? null : 'danger'),
                    $r->cost !== null ? Parts::fact('التكلفة', Parts::amount($r->cost)) : ($r->estimated_cost !== null ? Parts::fact('التقديرية', Parts::amount($r->estimated_cost)) : null),
                ])),
            ]),
            Grid::make(1)->columnSpan(['lg' => 2])->schema([
                Section::make(__('البلاغ'))->icon(Heroicon::OutlinedChatBubbleBottomCenterText)->columns(2)->schema([
                    TextEntry::make('description')->label(__('التفاصيل'))->placeholder('-')->columnSpanFull()->prose(),
                    TextEntry::make('preferred_time')->label(__('الوقت المناسب للزيارة'))->placeholder('-'),
                    TextEntry::make('reporter')->label(__('مصدر البلاغ'))
                        ->state(fn (MaintenanceRequest $r) => $r->created_by ? __('المكتب').' — '.$r->creator?->name : __('المستأجر من البوابة')),
                    ImageEntry::make('photo_urls')->label(__('الصور'))->columnSpanFull()->imageHeight(140)
                        ->state(fn (MaintenanceRequest $r) => $r->photoUrls())
                        ->url(fn ($state) => is_string($state) ? str_replace('?size=thumb', '', $state) : null, true)
                        ->extraImgAttributes(['class' => 'rounded-xl object-cover', 'loading' => 'lazy'])
                        ->visible(fn (MaintenanceRequest $r) => $r->getMedia('photos')->isNotEmpty()),
                ]),
                Section::make(__('الإنجاز'))->icon(Heroicon::OutlinedCheckBadge)->columns(['default' => 2, 'md' => 3])
                    ->visible(fn (MaintenanceRequest $r) => $r->status === S::Completed)
                    ->schema([
                        TextEntry::make('completed_at')->label(__('تاريخ الإنجاز'))->dateTime('Y-m-d H:i'),
                        Parts::money('cost', 'التكلفة'),
                        TextEntry::make('charge_to')->label(__('حُمّلت على'))->badge()->color('gray')->placeholder('-')
                            ->formatStateUsing(fn ($state) => MaintenanceRequest::chargeOptions()[$state] ?? $state),
                        Parts::link('paymentVoucher.number', 'سند الصرف', fn (MaintenanceRequest $r) => $r->payment_voucher_id
                            ? PaymentVoucherResource::getUrl('view', ['record' => $r->payment_voucher_id]) : null),
                        TextEntry::make('resolution')->label(__('ما الذي تم'))->placeholder('-')->columnSpanFull(),
                        ImageEntry::make('after_urls')->label(__('بعد الإنجاز'))->columnSpanFull()->imageHeight(140)
                            ->state(fn (MaintenanceRequest $r) => $r->photoUrls('after'))
                            ->extraImgAttributes(['class' => 'rounded-xl object-cover', 'loading' => 'lazy'])
                            ->visible(fn (MaintenanceRequest $r) => $r->getMedia('after')->isNotEmpty()),
                        TextEntry::make('rating')->label(__('تقييم المستأجر'))->placeholder(__('لم يقيّم بعد'))
                            ->formatStateUsing(fn ($state) => str_repeat('★', (int) $state).str_repeat('☆', 5 - (int) $state))
                            ->color(fn ($state) => (int) $state <= 2 ? 'danger' : 'warning')->size('lg'),
                        TextEntry::make('feedback')->label(__('ملاحظة المستأجر'))->placeholder('-')->columnSpan(2),
                    ]),
                Section::make(__('سبب الإلغاء'))->icon(Heroicon::OutlinedXCircle)->visible(fn (MaintenanceRequest $r) => $r->status === S::Cancelled)
                    ->schema([TextEntry::make('cancel_reason')->hiddenLabel()]),
            ]),
            Grid::make(1)->columnSpan(['lg' => 1])->schema([
                Section::make(__('الوحدة والمستأجر'))->icon(Heroicon::OutlinedHome)->schema([
                    Parts::link('unit.title', 'الوحدة', fn (MaintenanceRequest $r) => $r->unit ? UnitResource::getUrl('view', ['record' => $r->unit]) : null),
                    Parts::link('client.full_name', 'المستأجر', fn (MaintenanceRequest $r) => $r->client ? ClientResource::getUrl('view', ['record' => $r->client]) : null),
                    Parts::phone('client.mobile', 'جوال المستأجر'),
                ]),
                Section::make(__('الفني والموعد'))->icon(Heroicon::OutlinedUserCircle)->columns(2)->schema([
                    TextEntry::make('technician_name')->label(__('الفني / الشركة'))->placeholder(__('لم يُحدد')),
                    Parts::phone('technician_phone', 'جوال الفني'),
                    TextEntry::make('scheduled_at')->label(__('موعد الزيارة'))->dateTime('Y-m-d H:i')->placeholder('-'),
                    Parts::money('estimated_cost', 'التكلفة التقديرية'),
                    TextEntry::make('assignee.name')->label(__('الموظف المتابع'))->placeholder('-')->columnSpanFull(),
                ]),
                Section::make(__('موافقة المالك'))->icon(Heroicon::OutlinedHandRaised)->columns(2)
                    ->visible(fn (MaintenanceRequest $r) => $r->status === S::AwaitingApproval || $r->owner_decision !== null)
                    ->schema([
                        TextEntry::make('owner_decision')->label(__('القرار'))->badge()->placeholder(__('بانتظار الرد'))
                            ->formatStateUsing(fn ($state) => $state === 'approved' ? __('موافق') : __('مرفوض'))
                            ->color(fn ($state) => $state === 'approved' ? 'success' : 'danger'),
                        TextEntry::make('ownerDecider.full_name')->label(__('المالك'))->placeholder('-'),
                        TextEntry::make('owner_note')->label(__('ملاحظة المالك'))->placeholder('-')->columnSpanFull(),
                    ]),
                Parts::record(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['unit', 'client']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('الطلب'))->weight('bold')->searchable(['title', 'code'])->wrap()
                    ->description(fn (MaintenanceRequest $r) => $r->code.' · '.$r->category->getLabel()),
                TextColumn::make('unit.title')->label(__('الوحدة'))->searchable()
                    ->description(fn (MaintenanceRequest $r) => $r->client?->displayName()),
                TextColumn::make('priority')->label(__('الأولوية'))->badge(),
                TextColumn::make('status')->label(__('الحالة'))->badge()
                    ->description(fn (MaintenanceRequest $r) => $r->status === S::Scheduled && $r->scheduled_at ? $r->scheduled_at->format('Y-m-d H:i') : null),
                TextColumn::make('technician_name')->label(__('الفني'))->placeholder('-')->toggleable(),
                TextColumn::make('cost')->label(__('التكلفة'))->numeric(2)->placeholder('-')
                    ->state(fn (MaintenanceRequest $r) => $r->cost ?? $r->estimated_cost)
                    ->color(fn (MaintenanceRequest $r) => $r->cost === null ? 'gray' : null),
                TextColumn::make('rating')->label(__('التقييم'))->placeholder('-')->alignCenter()->toggleable()
                    ->formatStateUsing(fn ($state) => $state.' ★')->color(fn ($state) => (int) $state <= 2 ? 'danger' : 'warning'),
                TextColumn::make('created_at')->label(__('فُتح'))->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')->label(__('النوع'))->options(MaintenanceCategory::class),
                SelectFilter::make('priority')->label(__('الأولوية'))->options(MaintenancePriority::class),
                SelectFilter::make('unit_id')->label(__('الوحدة'))->relationship('unit', 'title')->searchable()->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make(MaintenanceActions::all()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceRequests::route('/'),
            'view' => ViewMaintenanceRequest::route('/{record}'),
        ];
    }
}
