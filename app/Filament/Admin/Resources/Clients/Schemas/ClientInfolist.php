<?php

namespace App\Filament\Admin\Resources\Clients\Schemas;

use App\Enums\ClientKind;
use App\Enums\ContractStatus;
use App\Filament\Admin\Resources\Installments\InstallmentResource;
use App\Filament\Admin\Resources\RentContracts\RentContractResource;
use App\Filament\Admin\Resources\SaleContracts\SaleContractResource;
use App\Filament\Infolists\Parts;
use App\Models\Client;
use App\Models\Installment;
use App\Models\RentContract;
use App\Models\SaleContract;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/** Client (tenant / buyer) card: identity, contracts, what they still owe, company details and contact. */
class ClientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Parts::header(Heroicon::OutlinedUser, function (Client $r) {
                $due = self::dues($r);
                $overdue = (float) (clone $due)->whereDate('due_date', '<', today())->sum('amount');
                $outstanding = (float) $due->sum('amount');

                return [
                    'kicker' => ($r->kind?->getLabel() ?? __('عميل')).($r->category ? ' · '.$r->category->title : ''),
                    'title' => $r->displayName(),
                    'sub' => [$r->kind === ClientKind::Company ? $r->full_name : null, $r->id_type?->getLabel().($r->id_number ? ' '.$r->id_number : ''), $r->mobile,
                        str_contains((string) $r->ejar_verification, 'succeed') ? '✓ '.__('متحقق في إيجار') : null],
                    'facts' => [
                        Parts::fact('عقود سارية', $r->rentContracts()->where('status', ContractStatus::Active)->count() + $r->saleContracts()->where('status', ContractStatus::Active)->count()),
                        Parts::fact('المستحق عليه', Parts::amount($outstanding), $outstanding > 0 ? 'warning' : 'success'),
                        Parts::fact('متأخرات', Parts::amount($overdue), $overdue > 0 ? 'danger' : 'success'),
                    ],
                ];
            }),
            Tabs::make('client')->contained(false)->persistTabInQueryString()->tabs([
                Tab::make(__('العقود والدفعات'))->icon(Heroicon::OutlinedDocumentText)->schema([
                    Grid::make(['default' => 1, 'lg' => 3])->schema([
                        Grid::make(1)->columnSpan(['lg' => 2])->schema([
                            Section::make(__('عقود الإيجار'))->icon(Heroicon::OutlinedDocumentText)
                                ->visible(fn (Client $r) => $r->rentContracts->isNotEmpty())
                                ->schema([
                                    RepeatableEntry::make('rentContracts')->hiddenLabel()->state(fn (Client $r) => $r->rentContracts()->with('unit')->get())->columns(['default' => 2, 'md' => 5])->schema([
                                        Parts::link('number', 'العقد', fn ($record) => RentContractResource::getUrl('view', ['record' => $record])),
                                        TextEntry::make('unit.title')->label(__('الوحدة'))->placeholder('-')->columnSpan(2),
                                        TextEntry::make('status')->label(__('الحالة'))->badge(),
                                        TextEntry::make('period')->label(__('المدة'))
                                            ->state(fn (RentContract $record) => $record->start_date?->format('Y-m-d').' ← '.$record->end_date?->format('Y-m-d')),
                                    ]),
                                ]),
                            Section::make(__('عقود البيع'))->icon(Heroicon::OutlinedDocumentCheck)
                                ->visible(fn (Client $r) => $r->saleContracts->isNotEmpty())
                                ->schema([
                                    RepeatableEntry::make('saleContracts')->hiddenLabel()->state(fn (Client $r) => $r->saleContracts()->with('unit')->get())->columns(['default' => 2, 'md' => 5])->schema([
                                        Parts::link('number', 'العقد', fn ($record) => SaleContractResource::getUrl('view', ['record' => $record])),
                                        TextEntry::make('unit.title')->label(__('الوحدة'))->placeholder('-')->columnSpan(2),
                                        TextEntry::make('status')->label(__('الحالة'))->badge(),
                                        Parts::money('total_amount', 'السعر'),
                                    ]),
                                ]),
                            Section::make(__('الدفعات المستحقة'))->icon(Heroicon::OutlinedCalendarDays)
                                ->visible(fn (Client $r) => self::dues($r)->exists())
                                ->headerActions([
                                    \Filament\Actions\Action::make('allInstallments')->label(__('كل الأقساط'))->icon(Heroicon::OutlinedArrowTopRightOnSquare)->link()
                                        ->url(fn () => InstallmentResource::getUrl()),
                                ])
                                ->schema([
                                    RepeatableEntry::make('due_installments')->hiddenLabel()->columns(['default' => 2, 'md' => 4])
                                        ->state(fn (Client $r) => self::dues($r)->orderBy('due_date')->limit(6)->get())
                                        ->schema([
                                            Parts::date('due_date', 'الاستحقاق')->weight('bold')
                                                ->color(fn (Installment $record) => $record->due_date?->isPast() ? 'danger' : null),
                                            TextEntry::make('unit.title')->label(__('الوحدة'))->placeholder('-'),
                                            Parts::money('amount', 'المبلغ'),
                                            TextEntry::make('remaining')->label(__('المتبقي'))->numeric(2)->weight('bold')
                                                ->state(fn (Installment $record) => $record->remainingAmount())
                                                ->url(fn (Installment $record) => InstallmentResource::getUrl('view', ['record' => $record]))->color('primary'),
                                        ]),
                                ]),
                        ]),
                        Grid::make(1)->columnSpan(['lg' => 1])->schema([
                            Section::make(__('سلوك السداد'))->icon(Heroicon::OutlinedShieldExclamation)
                                ->description(__('تقدير آلي من سجل الدفعات'))
                                ->schema([
                                    \Filament\Infolists\Components\ViewEntry::make('payment_risk')->hiddenLabel()
                                        ->view('filament.insights.payment-risk')
                                        ->viewData(fn (Client $r) => ['risk' => app(\App\Services\Insights\PaymentRisk::class)->forClient($r)]),
                                ]),
                        ]),
                    ]),
                ]),
                Tab::make(__('البيانات'))->icon(Heroicon::OutlinedIdentification)->schema([
                    Grid::make(['default' => 1, 'lg' => 3])->schema([
                        Grid::make(1)->columnSpan(['lg' => 2])->schema([
                            Parts::identity(),
                            Parts::contact(),
                            Section::make(__('ملاحظات'))->icon(Heroicon::OutlinedPencilSquare)->visible(fn (Client $r) => filled($r->notes))->schema([
                                TextEntry::make('notes')->hiddenLabel()->prose(),
                            ]),
                        ]),
                        Grid::make(1)->columnSpan(['lg' => 1])->schema([
                            Section::make(__('بيانات المنشأة'))->icon(Heroicon::OutlinedBuildingOffice)->visible(fn (Client $r) => $r->kind === ClientKind::Company || filled($r->company_name))->schema([
                                TextEntry::make('company_name')->label(__('اسم المنشأة'))->weight('bold')->placeholder('-'),
                                TextEntry::make('company_registration_number')->label(__('السجل التجاري'))->placeholder('-')->copyable(),
                                TextEntry::make('vat_number')->label(__('الرقم الضريبي'))->placeholder('-')->copyable(),
                                Parts::phone('company_mobile', 'هاتف المنشأة'),
                            ]),
                            Section::make(__('التصنيف'))->icon(Heroicon::OutlinedTag)->columns(2)->schema([
                                TextEntry::make('kind')->label(__('النوع'))->badge()->color('gray'),
                                TextEntry::make('category.title')->label(__('الفئة'))->badge()->color('info')->placeholder('-'),
                                TextEntry::make('calendar')->label(__('التقويم'))->badge()->color('gray'),
                            ]),
                        ]),
                    ]),
                ]),
                Tab::make(__('البوابة والسجل'))->icon(Heroicon::OutlinedKey)->schema([
                    Grid::make(['default' => 1, 'lg' => 2])->schema([
                        Parts::portalAccount(),
                        Parts::record(false)->collapsed(false),
                    ]),
                ]),
            ]),
        ]);
    }

    /** Unpaid, non-expense installments on any of the client's contracts. */
    private static function dues(Client $client): Builder
    {
        return Installment::query()->with(['unit', 'contract'])->where('is_collected', false)->where('type', '!=', 'expenses')
            ->whereHasMorph('contract', [RentContract::class, SaleContract::class],
                fn (Builder $q) => $q->whereHas('clients', fn (Builder $q) => $q->whereKey($client->getKey())));
    }
}
