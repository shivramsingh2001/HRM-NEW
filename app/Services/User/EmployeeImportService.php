<?php

namespace App\Services\User;

use App\Models\User;
use App\Support\Spreadsheet\SpreadsheetReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

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

    public function __construct(private SpreadsheetReader $reader = new SpreadsheetReader())
    {
    }

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
        $sheet = $this->reader->read($file);

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
}
