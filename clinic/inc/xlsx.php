<?php
declare(strict_types=1);

/**
 * كاتب ملفات Excel (.xlsx) بسيط بدون أي مكتبات خارجية — يعتمد على ZipArchive
 * المتوفر افتراضيًا على استضافات cPanel. يدعم العربية، واتجاه الورقة من اليمين
 * لليسار، وتثبيت صف الرؤوس، وتنسيق الأرقام والتواريخ والمبالغ.
 *
 * لو امتداد zip غير مفعّل يتحول تلقائيًا لتصدير CSV متوافق مع Excel العربي.
 */
final class XlsxWriter
{
    public const TEXT  = 'text';
    public const NUM   = 'num';
    public const MONEY = 'money';
    public const DATE  = 'date';

    /** @var array<array{title:string,type:string,width:int}> */
    private array $columns = [];
    /** @var array<array{cells:array,total:bool}> */
    private array $rows = [];
    private string $title = '';
    private string $subtitle = '';

    public function __construct(private string $sheetName = 'بيانات') {}

    public function setTitle(string $title, string $subtitle = ''): void
    {
        $this->title = $title;
        $this->subtitle = $subtitle;
    }

    /** @param array<array{0:string,1?:string,2?:int}> $columns [العنوان, النوع, العرض] */
    public function setColumns(array $columns): void
    {
        foreach ($columns as $c) {
            $this->columns[] = [
                'title' => (string)$c[0],
                'type'  => $c[1] ?? self::TEXT,
                'width' => (int)($c[2] ?? 18),
            ];
        }
    }

    public function addRow(array $cells, bool $total = false): void
    {
        $this->rows[] = ['cells' => array_values($cells), 'total' => $total];
    }

    public function addTotalRow(array $cells): void
    {
        $this->addRow($cells, true);
    }

    /** يرسل الملف للمتصفح وينهي التنفيذ */
    public function download(string $filename): never
    {
        if (!class_exists('ZipArchive')) {
            $this->downloadCsv($filename);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($tmp === false) {
            $this->downloadCsv($filename);
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            $this->downloadCsv($filename);
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml());
        $zip->close();

        $data = (string)file_get_contents($tmp);
        @unlink($tmp);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $this->safeName($filename) . '.xlsx"');
        header('Content-Length: ' . strlen($data));
        header('Cache-Control: max-age=0');
        echo $data;
        exit;
    }

