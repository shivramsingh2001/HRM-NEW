<?php

namespace App\Services\User;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Bulk "Import Employees" from an Excel (.xlsx) or CSV sheet with four
 * columns: Name, Email, Mobile No, Password. Writes `users` rows only —
 * job/personal/bank details are filled later from the employee profile.
 *
 * All-or-nothing: every row is validated first; if any row is invalid nothing
 * is imported and the per-row errors are returned.
 */
class EmployeeImportService
{
    public const MAX_ROWS = 1000;

    /** Header aliases (lower-cased, spaces/underscores/dots removed) → field. */
    private const HEADERS = [
        'name' => 'name', 'employeename' => 'name', 'fullname' => 'name',
        'email' => 'email', 'emailid' => 'email', 'emailaddress' => 'email',
        'mobileno' => 'contact', 'mobile' => 'contact', 'mobilenumber' => 'contact',
        'contact' => 'contact', 'contactno' => 'contact', 'phone' => 'contact', 'phoneno' => 'contact',
        'password' => 'password',
    ];

    private const FIELDS = ['name', 'email', 'contact', 'password'];

    /**
     * @return array{created:int, errors:array<int,array{row:int,messages:array<int,string>}>}
     */
    public function import(UploadedFile $file, int $tenantId): array
    {
        $rows = $this->readRows($file);
        if (empty($rows)) {
            throw new RuntimeException('The file has no employee rows.');
        }
        if (count($rows) > self::MAX_ROWS) {
            throw new RuntimeException('Maximum ' . self::MAX_ROWS . ' employees per file.');
        }

        $errors = [];
        $seen = ['email' => [], 'contact' => []];

        foreach ($rows as $rowNo => $row) {
            $v = Validator::make($row, [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'contact' => 'required|digits:10|unique:users,contact',
                'password' => 'required|min:6|max:10',
            ], [], ['contact' => 'mobile no']);

            $messages = $v->fails() ? $v->errors()->all() : [];

            foreach (['email' => 'Email', 'contact' => 'Mobile no'] as $field => $label) {
                $key = strtolower((string) $row[$field]);
                if ($key === '') {
                    continue;
                }
                if (isset($seen[$field][$key])) {
                    $messages[] = "{$label} is repeated in row {$seen[$field][$key]}.";
                } else {
                    $seen[$field][$key] = $rowNo;
                }
            }

            if ($messages) {
                $errors[] = ['row' => $rowNo, 'messages' => $messages];
            }
        }

        if ($errors) {
            return ['created' => 0, 'errors' => $errors];
        }

        DB::transaction(function () use ($rows, $tenantId) {
            foreach ($rows as $row) {
                $user = (new User())->forceFill([
                    'name' => $row['name'],
                    'employee_id' => null,
                    'email' => $row['email'],
                    'contact' => $row['contact'],
                    'password' => Hash::make($row['password']),
                    'status' => 1,
                    'role' => 'employee',
                    'tenant_id' => $tenantId,
                ]);
                $user->save();

                // Same two-step as the Add Employee wizard: the id needs the new user id.
                $user->employee_id = EmployeeIdService::generate($tenantId, $user->id);
                $user->save();
            }
        });

        return ['created' => count($rows), 'errors' => []];
    }

    /**
     * Data rows keyed by their sheet row number (header = row 1), each with
     * name/email/contact/password strings. Blank rows are skipped.
     *
     * @return array<int,array<string,string>>
     */
    public function readRows(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $sheet = match ($ext) {
            'xlsx' => $this->readXlsx($file->getRealPath()),
            'csv', 'txt' => $this->readCsv($file->getRealPath()),
            default => throw new RuntimeException('Please upload an Excel (.xlsx) or CSV file.'),
        };

        if (empty($sheet)) {
            return [];
        }

        // Map columns by header; fall back to Name, Email, Mobile, Password order.
        $header = array_shift($sheet);
        $map = [];
        foreach ($header as $col => $title) {
            $key = preg_replace('/[\s_.\-]+/', '', strtolower(trim((string) $title)));
            if (isset(self::HEADERS[$key]) && !in_array(self::HEADERS[$key], $map, true)) {
                $map[$col] = self::HEADERS[$key];
            }
        }
        if (count($map) < count(self::FIELDS)) {
            throw new RuntimeException('Header row must have the columns: Name, Email, Mobile No, Password.');
        }

        $rows = [];
        foreach ($sheet as $i => $cells) {
            $row = array_fill_keys(self::FIELDS, '');
            foreach ($map as $col => $field) {
                $row[$field] = trim((string) ($cells[$col] ?? ''));
            }
            if (implode('', $row) === '') {
                continue;
            }
            // Excel stores numbers like 9876543210 as "9876543210" or "9.87654321E9".
            if (is_numeric($row['contact'])) {
                $row['contact'] = number_format((float) $row['contact'], 0, '', '');
            }
            $rows[$i + 2] = $row;
        }

        return $rows;
    }

    /** @return array<int,array<int,string>> rows of 0-indexed cells */
    private function readCsv(string $path): array
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

    /**
     * Minimal .xlsx reader (first worksheet, values only) — avoids pulling in a
     * spreadsheet package for a four-column import.
     *
     * @return array<int,array<int,string>>
     */
    private function readXlsx(string $path): array
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
