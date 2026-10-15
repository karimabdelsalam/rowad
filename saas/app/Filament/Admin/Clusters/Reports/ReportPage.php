<?php

namespace App\Filament\Admin\Clusters\Reports;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use App\Models\Installment;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Base for the legacy reports: filtered table with totals, plus the legacy
 * "export to Excel" (exportotf) and "print all" (printotf) behaviours.
 */
abstract class ReportPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $cluster = ReportsCluster::class;

    /** reports.* permission suffix in config/rowad.php */
    protected static string $report;

    abstract protected static function title(): string;

    public static function getNavigationLabel(): string
    {
        return static::title();
    }

    public function getTitle(): string
    {
        return static::title();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can('reports.'.static::$report));
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    /** Date range filter on a column (legacy fdate/todate). */
    protected static function periodFilter(string $column, string $label = 'الفترة'): Filter
    {
        return Filter::make('period')
            ->label(__($label))
            ->schema([
                DatePicker::make('from')->label(__('من'))->default(now()->startOfYear()),
                DatePicker::make('until')->label(__('إلى'))->default(now()),
            ])
            ->columns(2)
            ->query(fn (Builder $query, array $data) => $query
                ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate($column, '>=', $d))
                ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate($column, '<=', $d)))
            ->indicateUsing(fn (array $data) => array_filter([
                filled($data['from'] ?? null) ? __('من').' '.$data['from'] : null,
                filled($data['until'] ?? null) ? __('إلى').' '.$data['until'] : null,
            ]));
    }

    /** Paid and remaining columns for installment reports; a fully collected installment counts as paid in full. */
    protected static function paidColumns(): array
    {
        $total = fn (string $sql) => Summarizer::make()->label(__('filament-tables::table.summary.summarizers.sum.label'))->numeric(2)
            ->using(fn ($query) => (float) $query->sum(DB::raw($sql)));

        return [
            TextColumn::make('paid_amount')->state(fn (Installment $record) => $record->paidTotal())->numeric(2)
                ->summarize($total(Installment::PAID_SQL)),
            TextColumn::make('remaining')->label(__('المتبقي'))->state(fn (Installment $record) => $record->remainingAmount())->numeric(2)
                ->summarize($total('amount - ('.Installment::PAID_SQL.')')),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('تصدير Excel'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(fn () => $this->exportCsv()),
            Action::make('print')
                ->label(__('طباعة'))
                ->icon(Heroicon::OutlinedPrinter)
                ->alpineClickHandler('window.print()'),
        ];
    }

    /** CSV with a UTF-8 BOM so Excel shows Arabic correctly; uses the current filters and sort. */
    public function exportCsv(): StreamedResponse
    {
        $table = $this->getTable();
        $columns = collect($table->getColumns())->filter(fn (Column $c) => ! $c->isHidden() && ! $c->isToggledHidden());
        $records = $this->getFilteredSortedTableQuery()->get();

        return response()->streamDownload(function () use ($columns, $records) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $columns->map(fn (Column $c) => strip_tags((string) $c->getLabel()))->all());
            foreach ($records as $record) {
                fputcsv($out, $columns->map(function (Column $c) use ($record) {
                    $state = $c->record($record)->getState();
                    $state = $state instanceof \BackedEnum && method_exists($state, 'getLabel') ? $state->getLabel() : $state;
                    $state = $state instanceof \DateTimeInterface ? $state->format('Y-m-d') : $state;

                    return is_array($state) ? implode('، ', $state) : (string) $state;
                })->all());
            }
            fclose($out);
        }, static::$report.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
