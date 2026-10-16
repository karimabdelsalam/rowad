<?php

namespace App\Filament\Admin\Resources\Viewings;

use App\Enums\ViewingStatus as S;
use App\Filament\Admin\Resources\Clients\ClientResource;
use App\Filament\Admin\Resources\Units\UnitResource;
use App\Filament\Admin\Resources\Viewings\Pages\ManageViewings;
use App\Filament\Resource;
use App\Models\User;
use App\Models\Viewing;
use App\Services\Viewings\ViewingSchedule;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** مواعيد المعاينة: booked from the website or by staff, with the outcome of each visit. */
class ViewingResource extends Resource
{
    protected static ?string $model = Viewing::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'التشغيل';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'موعد معاينة';

    protected static ?string $pluralModelLabel = 'مواعيد المعاينة';

    protected static ?string $permission = 'viewings';

    public static function getNavigationBadge(): ?string
    {
        $today = ViewingSchedule::now();
        $n = Viewing::query()->where('status', S::Booked)->whereBetween('starts_at', [$today->startOfDay(), $today->endOfDay()])->count();

        return $n ? (string) $n : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'md' => 2])->schema([
                Select::make('unit_id')->label(__('الوحدة'))->relationship('unit', 'title')->searchable()->preload()->required()->columnSpanFull(),
                DateTimePicker::make('starts_at')->label(__('الموعد'))->seconds(false)->minutesStep(15)->required()
                    ->default(fn () => ViewingSchedule::now()->addDay()->setTime(17, 0)),
                Select::make('assigned_to')->label(__('الموظف المرافق'))->native(false)
                    ->options(fn () => User::query()->where('type', 'staff')->where('is_active', true)->pluck('name', 'id'))->default(fn () => auth()->id()),
                TextInput::make('name')->label(__('اسم الزائر'))->required()->maxLength(100),
                TextInput::make('mobile')->label(__('الجوال'))->tel()->required()->maxLength(30),
                Textarea::make('notes')->label(__('ملاحظات'))->rows(2)->maxLength(1000)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['unit', 'assignee', 'client']))
            ->defaultSort('starts_at')
            ->columns([
                TextColumn::make('starts_at')->label(__('الموعد'))->sortable()->weight('bold')
                    ->formatStateUsing(fn ($state) => $state->locale(app()->getLocale())->translatedFormat('D j M').' · '.$state->format('H:i'))
                    ->description(fn (Viewing $r) => $r->starts_at->isToday() ? __('اليوم') : ($r->starts_at->isTomorrow() ? __('غدًا') : null))
                    ->color(fn (Viewing $r) => $r->status === S::Booked && $r->starts_at->isToday() ? 'primary' : null),
                TextColumn::make('unit.title')->label(__('الوحدة'))->searchable()
                    ->url(fn (Viewing $r) => $r->unit ? UnitResource::getUrl('view', ['record' => $r->unit]) : null),
                TextColumn::make('name')->label(__('الزائر'))->searchable(['name', 'mobile'])
                    ->description(fn (Viewing $r) => $r->mobile)
                    ->url(fn (Viewing $r) => $r->client_id ? ClientResource::getUrl('view', ['record' => $r->client_id]) : null),
                TextColumn::make('status')->label(__('الحالة'))->badge(),
                TextColumn::make('source')->label(__('المصدر'))->badge()->color('gray')
                    ->formatStateUsing(fn ($state) => $state === 'website' ? __('موقع المكتب') : __('المكتب')),
                TextColumn::make('assignee.name')->label(__('المرافق'))->placeholder('-')->toggleable(),
                TextColumn::make('notes')->label(__('ملاحظات'))->limit(40)->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('الحالة'))->options(S::class),
                SelectFilter::make('unit_id')->label(__('الوحدة'))->relationship('unit', 'title')->searchable()->preload(),
            ])
            ->recordActions([
                self::outcome(S::Done, Heroicon::OutlinedCheckCircle, 'success'),
                ActionGroup::make([
                    self::outcome(S::NoShow, Heroicon::OutlinedUserMinus, 'warning'),
                    Action::make('reschedule')->label(__('تغيير الموعد'))->icon(Heroicon::OutlinedCalendarDays)
                        ->visible(fn (Viewing $r) => $r->status === S::Booked && self::allowed('update'))
                        ->fillForm(fn (Viewing $r) => ['starts_at' => $r->starts_at])
                        ->schema([DateTimePicker::make('starts_at')->label(__('الموعد الجديد'))->seconds(false)->minutesStep(15)->required()])
                        ->action(function (Viewing $r, array $data) {
                            app(ViewingSchedule::class)->reschedule($r, (string) $data['starts_at']);
                            Notification::make()->success()->title(__('تم تغيير الموعد وإبلاغ الزائر'))->send();
                        }),
                    Action::make('toClient')->label(__('تسجيله كعميل'))->icon(Heroicon::OutlinedUserPlus)
                        ->visible(fn (Viewing $r) => $r->client_id === null && self::allowed('update'))
                        ->action(function (Viewing $r) {
                            $client = app(ViewingSchedule::class)->toClient($r);
                            Notification::make()->success()->title(__('تم تسجيل :name كعميل', ['name' => $client->displayName()]))
                                ->actions([Action::make('open')->label(__('فتح'))->url(ClientResource::getUrl('view', ['record' => $client]))])->send();
                        }),
                    Action::make('cancel')->label(__('إلغاء الموعد'))->icon(Heroicon::OutlinedXCircle)->color('danger')
                        ->visible(fn (Viewing $r) => $r->status === S::Booked && self::allowed('update'))
                        ->schema([TextInput::make('reason')->label(__('السبب (يصل للزائر)'))->maxLength(200)])
                        ->action(function (Viewing $r, array $data) {
                            app(ViewingSchedule::class)->cancel($r, $data['reason'] ?? null);
                            Notification::make()->success()->title(__('تم إلغاء الموعد وإبلاغ الزائر'))->send();
                        }),
                ]),
            ]);
    }

    private static function outcome(S $status, Heroicon $icon, string $color): Action
    {
        return Action::make('mark_'.$status->value)->label($status->getLabel())->icon($icon)->color($color)
            ->visible(fn (Viewing $r) => $r->status === S::Booked && $r->starts_at->lte(ViewingSchedule::now()->endOfDay()) && self::allowed('update'))
            ->action(fn (Viewing $r) => app(ViewingSchedule::class)->outcome($r, $status));
    }

    private static function allowed(string $ability): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can("viewings.{$ability}"));
    }

    public static function getPages(): array
    {
        return ['index' => ManageViewings::route('/')];
    }
}
