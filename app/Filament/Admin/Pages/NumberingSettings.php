<?php

namespace App\Filament\Admin\Pages;

use App\Exceptions\BusinessRuleException;
use App\Services\DocumentNumberGenerator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Legacy home/serial: where each document's numbering continues from (e.g. after moving from paper or another system). */
class NumberingSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'numbering';

    public const LABELS = [
        'rent_contract' => 'عقود الإيجار',
        'sale_contract' => 'عقود البيع',
        'receipt_voucher' => 'سندات القبض',
        'payment_voucher' => 'سندات الصرف',
        'invoice' => 'الفواتير',
    ];

    /** @var array<string, int|string|null> */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('اعداد الترقيم');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    public function getTitle(): string
    {
        return __('اعداد الترقيم');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can('settings.update'));
    }

    public function mount(): void
    {
        $numbers = app(DocumentNumberGenerator::class);
        $this->form->fill(collect(self::LABELS)->mapWithKeys(fn ($label, $key) => [$key => $numbers->upcoming($key)])->all());
    }

    public function form(Schema $schema): Schema
    {
        $numbers = app(DocumentNumberGenerator::class);
        $year = (int) now()->format('Y');

        return $schema->statePath('data')->components([
            Section::make(__('الرقم التالي لكل مستند في :year', ['year' => $year]))
                ->description(__('يبدأ الترقيم من جديد كل سنة. يمكن رفع الرقم فقط، ولا يمكن الرجوع لرقم مستخدم.'))
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->schema(collect(self::LABELS)->map(fn (string $label, string $key) => TextInput::make($key)
                    ->label(__($label))
                    ->integer()->required()
                    ->minValue(fn () => $numbers->upcoming($key))
                    ->prefix(DocumentNumberGenerator::PREFIXES[$key].'-'.$year.'-')
                    ->helperText(fn ($state) => __('المستند القادم').': '.sprintf('%s-%d-%05d', DocumentNumberGenerator::PREFIXES[$key], $year, (int) $state))
                    ->live(onBlur: true))->values()->all()),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])->id('form')->livewireSubmitHandler('save')->footer([
                Actions::make([Action::make('save')->label(__('حفظ'))->submit('save')]),
            ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $numbers = app(DocumentNumberGenerator::class);

        try {
            foreach (array_keys(self::LABELS) as $key) {
                $numbers->startAt($key, (int) $data[$key]);
            }
        } catch (BusinessRuleException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title(__('تم الحفظ'))->success()->send();
    }
}
