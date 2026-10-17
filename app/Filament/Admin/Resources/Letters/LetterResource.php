<?php

namespace App\Filament\Admin\Resources\Letters;

use App\Enums\Calendar;
use App\Enums\LetterType;
use App\Filament\Admin\Resources\Letters\Pages\ManageLetters;
use App\Models\Letter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use App\Filament\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LetterResource extends Resource
{
    protected static ?string $model = Letter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'العقود';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'خطاب';

    protected static ?string $pluralModelLabel = 'الخطابات';

    protected static ?string $permission = 'letters';

    /** Legacy letters/add: common fields, then the fields of the chosen letter type (stored in data.<type>). */
    public static function form(Schema $schema): Schema
    {
        $type = fn (Get $get) => $get('type') instanceof LetterType ? $get('type') : LetterType::tryFrom((string) $get('type'));
        $sendTo = fn (string $prefix) => [
            Radio::make("{$prefix}.sendto")->label(__('موجه إلى'))->inline()->live()->default('random')
                ->options(['random' => __('إلى من يهمه الأمر'), 'person' => __('شخص أو جهة محددة')]),
            TextInput::make("{$prefix}.sendto_desc")->label(__('الصفة'))->placeholder(__('سعادة / المكرم'))
                ->visible(fn (Get $get) => $get("{$prefix}.sendto") === 'person' && $prefix === 'data.cancel'),
            TextInput::make("{$prefix}.sendto_name")->label(__('الاسم'))->visible(fn (Get $get) => $get("{$prefix}.sendto") === 'person'),
            TextInput::make("{$prefix}.sendto_title")->label(__('اللقب / الجهة'))->visible(fn (Get $get) => $get("{$prefix}.sendto") === 'person'),
        ];

        return $schema->columns(3)->components([
            Select::make('type')->label(__('نوع الخطاب'))->options(LetterType::class)->required()->live()->columnSpan(1),
            Select::make('calendar')->options(Calendar::class)->default(fn () => \App\Support\Settings::get('system_calendar', 'gregorian'))->required(),
            DatePicker::make('letter_date')->label(__('تاريخ الخطاب'))->default(today())->required(),

            Select::make('unit_id')->label(__('الوحدة'))->relationship('unit', 'title')->searchable()->preload()
                ->required(fn (Get $get) => $type($get)?->needsTenant() ?? false)
                ->visible(fn (Get $get) => $type($get)?->needsTenant() ?? false),
            Select::make('client_id')->label(__('المستأجر'))->relationship('client', 'full_name')->searchable()->preload()
                ->required(fn (Get $get) => $type($get)?->needsTenant() ?? false)
                ->visible(fn (Get $get) => $type($get)?->needsTenant() ?? false),

            Section::make(__('الخطاب الرسمي'))->columns(2)->columnSpanFull()
                ->visible(fn (Get $get) => $type($get) === LetterType::Official)
                ->schema([...$sendTo('data.official'), RichEditor::make('data.official.content')->label(__('نص الخطاب'))->required()->columnSpanFull()
                    ->hintAction(\App\Filament\Forms\AiWriteAction::make('letter'))]),
            Section::make(__('إخلاء الطرف'))->columns(2)->columnSpanFull()
                ->visible(fn (Get $get) => $type($get) === LetterType::Clearance)
                ->schema($sendTo('data.cancel')),
            Section::make(__('تغيير الإيجار'))->columnSpanFull()
                ->visible(fn (Get $get) => $type($get) === LetterType::RentChange)
                ->schema([TextInput::make('rent_raise_amount')->label(__('قيمة الإيجار الجديدة'))->numeric()->minValue(0)->required()]),
            Section::make(__('تقييم العقار'))->columns(3)->columnSpanFull()
                ->visible(fn (Get $get) => $type($get) === LetterType::Evaluation)
                ->schema([
                    TextInput::make('data.review.provider')->label(__('اسم مقدم الطلب'))->required(),
                    Select::make('data.review.provider_title')->label(__('صفته'))->live()->default('owner')
                        ->options(['owner' => __('المالك'), 'agent' => __('وكيل شرعي')]),
                    TextInput::make('data.review.agent_name')->label(__('وكيلًا عن'))->visible(fn (Get $get) => $get('data.review.provider_title') === 'agent'),
                    Select::make('data.review.city_id')->label(__('المدينة'))->options(fn () => \App\Models\City::orderBy('name')->pluck('name', 'id'))->searchable(),
                    Select::make('data.review.district_id')->label(__('الحي'))->options(fn () => \App\Models\District::orderBy('name')->pluck('name', 'id'))->searchable(),
                    TextInput::make('data.review.size')->label(__('المساحة (م²)'))->numeric(),
                    TextInput::make('data.review.planno')->label(__('رقم المخطط')),
                    TextInput::make('data.review.plotno')->label(__('رقم القطعة')),
                    TextInput::make('data.review.amount')->label(__('قيمة التقييم'))->numeric()->required(),
                    Textarea::make('data.review.about')->label(__('وصف العقار'))->rows(3)->columnSpanFull(),
                    ...collect(\App\Models\UnitPlot::SIDES)->map(fn (string $side) => \Filament\Schemas\Components\Fieldset::make(__(['north' => 'الحد الشمالي', 'east' => 'الحد الشرقي', 'south' => 'الحد الجنوبي', 'west' => 'الحد الغربي'][$side]))
                        ->columns(4)->columnSpanFull()->schema([
                            Select::make("data.review.plot.{$side}_type")->label(__('نوع الحد'))
                                ->options(fn () => \App\Models\Lookups\BorderType::query()->whereNotNull('code')->pluck('title', 'code')),
                            TextInput::make("data.review.plot.{$side}_length")->label(__('الطول (م)'))->numeric(),
                            TextInput::make("data.review.plot.{$side}_width")->label(__('العرض (م)'))->numeric(),
                            TextInput::make("data.review.plot.{$side}_plotno")->label(__('رقم القطعة المجاورة')),
                        ]))->all(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->with(['unit', 'client']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('type')->label(__('نوع الخطاب'))->badge(),
                TextColumn::make('letter_date')->label(__('تاريخ الخطاب'))->date('Y-m-d')->sortable(),
                TextColumn::make('unit.title')->label(__('الوحدة'))->searchable()->placeholder('—'),
                TextColumn::make('client.full_name')->label(__('المستأجر'))->searchable()->placeholder('—'),
                TextColumn::make('created_at')->dateTime('Y-m-d')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->label(__('نوع الخطاب'))->options(LetterType::class),
            ])
            ->recordActions([
                Action::make('print')
                    ->label(__('طباعة'))
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(fn (Letter $record) => route('documents.print', ['type' => 'letter', 'id' => $record->getKey()]))
                    ->openUrlInNewTab(),
                \App\Filament\Support\DocumentActions::pdf('letter'),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLetters::route('/'),
        ];
    }
}