    /** خطة بديلة: CSV بترميز UTF-8 مع BOM ليفتح بالعربي في Excel مباشرة */
    private function downloadCsv(string $filename): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $this->safeName($filename) . '.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        if ($this->title !== '') {
            fputcsv($out, [$this->title]);
            if ($this->subtitle !== '') {
                fputcsv($out, [$this->subtitle]);
            }
            fputcsv($out, []);
        }
        fputcsv($out, array_column($this->columns, 'title'));
        foreach ($this->rows as $row) {
            fputcsv($out, array_map(
                fn($v) => $v === null ? '' : (is_float($v) ? number_format($v, 2, '.', '') : (string)$v),
                $row['cells']
            ));
        }
        fclose($out);
        exit;
    }

    private function safeName(string $name): string
    {
        $name = preg_replace('/[^\p{Arabic}\p{L}\p{N}_\- ]+/u', '', $name) ?? 'export';
        return trim($name) !== '' ? trim($name) : 'export';
    }

    private static function esc(string $v): string
    {
        // إزالة المحارف التي لا يقبلها XML 1.0
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v) ?? '';
        return htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function colLetter(int $index): string
    {
        $letter = '';
        for ($i = $index; $i >= 0; $i = intdiv($i, 26) - 1) {
            $letter = chr(65 + $i % 26) . $letter;
        }
        return $letter;
    }

    private static function dateSerial(string $date): ?int
    {
        $ts = strtotime($date);
        if ($ts === false || $date === '' || str_starts_with($date, '0000')) {
            return null;
        }
        $d = (new DateTimeImmutable('@' . $ts))->setTime(0, 0);
        $base = new DateTimeImmutable('1899-12-30 00:00:00', new DateTimeZone('UTC'));
        return (int)$base->diff($d->setTimezone(new DateTimeZone('UTC')))->days;
    }

    /* ------------------------------------------------------ أجزاء الملف */

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        $name = preg_replace('/[\\\\\/\?\*\[\]:]/u', '', $this->sheetName) ?: 'بيانات';
        $name = mb_substr($name, 0, 31);
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::esc($name) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        $ns = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="' . $ns . '/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="' . $ns . '/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2">'
            . '<numFmt numFmtId="164" formatCode="#,##0.00"/>'
            . '<numFmt numFmtId="165" formatCode="dd/mm/yyyy"/>'
            . '</numFmts>'
            . '<fonts count="4">'
            . '<font><sz val="11"/><name val="Arial"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>'
            . '<font><b/><sz val="14"/><color rgb="FF0F766E"/><name val="Arial"/></font>'
            . '<font><b/><sz val="11"/><name val="Arial"/></font>'
            . '</fonts>'
            . '<fills count="4">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF0F766E"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border>'
            . '<left style="thin"><color rgb="FFCBD5E1"/></left><right style="thin"><color rgb="FFCBD5E1"/></right>'
            . '<top style="thin"><color rgb="FFCBD5E1"/></top><bottom style="thin"><color rgb="FFCBD5E1"/></bottom>'
            . '<diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="10">'
            // 0 افتراضي
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            // 1 عنوان التقرير
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            // 2 رأس العمود
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            // 3 نص
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            // 4 رقم
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            // 5 مبلغ
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            // 6 تاريخ
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            // 7 إجمالي — نص
            . '<xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            // 8 إجمالي — رقم
            . '<xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            // 9 إجمالي — مبلغ
            . '<xf numFmtId="164" fontId="3" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function sheetXml(): string
    {
        $colCount = max(1, count($this->columns));
        $lastCol = self::colLetter($colCount - 1);

        $rowNum = 0;
        $body = '';
        $merges = [];

        if ($this->title !== '') {
            $rowNum++;
            $body .= '<row r="' . $rowNum . '" ht="24" customHeight="1">'
                . '<c r="A' . $rowNum . '" s="1" t="inlineStr"><is><t>' . self::esc($this->title) . '</t></is></c></row>';
            $merges[] = 'A' . $rowNum . ':' . $lastCol . $rowNum;

            if ($this->subtitle !== '') {
                $rowNum++;
                $body .= '<row r="' . $rowNum . '">'
                    . '<c r="A' . $rowNum . '" s="0" t="inlineStr"><is><t>' . self::esc($this->subtitle) . '</t></is></c></row>';
                $merges[] = 'A' . $rowNum . ':' . $lastCol . $rowNum;
            }
            $rowNum++; // صف فاصل فارغ
        }

        // صف الرؤوس
        $rowNum++;
        $headerRow = $rowNum;
        $body .= '<row r="' . $rowNum . '" ht="22" customHeight="1">';
        foreach ($this->columns as $i => $col) {
            $body .= '<c r="' . self::colLetter($i) . $rowNum . '" s="2" t="inlineStr"><is><t>'
                . self::esc($col['title']) . '</t></is></c>';
        }
        $body .= '</row>';

        // الصفوف
        foreach ($this->rows as $row) {
            $rowNum++;
            $body .= '<row r="' . $rowNum . '">';
            foreach ($this->columns as $i => $col) {
                $value = $row['cells'][$i] ?? null;
                $ref = self::colLetter($i) . $rowNum;
                $body .= $this->cellXml($ref, $value, $col['type'], $row['total']);
            }
            $body .= '</row>';
        }

        $cols = '<cols>';
        foreach ($this->columns as $i => $col) {
            $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $col['width'] . '" customWidth="1"/>';
        }
        $cols .= '</cols>';

        $mergeXml = $merges
            ? '<mergeCells count="' . count($merges) . '">'
              . implode('', array_map(fn($m) => '<mergeCell ref="' . $m . '"/>', $merges))
              . '</mergeCells>'
            : '';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView rightToLeft="1" tabSelected="1" workbookViewId="0">'
            . '<pane ySplit="' . $headerRow . '" topLeftCell="A' . ($headerRow + 1) . '" activePane="bottomLeft" state="frozen"/>'
            . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . $cols
            . '<sheetData>' . $body . '</sheetData>'
            . $mergeXml
            . '<pageMargins left="0.4" right="0.4" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
            . '</worksheet>';
    }

    private function cellXml(string $ref, mixed $value, string $type, bool $total): string
    {
        if ($value === null || $value === '') {
            $style = $total ? 7 : 3;
            return '<c r="' . $ref . '" s="' . $style . '"/>';
        }

        switch ($type) {
            case self::MONEY:
                $style = $total ? 9 : 5;
                return '<c r="' . $ref . '" s="' . $style . '"><v>' . number_format((float)$value, 2, '.', '') . '</v></c>';

            case self::NUM:
                if (!is_numeric($value)) {
                    break;
                }
                $style = $total ? 8 : 4;
                return '<c r="' . $ref . '" s="' . $style . '"><v>' . (0 + $value) . '</v></c>';

            case self::DATE:
                $serial = self::dateSerial((string)$value);
                if ($serial === null) {
                    break;
                }
                $style = $total ? 8 : 6;
                return '<c r="' . $ref . '" s="' . $style . '"><v>' . $serial . '</v></c>';
        }

        $style = $total ? 7 : 3;
        return '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">'
            . self::esc((string)$value) . '</t></is></c>';
    }
}
