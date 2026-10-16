<?php

namespace App\Filament\Admin\Pages;

use App\Models\Client;
use App\Models\Owner;
use App\Models\RentContract;
use App\Models\Unit;
use App\Services\Import\DataImport as Imports;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

/**
 * Moving to the system from Excel: the office downloads a template per kind of record, fills it (or pastes its
 * existing sheet under the same headers), previews the problems row by row, then imports what is ready.
 */
class DataImport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'import';

    protected string $view = 'filament.admin.data-import';

    public ?array $data = [];

    /** The uploaded file, kept on the local disk between the preview and the import. */
    public ?string $file = null;

    public ?array $preview = null;

    public ?array $result = null;

    public static function getNavigationLabel(): string
    {
        return __('استيراد البيانات');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    public function getTitle(): string
    {
        return __('استيراد البيانات من Excel');
    }

    public function getSubheading(): ?string
    {
        return __('انقل بياناتك من نظامك السابق أو جداولك: نزّل القالب، املأه، ثم راجع المعاينة قبل الحفظ. لا يتكرر سجل موجود عند إعادة رفع الملف.');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return tenancy()->initialized && $user !== null && ($user->is_super_admin || $user->can('settings.update'));
    }

    public function mount(): void
    {
        $this->form->fill(['type' => 'owners', 'update_existing' => false, 'collect_past' => true]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Hidden::make('type')->required(),
            FileUpload::make('upload')->label(__('ملف Excel أو CSV'))->required()
                ->disk('local')->directory('imports')->visibility('private')->maxSize(10240)
                ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file) => Str::uuid().'.'.(strtolower($file->getClientOriginalExtension()) === 'csv' ? 'csv' : 'xlsx'))
                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'])
                ->helperText(__('الصف الأول عناوين الأعمدة كما في القالب، وحتى :n صف في الملف الواحد.', ['n' => number_format(\App\Services\Import\Spreadsheet::MAX_ROWS)])),
            Toggle::make('update_existing')->label(__('تحديث السجلات الموجودة ببيانات الملف'))
                ->helperText(__('يُطابق المالك والعميل برقم الهوية ثم الجوال، والوحدة باسمها داخل العقار. عند إيقافه تُترك السجلات الموجودة كما هي.'))
                ->visible(fn (Get $get) => $get('type') !== 'rent_contracts'),
            Toggle::make('collect_past')->label(__('اعتبار الدفعات المستحقة قبل اليوم محصّلة'))
                ->helperText(__('للعقود المنقولة من نظام سابق: تُغلق الدفعات الماضية دون سندات قبض أو قيود على الملاك، وتبقى القادمة للتحصيل.'))
                ->visible(fn (Get $get) => $get('type') === 'rent_contracts'),
        ]);
    }

    /** Check the file and show what will happen, before anything is saved. */
    public function check(): void
    {
        $state = $this->form->getState();
        $this->discardFile();
        $this->file = $state['upload'];
        $this->result = null;
        try {
            $this->preview = app(Imports::class)->preview($state['type'], $this->path(), $this->extension());
        } catch (\Throwable $e) {
            report($e);
            $this->discardFile();
            Notification::make()->danger()->title(__('تعذّرت قراءة الملف؛ احفظه بصيغة xlsx أو CSV (UTF-8) وأعد المحاولة'))->send();
        }
    }

    public function runImport(): void
    {
        if ($this->file === null || ! Storage::disk('local')->exists($this->file)) {
            $this->reset(['preview', 'file']);

            return;
        }
        $state = $this->data;
        $this->result = app(Imports::class)->run($state['type'], $this->path(), $this->extension(), [
            'update_existing' => (bool) ($state['update_existing'] ?? false),
            'collect_past' => (bool) ($state['collect_past'] ?? false),
        ]);
        activity()->event('import')->withProperties(['type' => $state['type']] + array_diff_key($this->result, ['failed' => 1]))
            ->log('data imported');
        $this->discardFile();
        $this->preview = null;
        $this->form->fill(['type' => $state['type'], 'update_existing' => $state['update_existing'] ?? false, 'collect_past' => $state['collect_past'] ?? true]);

        Notification::make()->success()->title(__('اكتمل الاستيراد: :created جديد، :updated محدَّث، :skipped موجود مسبقًا', [
            'created' => $this->result['created'], 'updated' => $this->result['updated'], 'skipped' => $this->result['skipped'],
        ]))->send();
    }

    public function choose(string $type): void
    {
        if (array_key_exists($type, Imports::types())) {
            $this->discardFile();
            $this->reset(['preview', 'result']);
            $this->form->fill(['type' => $type, 'update_existing' => $this->data['update_existing'] ?? false, 'collect_past' => $this->data['collect_past'] ?? true]);
        }
    }

    public function startOver(): void
    {
        $this->discardFile();
        $this->reset(['preview', 'result']);
        $this->form->fill(['type' => $this->data['type'] ?? 'owners', 'update_existing' => false, 'collect_past' => true]);
    }

    public function templateAction(): Action
    {
        return Action::make('template')
            ->label(__('تنزيل القالب'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->link()
            ->size('sm')
            ->action(function (array $arguments) {
                $type = (string) ($arguments['type'] ?? 'owners');

                return response()->download(Imports::template($type), __('قالب :title', ['title' => Imports::types()[$type] ?? $type]).'.xlsx')->deleteFileAfterSend();
            });
    }

    protected function getViewData(): array
    {
        return [
            'types' => Imports::types(),
            'type' => $this->data['type'] ?? 'owners',
            'counts' => ['owners' => Owner::count(), 'clients' => Client::count(), 'units' => Unit::count(),
                'rent_contracts' => RentContract::query()->where('status', 'active')->count()],
            'columns' => collect(Imports::importer($this->data['type'] ?? 'owners')::columns())
                ->map(fn ($c) => ['label' => __($c[0]), 'required' => $c[1]])->values()->all(),
        ];
    }

    private function path(): string
    {
        return Storage::disk('local')->path((string) $this->file);
    }

    private function extension(): string
    {
        return strtolower(pathinfo((string) $this->file, PATHINFO_EXTENSION)) === 'csv' ? 'csv' : 'xlsx';
    }

    private function discardFile(): void
    {
        if ($this->file !== null) {
            Storage::disk('local')->delete($this->file);
            $this->file = null;
        }
    }
}
