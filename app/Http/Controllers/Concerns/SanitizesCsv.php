<?php

namespace App\Http\Controllers\Concerns;

trait SanitizesCsv
{
    /**
     * Write a CSV row, neutralising spreadsheet formula injection: any cell
     * beginning with = + - @ (or tab / CR) is prefixed with a single quote so
     * Excel / Google Sheets treat it as text rather than a formula.
     */
    protected function writeCsvRow($handle, array $row): void
    {
        $safe = array_map(function ($cell) {
            $cell = (string) ($cell ?? '');
            if ($cell !== '' && preg_match('/^[=+\-@\t\r]/', $cell)) {
                return "'" . $cell;
            }
            return $cell;
        }, $row);

        fputcsv($handle, $safe);
    }
}
