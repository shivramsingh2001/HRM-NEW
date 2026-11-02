<?php

namespace App\Support\Spreadsheet;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Reads the first worksheet of an uploaded .xlsx, or a .csv, into rows of
 * 0-indexed string cells (header row included). Values only — no formulas,
 * no styles — which avoids pulling in a spreadsheet package. Shared by the
 * employee import and the site import.
 */
class SpreadsheetReader
{
    /** @return array<int,array<int,string>> */
    public function read(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());

        return match ($ext) {
            'xlsx' => $this->readXlsx($file->getRealPath()),
            'csv', 'txt' => $this->readCsv($file->getRealPath()),
            default => throw new RuntimeException('Please upload an Excel (.xlsx) or CSV file.'),
        };
    }

    /** @return array<int,array<int,string>> rows of 0-indexed cells */
    public function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new RuntimeException('Could not read the file.');
        }

        $rows = [];
        while (($cells = fgetcsv($handle)) !== false) {
            if ($rows === [] && isset($cells[0])) {
                $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cells[0]); // UTF-8 BOM
            }
            $rows[] = $cells;
        }
        fclose($handle);

        return $rows;
    }

    /** @return array<int,array<int,string>> */
    public function readXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Could not open the Excel file.');
        }

        try {
            $shared = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                foreach ($this->xml($xml)->si as $si) {
                    $shared[] = $this->inlineText($si);
                }
            }

            $sheetXml = $zip->getFromName($this->firstSheetPath($zip));
            if ($sheetXml === false) {
                throw new RuntimeException('The Excel file has no worksheet.');
            }

            $rows = [];
            foreach ($this->xml($sheetXml)->sheetData->row as $row) {
                $cells = [];
                foreach ($row->c as $c) {
                    $col = $this->columnIndex((string) $c['r']);
                    $type = (string) $c['t'];
                    $cells[$col] = match ($type) {
                        's' => $shared[(int) $c->v] ?? '',
                        'inlineStr' => $this->inlineText($c->is),
                        default => (string) $c->v,
                    };
                }
                if ($cells) {
                    $max = max(array_keys($cells));
                    $rows[] = array_replace(array_fill(0, $max + 1, ''), $cells);
                }
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook !== false && $rels !== false) {
            $wb = $this->xml($workbook);
            $sheet = $wb->sheets->sheet[0] ?? null;
            $rid = $sheet ? (string) $sheet->attributes('r', true)->id : '';

            foreach ($this->xml($rels)->Relationship as $rel) {
                if ((string) $rel['Id'] === $rid) {
                    $target = ltrim((string) $rel['Target'], '/');
                    return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private function xml(string $xml): SimpleXMLElement
    {
        $doc = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
        if ($doc === false) {
            throw new RuntimeException('The Excel file is damaged.');
        }

        return $doc;
    }

    /** Text of a <si>/<is> node: plain <t> or rich-text runs <r><t>. */
    private function inlineText(?SimpleXMLElement $node): string
    {
        if (!$node) {
            return '';
        }
        if (isset($node->t)) {
            return (string) $node->t;
        }

        $text = '';
        foreach ($node->r as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    /** "C12" → 2 */
    private function columnIndex(string $ref): int
    {
        $letters = preg_replace('/\d+/', '', strtoupper($ref));
        $index = 0;
        foreach (str_split($letters) as $ch) {
            $index = $index * 26 + (ord($ch) - 64);
        }

        return $index - 1;
    }
}
