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
    use InteractsWithTable {
        makeTable as makeBaseReportTable;
    }

    /** Filters live in the URL, so a report can be bookmarked or opened pre-filtered (e.g. one bank account's transactions). */
    #[\Livewire\Attributes\Url(as: 'filters')]
    public ?array $tableFilters = null;

    /** Sidebar order and icon per report, following the legacy reports menu. */
    private const MENU = [
        'taxes' => [1, Heroicon::OutlinedReceiptPercent],
        'expenses' => [2, Heroicon::OutlinedArrowTrendingDown],
        'renter' => [3, Heroicon::OutlinedUser],
        'unit' => [4, Heroicon::OutlinedHome],
        'income' => [5, Heroicon::OutlinedArrowTrendingUp],
        'profits' => [6, Heroicon::OutlinedPresentationChartLine],
        'banking' => [7, Heroicon::OutlinedBuildingLibrary],
        'units' => [8, Heroicon::OutlinedChartBar],
        'corporate_taxes' => [9, Heroicon::OutlinedScale],
        'rent' => [10, Heroicon::OutlinedCalendarDays],
    ];

    public static function getNavigationGroup(): ?string
    {
        return __('التقارير');
    }

    public static function getNavigationSort(): ?int
    {
        return self::MENU[static::$report][0] ?? null;
    }

    public static function getNavigationIcon(): Heroicon
    {
        return self::MENU[static::$report][1] ?? Heroicon::OutlinedDocumentChartBar;
    }

    /** Kept under /reports/... as before. */
    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'reports/'.\Illuminate\Support\Str::kebab(class_basename(static::class));
    }

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
                DatePicker::make('from')->label(__('من'))->default(now()->startOfYear()->toDateString()),
                DatePicker::make('until')->label(__('إلى'))->default(today()->toDateString()),
            ])
            ->columns(2)
            ->query(fn (Builder $query, array $data) => $query
                ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate($column, '>=', substr((string) $d, 0, 10)))
                ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate($column, '<=', substr((string) $d, 0, 10))))
            ->indicateUsing(fn (array $data) => array_filter([
                filled($data['from'] ?? null) ? __('من').' '.substr((string) $data['from'], 0, 10) : null,
                filled($data['until'] ?? null) ? __('إلى').' '.substr((string) $data['until'], 0, 10) : null,
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

    /** Totals rows read "all <report>" instead of the model's English name. */
    protected function makeTable(): Table
    {
        return $this->makeBaseReportTable()->modelLabel(static::title())->pluralModelLabel(static::title())
            ->emptyStateHeading(__('لا توجد بيانات للفترة أو التصفية المختارة'))
            ->emptyStateDescription(__('غيّر الفترة أو التصفية من زر التصفية أعلى الجدول.'));
    }

    /** Excel and print come from the table's export menu (TableExport); a CSV header action stays for quick downloads. */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('analyze')
                ->label(__('تحليل ذكي'))
                ->icon(Heroicon::OutlinedSparkles)
                ->visible(fn () => \App\Filament\Admin\Pages\Assistant::canAccess())
                ->action(fn () => $this->analyzeWithAi()),
            Action::make('export')
                ->label(__('CSV'))
                ->color('gray')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(fn () => $this->exportCsv()),
        ];
    }

    /** Every row of the report with the current filters (array-based reports override this). */
    protected function exportRecords(): iterable
    {
        return $this->getTable()->hasQuery() ? $this->getFilteredSortedTableQuery()->get() : $this->getTableRecords();
    }

    /**
     * The report as plain rows with the current filters and sort: what the CSV export writes and what the AI analysis reads.
     *
     * @return array{0: list<string>, 1: \Generator<list<string>>}
     */
    protected function reportRows(): array
    {
        $columns = collect($this->getTable()->getColumns())->filter(fn (Column $c) => ! $c->isHidden() && ! $c->isToggledHidden());
        $records = $this->exportRecords();
        $rows = (function () use ($columns, $records) {
            foreach ($records as $record) {
                yield $columns->map(function (Column $c) use ($record) {
                    $state = $c->record($record)->getState();
                    $state = $state instanceof \BackedEnum && method_exists($state, 'getLabel') ? $state->getLabel() : $state;
                    $state = $state instanceof \DateTimeInterface ? $state->format('Y-m-d') : $state;

                    return is_array($state) ? implode('، ', $state) : (string) $state;
                })->values()->all();
            }
        })();

        return [$columns->map(fn (Column $c) => strip_tags((string) $c->getLabel()))->values()->all(), $rows];
    }

    /** CSV with a UTF-8 BOM so Excel shows Arabic correctly; uses the current filters and sort. */
    public function exportCsv(): StreamedResponse
    {
        [$header, $rows] = $this->reportRows();

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, static::$report.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Rows sent to the AI analysis; enough for trends without an oversized request. */
    private const ANALYSIS_ROWS = 400;

    /** "تحليل ذكي": the AI reads the report as filtered on screen, and the analysis opens in the assistant for follow-up questions. */
    public function analyzeWithAi(): void
    {
        [$header, $rows] = $this->reportRows();
        $data = [];
        $total = 0;
        foreach ($rows as $row) {
            if (++$total <= self::ANALYSIS_ROWS) {
                $data[] = array_combine(array_map(fn ($h, $i) => $h !== '' ? $h : 'col'.$i, $header, array_keys($header)), $row);
            }
        }
        $filters = collect($this->tableFilters ?? [])->map(fn ($f) => array_filter((array) $f, fn ($v) => filled($v)))->filter()->all();

        try {
            $answer = app(\App\Services\Ai\Assistant::class)->analyze(auth()->user(), __('تحليل :report', ['report' => static::title()]), [
                'report' => static::title(),
                'filters' => $filters ?: __('بدون تصفية'),
                'rows_total' => $total,
                'rows_included' => count($data),
                'rows' => $data,
            ], 'Analyse this report for the office manager: key totals, trends over time, the biggest and the unusual items, risks '
                .'(late payments, vacancies, expenses growing faster than income) and 3-5 concrete recommendations. '
                .($total > self::ANALYSIS_ROWS ? 'Only the first '.self::ANALYSIS_ROWS." of {$total} rows are included; say so when it matters. " : '')
                .'Use tables where helpful.');
        } catch (\App\Services\Ai\AiUnavailable $e) {
            \Filament\Notifications\Notification::make()->title($e->getMessage())->warning()->send();

            return;
        }

        $this->redirect(\App\Filament\Admin\Pages\Assistant::getUrl(['c' => $answer->ai_conversation_id]));
    }
}
