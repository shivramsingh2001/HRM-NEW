<?php

namespace App\Services\Reports;

use RuntimeException;
use ZipArchive;

/**
 * Turns a report export file (what ReportFileGenerator returns — CSV, or the
 * XLSX that App\Support\Spreadsheet\SpreadsheetWriter writes) into a table for
 * the mobile admin API: title lines above the table, the column names, and the
 * rows. The file is the same one the web Export button gives, so the app shows
 * exactly the web numbers.
 *
 * Header row = the first row as wide as most rows of the file (the table's own
 * width); single-cell / narrower lines before it are the report's title lines.
 * Blank lines are dropped, short rows padded to the header width.
 */
class ReportTableReader
{
    /** @return array{title_lines: array<int, string>, columns: array<int, string>, rows: array<int, array<int, string>>} */
    public function read(string $content, string $mime): array
    {
        $rows = str_contains($mime, 'spreadsheet') ? $this->xlsxRows($content) : $this->csvRows($content);
        $rows = array_values(array_filter($rows, fn (array $r) => implode('', $r) !== ''));
        if ($rows === []) {
            return ['title_lines' => [], 'columns' => [], 'rows' => []];
        }

        $width = $this->tableWidth($rows);
        $headerAt = 0;
        foreach ($rows as $i => $row) {
            if (count($row) >= $width) {
                $headerAt = $i;
                break;
            }
        }

        $titles = array_map(fn (array $r) => trim(implode(' ', array_filter($r, fn ($c) => $c !== ''))), array_slice($rows, 0, $headerAt));
        $columns = array_slice($rows[$headerAt], 0, $width);
        $body = array_map(fn (array $r) => array_pad(array_slice($r, 0, $width), $width, ''), array_slice($rows, $headerAt + 1));

        return ['title_lines' => $titles, 'columns' => $columns, 'rows' => $body];
    }

    /** @return array<int, array<int, string>> */
    private function csvRows(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $rows[] = array_map(fn ($cell) => $this->clean((string) $cell), $row);
        }
        fclose($handle);

        return $rows;
    }

    /** First sheet of an XLSX (inline strings, or shared strings). @return array<int, array<int, string>> */
    private function xlsxRows(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'rpt');
        file_put_contents($path, $content);
        $zip = new ZipArchive;

        try {
            if ($zip->open($path) !== true) {
                throw new RuntimeException('The report file could not be read.');
            }
            $shared = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                foreach (simplexml_load_string($xml)->si as $si) {
                    $shared[] = trim((string) $si->t) !== '' ? (string) $si->t : (string) implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]') ?: []));
                }
            }
            $sheet = simplexml_load_string((string) $zip->getFromName('xl/worksheets/sheet1.xml'));
        } finally {
            $zip->close();
            @unlink($path);
        }

        $rows = [];
        foreach ($sheet->sheetData->row ?? [] as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $index = $this->columnIndex((string) $c['r']);
                $value = match ((string) $c['t']) {
                    'inlineStr' => (string) $c->is->t,
                    's' => $shared[(int) $c->v] ?? '',
                    default => (string) $c->v,
                };
                $cells[$index] = $this->clean($value);
            }
            if ($cells !== []) {
                $cells += array_fill(0, max(array_keys($cells)) + 1, '');
                ksort($cells);
            }
            $rows[] = array_values($cells);
        }

        return $rows;
    }

    /** Most common row length of the file (ties → the wider). */
    private function tableWidth(array $rows): int
    {
        $counts = [];
        foreach ($rows as $row) {
            $counts[count($row)] = ($counts[count($row)] ?? 0) + 1;
        }
        $best = max($counts);

        return max(array_keys(array_filter($counts, fn ($n) => $n === $best)));
    }

    /** Undo the export's formula-injection guard ('=…, '+…, '-…, '@…) and trim. */
    private function clean(string $cell): string
    {
        $cell = trim($cell);

        return preg_match("/^'[=+\\-@\t\r]/", $cell) ? substr($cell, 1) : $cell;
    }

    private function columnIndex(string $ref): int
    {
        $letters = preg_replace('/\d+/', '', $ref) ?: 'A';
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }
}
