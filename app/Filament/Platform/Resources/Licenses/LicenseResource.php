<?php

namespace App\Filament\Platform\Resources\Licenses;

use App\Filament\Resource;
use App\Models\License;
use App\Models\Plan;
use App\Services\Licensing\LicenseClient;
use App\Support\PlanLimits;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** Licenses for copies installed on customers' servers (aqarnova.com only). */
class LicenseResource extends Resource
{
    protected static ?string $model = License::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'التراخيص';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'ترخيص';

    protected static ?string $pluralModelLabel = 'التراخيص';

    public static function canAccess(): bool
    {
        return ! LicenseClient::selfHosted() && parent::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        $soon = License::where('status', 'active')->whereBetween('expires_at', [now(), now()->addDays(30)])->count();

        return $soon ? (string) $soon : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('تنتهي خلال 30 يومًا');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('العميل'))->columns(['default' => 1, 'md' => 3])->schema([
                TextInput::make('customer_name')->label(__('اسم العميل'))->required()->maxLength(190),
                TextInput::make('customer_email')->label(__('البريد الإلكتروني'))->email()->extraInputAttributes(['dir' => 'ltr']),
                TextInput::make('customer_phone')->label(__('الجوال'))->tel()->extraInputAttributes(['dir' => 'ltr']),
            ]),
            Section::make(__('الترخيص'))->columns(['default' => 1, 'md' => 3])->schema([
                Select::make('plan_id')->label(__('الباقة'))->relationship('plan', 'name_ar')->preload()->live()
                    ->helperText(__('تحدد المزايا الافتراضية والحدود.'))
                    ->afterStateUpdated(function (Set $set, $state) {
                        if ($plan = Plan::find($state)) {
                            $set('features', $plan->features ?? []);
                            $set('max_units', $plan->max_units);
                            $set('max_users', $plan->max_users);
                        }
                    }),
                DateTimePicker::make('starts_at')->label(__('يبدأ في'))->default(now()),
                DateTimePicker::make('expires_at')->label(__('ينتهي في'))->default(now()->addYear())
                    ->helperText(__('اتركه فارغًا لترخيص دائم.'))
                    ->hintActions([
                        Action::make('plusYear')->label(__('+ سنة'))->action(fn (Set $set, $get) => $set('expires_at', Carbon::parse($get('expires_at') ?: now())->addYear()->toDateTimeString())),
                    ]),
                DateTimePicker::make('support_until')->label(__('الدعم والتحديثات حتى')),
                Select::make('status')->label(__('الحالة'))->options(fn () => array_map('__', License::STATUSES))->default('active')->required(),
            ]),
            Section::make(__('الحدود'))->description(__('اترك أي حد فارغًا ليكون غير محدود.'))->columns(['default' => 1, 'md' => 4])->schema([
                TextInput::make('max_activations')->label(__('عدد السيرفرات'))->integer()->minValue(1)->default(1)->required()
                    ->helperText(__('على كم سيرفر يمكن تفعيله في نفس الوقت.')),
                TextInput::make('max_offices')->label(__('عدد المؤسسات'))->integer()->minValue(1)->default(1),
                TextInput::make('max_units')->label(__('الوحدات'))->integer()->minValue(1),
                TextInput::make('max_users')->label(__('المستخدمون'))->integer()->minValue(1),
                TagsInput::make('allowed_domains')->label(__('النطاقات المسموحة'))->columnSpanFull()
                    ->placeholder('alrowad.com.sa')->splitKeys(['Enter', ',', ' '])
                    ->helperText(__('اتركه فارغًا للسماح بأي نطاق. يمكن استخدام *.example.com.')),
            ]),
            Section::make(__('المزايا'))->schema([
                CheckboxList::make('features')->hiddenLabel()->options(fn () => array_map('__', PlanLimits::FEATURES))
                    ->columns(['default' => 1, 'md' => 3])->bulkToggleable()
                    ->helperText(__('بدون اختيار = مزايا الباقة.')),
            ]),
            Textarea::make('notes')->label(__('ملاحظات داخلية'))->rows(2),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $limit = fn ($v) => $v === null ? __('غير محدود') : number_format($v);

