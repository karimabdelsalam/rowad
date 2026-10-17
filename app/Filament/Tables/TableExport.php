<?php

namespace App\Filament\Tables;

use App\Services\Documents\DocumentData;
use BackedEnum;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\Widget;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Legacy ?exportotf=excel and ?printotf=all on every list: the visible columns of the
 * filtered, sorted table — all pages, not just the current one.
 */
final class TableExport
{
    private const PRINT_LIMIT = 2000;

    /** Header actions added to every resource and report table. */
    public static function actions(): ActionGroup
    {
        return ActionGroup::make([
            Action::make('exportExcel')
                ->label(__('تصدير Excel'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(fn (HasTable $livewire) => self::download($livewire)),
            Action::make('printTable')
                ->label(__('طباعة'))
                ->icon(Heroicon::OutlinedPrinter)
                ->action(fn (HasTable $livewire) => self::print($livewire)),
            Action::make('pdfTable')
                ->label(__('تصدير PDF'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->action(fn (HasTable $livewire) => self::print($livewire, pdf: true)),
        ])->label(__('تصدير'))->icon(Heroicon::OutlinedEllipsisVertical)->button()->color('gray')
            ->visible(fn (HasTable $livewire) => self::allowed($livewire));
    }

    public static function download(HasTable $livewire): StreamedResponse
    {
        [$headings, $rows] = self::snapshot($livewire);
        $name = Str::slug(self::title($livewire), '-', 'ar') ?: 'export';

        return response()->streamDownload(function () use ($headings, $rows) {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->getCurrentSheet()->setSheetView((new SheetView)->setRightToLeft(app()->getLocale() === 'ar'));
            $writer->addRow(Row::fromValues($headings));
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }
            $writer->close();
        }, $name.'-'.now()->format('Y-m-d').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * The table as a report on the office letterhead (A4 landscape) in a new tab: printed, or saved as a PDF ($pdf).
     * It carries the filters in use, the number of rows and totals of the money columns.
     */
    public static function print(HasTable $livewire, bool $pdf = false): void
    {
        [$headings, $rows] = self::snapshot($livewire, self::PRINT_LIMIT);
        $title = self::title($livewire);
        $filters = collect($livewire->getTable()->getFilterIndicators())
            ->map(fn ($indicator) => self::plain($indicator->getLabel()))->filter()->values()->all();

        $body = view('print.table', [
            'title' => $title,
            'headings' => $headings,
            'rows' => $rows,
            'totals' => self::totals($rows),
            'filters' => $filters,
            'truncated' => count($rows) >= self::PRINT_LIMIT,
        ] + app(DocumentData::class)->letterhead())->render();
        $html = view('print.document', ['html' => $body, 'title' => $title, 'landscape' => true, 'autoPrint' => ! $pdf, 'autoPdf' => $pdf])->render();

        $livewire->js('(() => { const w = window.open("", "_blank"); w.document.open(); w.document.write('.json_encode($html).'); w.document.close(); w.focus(); })()');
    }

    /** Sum of each column holding amounts (decimals); null where a total means nothing (names, phones, counts). @return list<float|null> */
    private static function totals(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $totals = [];
        foreach (array_keys($rows[0]) as $i) {
            $values = array_column($rows, $i);
            $money = array_filter($values, fn ($v) => is_float($v));
            $totals[] = $money !== [] && count(array_filter($values, fn ($v) => $v !== null && ! is_float($v) && ! is_int($v))) === 0
                ? round(array_sum(array_map(fn ($v) => (float) $v, $values)), 2) : null;
        }

        return array_filter($totals, fn ($t) => $t !== null) === [] ? [] : $totals;
    }

    /** @return array{0: list<string>, 1: list<list<scalar|null>>} */
    public static function snapshot(HasTable $livewire, ?int $limit = null): array
    {
        $table = $livewire->getTable();
        $columns = array_values(array_filter($table->getVisibleColumns(), fn (Column $column) => ! $column->isToggledHidden()));
        $headings = array_map(fn (Column $column) => self::plain($column->getLabel()), $columns);

        $query = $livewire->getFilteredSortedTableQuery();
        $records = $query !== null
            ? $query->when($limit, fn ($q) => $q->limit($limit))->cursor()
            : self::items($livewire->getTableRecords());

        $rows = [];
        foreach ($records as $record) {
            $rows[] = array_map(fn (Column $column) => self::cell($column, $record), $columns);
            if ($limit !== null && count($rows) >= $limit) {
                break;
            }
        }

        return [$headings, $rows];
    }

    private static function cell(Column $column, mixed $record): string|int|float|null
    {
        $column->record($record);
        $state = $column->getState();
        $values = is_iterable($state) && ! is_string($state) ? collect($state)->all() : [$state];
        $values = array_values(array_filter($values, fn ($v) => $v !== null && $v !== ''));

        if (count($values) === 1 && ! $column instanceof IconColumn) {
            $value = $values[0];
            if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^-?\d+\.\d+$/', $value))) {
                return $value + 0; // keep numbers numeric for Excel; strings like phone numbers stay text
            }
        }

        return implode('، ', array_map(fn ($value) => self::text($column, $value), $values)) ?: null;
    }

    private static function text(Column $column, mixed $value): string
    {
        if ($column instanceof IconColumn && is_bool($value)) {
            return $value ? __('نعم') : __('لا');
        }
        if ($column instanceof TextColumn && ! $value instanceof HasLabel) {
            $value = $column->formatState($value);
        }

        return match (true) {
            $value instanceof HasLabel => self::plain($value->getLabel()),
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_bool($value) => $value ? __('نعم') : __('لا'),
            default => self::plain($value),
        };
    }

    private static function plain(mixed $value): string
    {
        $value = $value instanceof Htmlable ? $value->toHtml() : (string) $value;

        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5));
    }

    private static function items(mixed $records): iterable
    {
        return $records instanceof Paginator ? $records->items() : $records;
    }

    private static function title(HasTable $livewire): string
    {
        return self::plain(method_exists($livewire, 'getTitle') ? $livewire->getTitle() : __('تقرير'));
    }

    /** Units carry an explicit export permission (legacy building export); other lists follow view access. */
    private static function allowed(HasTable $livewire): bool
    {
        $user = auth()->user();
        if ($user === null || $user->is_super_admin || ! method_exists($livewire, 'getResource')) {
            return $user !== null;
        }
        $prefix = $livewire::getResource()::getPermissionPrefix();

        return $prefix === null
            || ! in_array('export', config("rowad.permissions.{$prefix}", []), true)
            || $user->can("{$prefix}.export");
    }

    /** Applied from AppServiceProvider to every panel table except dashboard widgets. */
    public static function register(): void
    {
        Table::configureUsing(function (Table $table) {
            if (! $table->getLivewire() instanceof Widget) {
                $table->headerActions([self::actions()]);
            }
        });
    }
}
