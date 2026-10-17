<?php

namespace App\Filament\Admin\Resources\Units;

use App\Models\Unit;
use App\Services\Ejar\EjarApi;
use App\Services\Ejar\EjarException;
use App\Services\Ejar\EjarPortfolio;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * "تسجيل في إيجار": registers the unit (and its property, the first time) in the office's Ejar portfolio,
 * from a three-step form prefilled with what the system already knows. What the office adds is kept on the
 * building / unit, so the next unit of the same building only needs its own details.
 */
final class EjarRegisterAction
{
    public static function make(): Action
    {
        return Action::make('ejarRegister')
            ->label(__('تسجيل في إيجار'))
            ->icon(Heroicon::OutlinedCloudArrowUp)
            ->color('primary')
            ->visible(fn (Unit $record) => EjarApi::connected() && self::can() && blank($record->ejar_unit_id))
            ->modalHeading(fn (Unit $record) => EjarPortfolio::propertyId($record)
                ? __('تسجيل الوحدة :unit في إيجار', ['unit' => $record->number ?: $record->title])
                : __('تسجيل العقار والوحدة في إيجار'))
            ->modalDescription(fn (Unit $record) => ($problems = EjarPortfolio::problems($record)) === []
                ? __('البيانات معبأة مما في النظام؛ راجعها وأكمل الناقص. تُحفظ لتسجيل باقي وحدات العقار دون إعادة إدخال.')
                : new \Illuminate\Support\HtmlString(e(__('أكمل ما يلي أولًا:')).'<ul style="margin:6px 0 0;padding-inline-start:20px;list-style:disc;color:#b45309">'
                    .collect($problems)->map(fn ($p) => '<li>'.e($p).'</li>')->join('').'</ul>'))
            ->modalWidth('5xl')
            ->fillForm(fn (Unit $record) => ['property' => EjarPortfolio::propertyState($record), 'unit' => EjarPortfolio::unitState($record)])
            ->steps(fn (Unit $record) => [
                Step::make(__('صك الملكية'))->icon(Heroicon::OutlinedDocumentCheck)
                    ->visible(fn () => EjarPortfolio::propertyId($record) === null)
                    ->schema([Group::make(self::deedFields($record))->statePath('property')]),
                Step::make(__('العقار'))->icon(Heroicon::OutlinedBuildingOffice2)
                    ->visible(fn () => EjarPortfolio::propertyId($record) === null)
                    ->schema([Group::make(self::propertyFields())->statePath('property')]),
                Step::make(__('الوحدة'))->icon(Heroicon::OutlinedHome)
                    ->schema([
                        Text::make(fn () => EjarPortfolio::propertyId($record) ? __('العقار مسجل في إيجار؛ تُضاف الوحدة إليه.') : null),
                        Group::make(self::unitFields())->statePath('unit'),
                    ]),
            ])
            ->modalSubmitAction(fn (Action $action, Unit $record) => $action->disabled(EjarPortfolio::problems($record) !== []))
            ->modalSubmitActionLabel(__('تسجيل في إيجار'))
            ->action(function (Unit $record, array $data) {
                $property = (array) ($data['property'] ?? []);
                $scans = collect((array) ($property['scans'] ?? []))
                    ->filter(fn ($file) => $file instanceof TemporaryUploadedFile)
                    ->map(fn (TemporaryUploadedFile $file) => ['name' => $file->getClientOriginalName(), 'mime' => (string) $file->getMimeType(), 'content' => $file->get()])
                    ->values()->all();
                unset($property['scans']);

                try {
                    EjarPortfolio::make()->register($record, $property, (array) ($data['unit'] ?? []), $scans);
                    Notification::make()->success()->title(__('سُجلت الوحدة في إيجار'))
                        ->body(__('يمكن الآن إرسال عقود هذه الوحدة إلى إيجار من صفحة العقد.'))->persistent()->send();
                } catch (EjarException $e) {
                    Notification::make()->danger()->title(__('رفضت منصة إيجار التسجيل'))
                        ->body(implode("\n", array_slice([$e->getMessage(), ...$e->messages], 0, 6))."\n".__('البيانات محفوظة؛ صحّحها وأعد المحاولة.'))->persistent()->send();
                }
            });
    }

