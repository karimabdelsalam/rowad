<?php

namespace App\Services\Import;

use App\Exceptions\BusinessRuleException;
use App\Services\Import\Importers\ClientsImporter;
use App\Services\Import\Importers\OwnersImporter;
use App\Services\Import\Importers\RentContractsImporter;
use App\Services\Import\Importers\UnitsImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bringing an office's existing records over from Excel: owners, clients, units, then running rent contracts
 * (in that order, since each refers to the previous). A file is previewed row by row before anything is saved;
 * rows with problems are skipped and reported, every other row is saved on its own so one bad row never
 * undoes the rest.
 */
class DataImport
{
    /** @var list<class-string<Importer>> in the order an office should import them */
    public const IMPORTERS = [OwnersImporter::class, ClientsImporter::class, UnitsImporter::class, RentContractsImporter::class];

    /** @return array<string, string> key => title */
    public static function types(): array
    {
        return collect(self::IMPORTERS)->mapWithKeys(fn ($class) => [$class::key() => $class::title()])->all();
    }

    public static function importer(string $type): Importer
    {
        $class = collect(self::IMPORTERS)->first(fn ($class) => $class::key() === $type)
            ?? throw new BusinessRuleException(__('نوع الاستيراد غير معروف'));

        return app($class);
    }

    /** Writes the Excel template (headers and one example row) and returns its path. */
    public static function template(string $type): string
    {
        $importer = self::importer($type);
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        Spreadsheet::template($path, $importer::headers(), [$importer::example()]);

        return $path;
    }

    /**
     * @return array{rows: int, ready: int, problems: list<array{row: int, problems: list<string>}>, unknown_headers: list<string>, missing_headers: list<string>}
     */
    public function preview(string $type, string $path, string $extension): array
    {
        $importer = self::importer($type);
        $rows = Spreadsheet::rows($path, $extension);
        $problems = [];
        foreach ($rows as $i => $row) {
            if (($p = $importer->problems(self::normalise($row))) !== []) {
                $problems[] = ['row' => $i + 2, 'problems' => $p]; // +2: the header row and 1-based numbering, as Excel shows it
            }
        }
        $headers = array_map(fn ($h) => self::header($h), array_keys($rows[0] ?? []));
        $known = collect($importer::columns())->map(fn ($c) => __($c[0]));

        return [
            'rows' => count($rows),
            'ready' => count($rows) - count($problems),
            'problems' => $problems,
            'unknown_headers' => array_values(array_filter($headers, fn ($h) => $h !== '' && ! $known->contains($h))),
            'missing_headers' => $rows === [] ? [] : $known->filter(fn ($h, $field) => $importer::columns()[$field][1] && ! in_array($h, $headers, true))->values()->all(),
        ];
    }

    /**
     * @param  array{update_existing?: bool, collect_past?: bool}  $options
     * @return array{created: int, updated: int, skipped: int, failed: list<array{row: int, problems: list<string>}>}
     */
    public function run(string $type, string $path, string $extension, array $options = []): array
    {
        $importer = self::importer($type);
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => []];
        foreach (Spreadsheet::rows($path, $extension) as $i => $row) {
            $row = self::normalise($row);
            if (($problems = $importer->problems($row)) !== []) {
                $result['failed'][] = ['row' => $i + 2, 'problems' => $problems];

                continue;
            }
            try {
                $result[DB::transaction(fn () => $importer->import($row, $options))]++;
            } catch (BusinessRuleException $e) {
                $result['failed'][] = ['row' => $i + 2, 'problems' => [$e->getMessage()]];
            } catch (\Throwable $e) {
                Log::warning('Import row failed', ['type' => $type, 'row' => $i + 2, 'error' => $e->getMessage()]);
                $result['failed'][] = ['row' => $i + 2, 'problems' => [__('تعذّر حفظ هذا الصف')]];
            }
        }

        return $result;
    }

    /** The template marks required headers with " *"; files typed by hand may not. */
    private static function normalise(array $row): array
    {
        $out = [];
        foreach ($row as $header => $value) {
            $out[self::header((string) $header)] = $value;
        }

        return $out;
    }

    private static function header(string $header): string
    {
        return trim(preg_replace('/\s*\*\s*$/u', '', $header));
    }
}
