<?php

namespace App\Filament\Admin\Resources\PayrollRuns;

use App\Enums\PayrollStatus;
use App\Filament\Admin\Resources\Hr;
use App\Filament\Admin\Resources\PayrollRuns\Pages\ListPayrollRuns;
use App\Filament\Admin\Resources\PayrollRuns\Pages\ViewPayrollRun;
use App\Filament\Admin\Resources\PayrollRuns\RelationManagers\ItemsRelationManager;
use App\Filament\Infolists\Parts;
use App\Filament\Resource;
use App\Models\PayrollRun;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Monthly payroll runs: generate → adjust lines → approve → pay (vouchers + WPS file). */
class PayrollRunResource extends Resource
{
    protected static ?string $model = PayrollRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = Hr::GROUP;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'مسيّر رواتب';

    protected static ?string $pluralModelLabel = 'الرواتب';

    protected static ?string $permission = 'payroll';

    protected static ?string $recordTitleAttribute = 'year';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Parts::header(Heroicon::OutlinedBanknotes, fn (PayrollRun $r) => [
                'kicker' => __('مسيّر رواتب').' · '.$r->status->getLabel(),
                'title' => $r->periodLabel(),
                'sub' => [trans_choice('{0} لا يوجد موظفون|{1} موظف واحد|{2} موظفان|[3,*] :count موظفًا', $r->items()->count()),
                    $r->approved_at ? __('اعتُمد :d بواسطة :u', ['d' => $r->approved_at->format('Y-m-d'), 'u' => $r->approver?->name]) : null,
                    $r->paid_at ? __('صُرف :d', ['d' => $r->paid_at->format('Y-m-d')]) : null],
                'facts' => [
                    Parts::fact('الإجمالي', Parts::amount($r->total_gross)),
                    Parts::fact('الخصومات', Parts::amount($r->total_deductions), 'danger'),
                    Parts::fact('الصافي', Parts::amount($r->total_net), 'success'),
                    Parts::fact('تأمينات المنشأة', Parts::amount($r->total_gosi_employer), 'warning'),
                ],
            ]),
            Grid::make(1)->columnSpan(['lg' => 3])->schema([
                Section::make(__('ملاحظات'))->icon(Heroicon::OutlinedPencilSquare)->visible(fn (PayrollRun $r) => filled($r->notes))->schema([
                    TextEntry::make('notes')->hiddenLabel()->prose(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('year', 'desc')
            ->columns([
                TextColumn::make('period')->label(__('الشهر'))->weight('bold')->state(fn (PayrollRun $r) => $r->periodLabel())
                    ->description(fn (PayrollRun $r) => trans_choice('{0} لا يوجد موظفون|{1} موظف واحد|{2} موظفان|[3,*] :count موظفًا', $r->items()->count())),
                TextColumn::make('status')->label(__('الحالة'))->badge(),
                TextColumn::make('total_gross')->label(__('الإجمالي'))->numeric(2),
                TextColumn::make('total_deductions')->label(__('الخصومات'))->numeric(2)->color('danger'),
                TextColumn::make('total_net')->label(__('الصافي'))->numeric(2)->weight('bold')->color('success'),
                TextColumn::make('total_gosi_employer')->label(__('تأمينات المنشأة'))->numeric(2)->toggleable(),
                TextColumn::make('paid_at')->label(__('تاريخ الصرف'))->date('Y-m-d')->placeholder('-')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('الحالة'))->options(PayrollStatus::class),
            ])
            ->recordActions([ViewAction::make()->label(__('فتح'))]);
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayrollRuns::route('/'),
            'view' => ViewPayrollRun::route('/{record}'),
        ];
    }
}
