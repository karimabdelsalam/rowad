<?php

namespace App\Services\Import;

use DateTimeInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/** Reads an uploaded .xlsx / .csv into header-keyed rows, and writes the import templates. */
final class Spreadsheet
{
    /** Most offices import a few hundred rows; this keeps a stray huge file from tying up the request. */
    public const MAX_ROWS = 5000;

    /**
     * @return list<array<string, string|DateTimeInterface>> rows keyed by the header text (first row), empty rows skipped
     */
    public static function rows(string $path, string $extension): array
    {
        $reader = strtolower($extension) === 'csv' ? new CsvReader : new XlsxReader;
        $reader->open($path);
        $headers = null;
        $rows = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $values = array_map(fn ($v) => $v instanceof DateTimeInterface ? $v : trim((string) $v), $row->toArray());
                    if ($headers === null) {
                        // a BOM on the first CSV header would otherwise hide the column
                        $headers = array_map(fn ($h) => trim(preg_replace('/^\x{FEFF}/u', '', (string) $h)), $values);

                        continue;
                    }
                    if (count(array_filter($values, fn ($v) => $v instanceof DateTimeInterface || $v !== '')) === 0) {
                        continue;
                    }
                    $rows[] = array_combine($headers, array_pad(array_slice($values, 0, count($headers)), count($headers), ''));
                    if (count($rows) >= self::MAX_ROWS) {
                        break 2;
                    }
                }
                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    /** @param list<string> $headers @param list<list<string>> $examples */
    public static function template(string $path, array $headers, array $examples): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers, (new Style)->setFontBold()->setBackgroundColor('E6F4F1')));
        foreach ($examples as $example) {
            $writer->addRow(Row::fromValues($example));
        }
        $writer->close();
    }
}