    /** Registers the selected units of buildings already on Ejar, each from its saved (or default) details. */
    public static function bulk(): BulkAction
    {
        return BulkAction::make('ejarRegisterUnits')
            ->label(__('تسجيل في إيجار'))
            ->icon(Heroicon::OutlinedCloudArrowUp)
            ->visible(fn () => EjarApi::connected() && self::can())
            ->requiresConfirmation()
            ->modalDescription(__('تُسجل كل وحدة محددة تحت عقارها المسجل في إيجار ببياناتها المحفوظة. الوحدات التي لم يُسجل عقارها بعد تُتخطى؛ سجّل أول وحدة منها من صفحتها.'))
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records) {
                $portfolio = EjarPortfolio::make();
                [$done, $skipped, $failed] = [0, 0, []];
                foreach ($records as $unit) {
                    if (filled($unit->ejar_unit_id) || EjarPortfolio::propertyId($unit) === null) {
                        $skipped++;

                        continue;
                    }
                    try {
                        $portfolio->registerUnit($unit);
                        $done++;
                    } catch (EjarException $e) {
                        $failed[] = ($unit->number ?: $unit->title).': '.trim($e->getMessage().' '.implode(' ', $e->messages));
                    }
                }
                Notification::make()->{$failed ? 'warning' : 'success'}()
                    ->title(__('سُجلت :done وحدة في إيجار', ['done' => $done]))
                    ->body(trim(($skipped ? __('تُخطيت :n (مسجلة أو عقارها غير مسجل).', ['n' => $skipped])."\n" : '').implode("\n", array_slice($failed, 0, 5))))
                    ->persistent()->send();
            });
    }

    /** @return list<\Filament\Schemas\Components\Component> */
    private static function deedFields(Unit $unit): array
    {
        $owners = $unit->owners()->get();

        return [
            Text::make(__('يُسجل العقار باسم :owners كما في إيجار؛ يتحقق النظام من هويتهم قبل الإرسال.', ['owners' => $owners->pluck('full_name')->join('، ') ?: '—'])),
            Grid::make(['default' => 1, 'md' => 3])->schema([
                TextInput::make('document_number')->label(__('رقم الصك / الوثيقة'))->required()->maxLength(40),
                DatePicker::make('issued_date')->label(__('تاريخ الصك (ميلادي)'))->required()->maxDate(today()),
                Select::make('ownership_document_type')->label(__('نوع الوثيقة'))->required()->native(false)->live()
                    ->options(self::options('ownership_document_types')),
                TextInput::make('issue_place')->label(__('مكان الإصدار'))->maxLength(120),
                TextInput::make('issued_by')->label(__('جهة الإصدار'))->maxLength(120)->placeholder(__('كتابة العدل / وزارة العدل')),
                TextInput::make('legal_document_type_name')->label(__('اسم نوع الوثيقة'))->maxLength(120),
            ]),
            FileUpload::make('scans')->label(__('صورة الوثيقة'))->multiple()->maxFiles(5)->storeFiles(false)
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(5120)
                ->helperText(__('مطلوبة لغير الصكوك الإلكترونية؛ إيجار يقرأ الصك الإلكتروني بنفسه.'))
                ->visible(fn (Get $get) => ! in_array($get('ownership_document_type'), config('ejar.electronic_documents'), true))
                ->required(fn (Get $get) => ! in_array($get('ownership_document_type'), config('ejar.electronic_documents'), true)),
        ];
    }

    /** @return list<\Filament\Schemas\Components\Component> */
    private static function propertyFields(): array
    {
        return [
            Grid::make(['default' => 1, 'md' => 4])->schema([
                TextInput::make('property_name')->label(__('اسم العقار'))->required()->maxLength(120)->columnSpan(2),
                TextInput::make('property_number')->label(__('رقم العقار'))->maxLength(4)->regex('/^\d{1,4}$/')->helperText(__('حتى 4 أرقام')),
                Select::make('property_type')->label(__('نوع العقار'))->required()->native(false)->options(self::options('property_types')),
                Select::make('property_usage')->label(__('الاستخدام'))->required()->native(false)->options(self::options('usages')),
                TextInput::make('total_floors')->label(__('عدد الأدوار'))->required()->integer()->minValue(1)->maxValue(200),
                TextInput::make('units_per_floor')->label(__('الوحدات في الدور'))->required()->integer()->minValue(1)->maxValue(200),
                DatePicker::make('established_date')->label(__('تاريخ الإنشاء'))->maxDate(today()),
            ]),
            Fieldset::make(__('العنوان الوطني'))->columns(1)->schema([Grid::make(['default' => 1, 'md' => 4])->schema([
                Select::make('region')->label(__('المنطقة'))->required()->native(false)->searchable()->options(self::options('regions')),
                TextInput::make('city')->label(__('المدينة'))->required()->maxLength(80),
                TextInput::make('district')->label(__('الحي'))->required()->maxLength(80),
                TextInput::make('street_name')->label(__('الشارع'))->required()->maxLength(120),
                TextInput::make('building_number')->label(__('رقم المبنى'))->required()->regex('/^\d{4}$/')->length(4)->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'numeric']),
                TextInput::make('postal_code')->label(__('الرمز البريدي'))->required()->regex('/^\d{5}$/')->length(5)->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'numeric']),
                TextInput::make('additional_number')->label(__('الرقم الإضافي'))->required()->regex('/^\d{4}$/')->length(4)->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'numeric']),
                Text::make(__('من شهادة العنوان الوطني (سبل) للعقار.')),
            ])]),
            Fieldset::make(__('المرافق'))->columns(1)->schema([Grid::make(['default' => 2, 'md' => 6])->schema(
                collect(config('ejar.facilities'))->map(fn ($label, $key) => TextInput::make("facilities.{$key}")->label(__($label))->integer()->minValue(0)->maxValue(999)->default(0))->values()->all()
            )]),
        ];
    }

    /** @return list<\Filament\Schemas\Components\Component> */
    private static function unitFields(): array
    {
        return [
            Grid::make(['default' => 1, 'md' => 4])->schema([
                TextInput::make('unit_number')->label(__('رقم الوحدة'))->required()->maxLength(20),
                TextInput::make('floor_number')->label(__('الدور'))->required()->maxLength(10)->helperText(__('G للأرضي')),
                Select::make('unit_type')->label(__('نوع الوحدة'))->required()->native(false)->options(self::options('unit_types')),
                Select::make('unit_usage')->label(__('الاستخدام'))->required()->native(false)->options(self::options('usages')),
                TextInput::make('area')->label(__('المساحة (م²)'))->required()->numeric()->minValue(1),
                TextInput::make('rooms')->label(__('عدد الغرف'))->integer()->minValue(0)->maxValue(99),
                Select::make('unit_direction')->label(__('الاتجاه'))->required()->native(false)->options(self::options('directions')),
                Select::make('unit_finishing')->label(__('التشطيب'))->required()->native(false)->options(self::options('finishings')),
                Toggle::make('is_furnished')->label(__('مفروشة'))->inline(false)->live(),
                Select::make('furnish_type')->label(__('نوع الفرش'))->native(false)->options(self::options('furnish_types'))
                    ->visible(fn (Get $get) => (bool) $get('is_furnished'))->required(fn (Get $get) => (bool) $get('is_furnished')),
                Toggle::make('include_mezzanine')->label(__('بها ميزانين'))->inline(false),
                DatePicker::make('established_date')->label(__('تاريخ الإنشاء'))->maxDate(today()),
            ]),
            Fieldset::make(__('العدادات'))->columns(1)->schema([Grid::make(['default' => 1, 'md' => 3])->schema([
                TextInput::make('electricity_meter')->label(__('رقم عداد الكهرباء'))->maxLength(40)->extraInputAttributes(['dir' => 'ltr']),
                TextInput::make('water_meter')->label(__('رقم عداد المياه'))->maxLength(40)->extraInputAttributes(['dir' => 'ltr']),
                TextInput::make('gas_meter')->label(__('رقم عداد الغاز'))->maxLength(40)->extraInputAttributes(['dir' => 'ltr']),
            ])]),
            Fieldset::make(__('مكونات الوحدة'))->columns(1)->schema([Grid::make(['default' => 2, 'md' => 6])->schema([
                ...collect(config('ejar.amenities'))->map(fn ($label, $key) => TextInput::make("amenities.{$key}")->label(__($label))->integer()->minValue(0)->maxValue(99)->default(0))->values()->all(),
                ...collect(config('ejar.fixtures'))->map(fn ($label, $key) => Toggle::make("fixtures.{$key}")->label(__($label))->inline(false))->values()->all(),
            ])]),
            Fieldset::make(__('الأبعاد (اختياري، بالمتر)'))->columns(1)->schema([Grid::make(3)->schema([
                TextInput::make('dimensions.length')->label(__('الطول'))->numeric()->minValue(0),
                TextInput::make('dimensions.width')->label(__('العرض'))->numeric()->minValue(0),
                TextInput::make('dimensions.height')->label(__('الارتفاع'))->numeric()->minValue(0),
            ])]),
        ];
    }

    /** @return array<string, string> */
    private static function options(string $list): array
    {
        return collect(config("ejar.{$list}"))->map(fn ($label) => __($label))->all();
    }

    private static function can(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can('units.update'));
    }
}