        return $schema->columns(1)->components([
            Section::make(__('الترخيص'))->columns(['default' => 1, 'md' => 4])->schema([
                TextEntry::make('key')->label(__('مفتاح الترخيص'))->copyable()->fontFamily('mono')->weight('bold')->columnSpan(2),
                TextEntry::make('status')->label(__('الحالة'))->badge()
                    ->formatStateUsing(fn ($state, License $record) => $record->isExpired() && $state === 'active' ? __('منتهي') : __(License::STATUSES[$state] ?? $state))
                    ->color(fn ($state, License $record) => $state === 'active' && ! $record->isExpired() ? 'success' : ($state === 'suspended' ? 'warning' : 'danger')),
                TextEntry::make('plan.name_ar')->label(__('الباقة'))->placeholder('-'),
                TextEntry::make('customer_name')->label(__('العميل')),
                TextEntry::make('customer_email')->label(__('البريد الإلكتروني'))->placeholder('-'),
                TextEntry::make('starts_at')->label(__('يبدأ في'))->date('Y-m-d'),
                TextEntry::make('expires_at')->label(__('ينتهي في'))->date('Y-m-d')->placeholder(__('دائم'))
                    ->color(fn ($state) => $state && Carbon::parse($state)->lt(now()->addDays(30)) ? 'danger' : null),
                TextEntry::make('max_activations')->label(__('السيرفرات'))
                    ->state(fn (License $record) => $record->activeActivations()->count().' / '.$record->max_activations),
                TextEntry::make('max_offices')->label(__('المؤسسات'))->formatStateUsing($limit)->placeholder(__('غير محدود')),
                TextEntry::make('max_units')->label(__('الوحدات'))->formatStateUsing($limit)->placeholder(__('غير محدود')),
                TextEntry::make('max_users')->label(__('المستخدمون'))->formatStateUsing($limit)->placeholder(__('غير محدود')),
                TextEntry::make('allowed_domains')->label(__('النطاقات المسموحة'))->badge()->placeholder(__('أي نطاق'))->columnSpan(2),
                TextEntry::make('features')->label(__('المزايا'))->badge()->color('gray')->columnSpanFull()
                    ->state(fn (License $record) => array_map(fn ($f) => __(PlanLimits::FEATURES[$f] ?? $f), $record->effectiveFeatures())),
                TextEntry::make('notes')->label(__('ملاحظات داخلية'))->placeholder('-')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $q) => $q->with('plan')->withCount(['activeActivations'])->withMax('activations', 'last_seen_at'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('customer_name')->label(__('العميل'))->searchable()->weight('bold')
                    ->description(fn (License $record) => $record->maskedKey()),
                TextColumn::make('plan.name_ar')->label(__('الباقة'))->badge()->color('gray')->placeholder('-'),
                TextColumn::make('status')->label(__('الحالة'))->badge()
                    ->formatStateUsing(fn ($state, License $record) => $record->isExpired() && $state === 'active' ? __('منتهي') : __(License::STATUSES[$state] ?? $state))
                    ->color(fn ($state, License $record) => $state === 'active' && ! $record->isExpired() ? 'success' : ($state === 'suspended' ? 'warning' : 'danger')),
                TextColumn::make('active_activations_count')->label(__('السيرفرات'))
                    ->formatStateUsing(fn ($state, License $record) => $state.' / '.$record->max_activations),
                TextColumn::make('expires_at')->label(__('ينتهي في'))->date('Y-m-d')->sortable()->placeholder(__('دائم'))
                    ->color(fn ($state) => $state && Carbon::parse($state)->lt(now()->addDays(30)) ? 'danger' : null),
                TextColumn::make('activations_max_last_seen_at')->label(__('آخر اتصال'))->since()->placeholder(__('لم يتصل'))
                    ->color(fn ($state) => $state && Carbon::parse($state)->lt(now()->subDays(3)) ? 'warning' : null),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('الحالة'))->options(fn () => array_map('__', License::STATUSES)),
                Filter::make('expiring')->label(__('تنتهي خلال 30 يومًا'))
                    ->query(fn (Builder $q) => $q->whereBetween('expires_at', [now(), now()->addDays(30)])),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([...self::lifecycleActions(), EditAction::make()]),
            ]);
    }

    /** Suspend, reactivate, renew, revoke and the setup instructions for the customer. */
    public static function lifecycleActions(): array
    {
        return [
            Action::make('instructions')->label(__('تعليمات الربط'))->icon(Heroicon::OutlinedCommandLine)->color('gray')
                ->modalHeading(__('ربط نسخة العميل بالترخيص'))->modalSubmitAction(false)->modalCancelActionLabel(__('إغلاق'))
                ->modalContent(fn (License $record) => view('filament.platform.license-instructions', ['license' => $record])),
            Action::make('renew')->label(__('تجديد'))->icon(Heroicon::OutlinedArrowPath)->color('success')
                ->schema([DateTimePicker::make('expires_at')->label(__('ينتهي في'))->required()
                    ->default(fn (License $record) => Carbon::parse(max($record->expires_at ?? now(), now()))->addYear())])
                ->action(function (License $record, array $data) {
                    $record->update(['expires_at' => $data['expires_at'], 'status' => $record->status === 'revoked' ? 'revoked' : 'active']);
                    Notification::make()->title(__('تم التجديد حتى :date', ['date' => Carbon::parse($data['expires_at'])->format('Y-m-d')]))->success()->send();
                }),
            Action::make('suspend')->label(__('إيقاف'))->icon(Heroicon::OutlinedPauseCircle)->color('warning')
                ->visible(fn (License $record) => $record->status === 'active')->requiresConfirmation()
                ->modalDescription(__('تتوقف نسخة العميل عند تحققها التالي من الترخيص (خلال يوم).'))
                ->action(fn (License $record) => $record->update(['status' => 'suspended'])),
            Action::make('reactivate')->label(__('تفعيل'))->icon(Heroicon::OutlinedPlayCircle)->color('success')
                ->visible(fn (License $record) => $record->status === 'suspended')
                ->action(fn (License $record) => $record->update(['status' => 'active'])),
            Action::make('revoke')->label(__('إلغاء الترخيص نهائيًا'))->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                ->visible(fn (License $record) => $record->status !== 'revoked')->requiresConfirmation()
                ->action(fn (License $record) => $record->update(['status' => 'revoked'])),
        ];
    }

    public static function getRelations(): array
    {
        return [RelationManagers\ActivationsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLicenses::route('/'),
            'create' => Pages\CreateLicense::route('/create'),
            'view' => Pages\ViewLicense::route('/{record}'),
            'edit' => Pages\EditLicense::route('/{record}/edit'),
        ];
    }
}
