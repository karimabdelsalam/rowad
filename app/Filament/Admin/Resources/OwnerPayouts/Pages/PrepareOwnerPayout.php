<?php

namespace App\Filament\Admin\Resources\OwnerPayouts\Pages;

use App\Exceptions\BusinessRuleException;
use App\Filament\Admin\Resources\OwnerPayouts\OwnerPayoutResource;
use App\Filament\Admin\Resources\Owners\OwnerResource;
use App\Models\BankAccount;
use App\Services\Payouts\OwnerPayouts;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

/** Choose the owners to pay: everyone with a positive net balance up to a date, ready ones ticked. */
class PrepareOwnerPayout extends Page
{
    protected static string $resource = OwnerPayoutResource::class;

    protected string $view = 'filament.admin.owner-payout-prepare';

    public ?array $data = [];

    /** @var list<int> owner ids */
    public array $selected = [];

    public static function canAccess(array $parameters = []): bool
    {
        return OwnerPayoutResource::canCreate();
    }

    public function getTitle(): string
    {
        return __('دفعة توزيع جديدة');
    }

    public function getSubheading(): ?string
    {
        return __('يُحسب لكل مالك صافي مستحقاته المفتوحة (حصته من الإيجارات بعد العمولة والمصروفات والضرائب)، ويصدر له سند صرف واحد، ثم تنزّل ملف التحويل لترفعه على بنكك.');
    }

    public function mount(): void
    {
        $this->form->fill([
            'dues_until' => today()->toDateString(),
            'issued_at' => today()->toDateString(),
            'bank_account_id' => BankAccount::query()->where('is_default', true)->value('id'),
            'minimum' => null,
        ]);
        $this->selectReady();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->columns(['default' => 1, 'md' => 2, 'xl' => 4])->components([
            DatePicker::make('dues_until')->label(__('المستحقات حتى تاريخ'))->required()->native(false)->live()
                ->afterStateUpdated(fn () => $this->selectReady()),
            TextInput::make('minimum')->label(__('أقل مبلغ للتحويل'))->numeric()->minValue(0)->placeholder('0')->live(onBlur: true)
                ->afterStateUpdated(fn () => $this->selectReady()),
            Select::make('bank_account_id')->label(__('التحويل من حساب'))->options(fn () => BankAccount::query()->pluck('holder_name', 'id'))
                ->placeholder(__('اختر الحساب البنكي'))->required(),
            DatePicker::make('issued_at')->label(__('تاريخ السندات'))->required()->native(false),
            TextInput::make('notes')->label(__('ملاحظات'))->maxLength(500)->columnSpanFull(),
        ]);
    }

    public function updatedSelected(): void
    {
        $this->selected = array_values(array_unique(array_map('intval', $this->selected)));
    }

    public function toggleAll(bool $on): void
    {
        $on ? $this->selectReady() : $this->selected = [];
    }

    public function create(): void
    {
        $state = $this->form->getState();
        try {
            $payout = app(OwnerPayouts::class)->prepare($this->selected, $state['dues_until'], BankAccount::find($state['bank_account_id']),
                $state['issued_at'], $state['notes'] ?? null);
        } catch (BusinessRuleException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }
        Notification::make()->success()->title(__('أُنشئت الدفعة :n — نزّل ملف التحويل وارفعه على البنك', ['n' => $payout->number]))->send();
        $this->redirect(OwnerPayoutResource::getUrl('view', ['record' => $payout]));
    }

    protected function getViewData(): array
    {
        $rows = $this->candidates();
        $chosen = $rows->filter(fn ($row) => in_array($row['owner']->id, $this->selected, true));

        return [
            'rows' => $rows,
            'chosenCount' => $chosen->count(),
            'chosenTotal' => $chosen->sum('net'),
            'ownerUrl' => fn ($owner) => OwnerResource::getUrl('edit', ['record' => $owner]),
        ];
    }

    private function candidates(): Collection
    {
        return app(OwnerPayouts::class)->candidates($this->data['dues_until'] ?? today(), (float) ($this->data['minimum'] ?? 0));
    }

    private function selectReady(): void
    {
        $this->selected = $this->candidates()->where('ready', true)->map(fn ($row) => $row['owner']->id)->values()->all();
    }
}
