<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\User\EmployeeIdService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

/**
 * "Import Employees" on the employee list (UserController::importEmployees +
 * EmployeeImportService): users-table-only bulk import from .xlsx / .csv.
 * Shared dev DB — every imported user is removed in tearDown.
 */
class EmployeeImportTest extends TestCase
{
    private User $admin;
    private string $tag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tag = uniqid();
    }

    protected function tearDown(): void
    {
        User::withoutGlobalScopes()->where('email', 'like', "%.{$this->tag}@phpunit.test")->forceDelete();
        parent::tearDown();
    }

    private function rows(): array
    {
        return [
            ['Name', 'Email', 'Mobile No', 'Password'],
            ['Import One', "one.{$this->tag}@phpunit.test", $this->mobile(), 'secret1'],
            ['Import Two', "two.{$this->tag}@phpunit.test", $this->mobile(), 'secret2'],
        ];
    }

    private function mobile(): string
    {
        do {
            $m = (string) random_int(6000000000, 9999999999);
        } while (User::withoutGlobalScopes()->where('contact', $m)->exists());

        return $m;
    }

    private function csv(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'imp') . '.csv';
        $h = fopen($path, 'w');
        foreach ($rows as $r) {
            fputcsv($h, $r);
        }
        fclose($h);

        return new UploadedFile($path, 'employees.csv', 'text/csv', null, true);
    }

    /** Minimal real .xlsx: shared strings for text, a numeric cell for the mobile. */
    private function xlsx(array $rows): UploadedFile
    {
        $strings = [];
        $sheetRows = '';
        foreach ($rows as $i => $r) {
            $cells = '';
            foreach ($r as $c => $value) {
                $ref = chr(65 + $c) . ($i + 1);
                if ($i > 0 && $c === 2) {
                    $cells .= "<c r=\"{$ref}\"><v>{$value}</v></c>";
                } else {
                    $strings[] = htmlspecialchars($value);
                    $cells .= "<c r=\"{$ref}\" t=\"s\"><v>" . (count($strings) - 1) . '</v></c>';
                }
            }
            $sheetRows .= '<row r="' . ($i + 1) . "\">{$cells}</row>";
        }

        $path = tempnam(sys_get_temp_dir(), 'imp') . '.xlsx';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . implode('', array_map(fn ($s) => "<si><t>{$s}</t></si>", $strings)) . '</sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $sheetRows . '</sheetData></worksheet>');
        $zip->close();

        return new UploadedFile($path, 'employees.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function assertImported(array $rows): void
    {
        foreach (array_slice($rows, 1) as [$name, $email, $mobile, $password]) {
            $user = User::withoutGlobalScopes()->where('email', $email)->firstOrFail();
            $this->assertSame($name, $user->name);
            $this->assertSame($mobile, (string) $user->contact);
            $this->assertSame((int) $this->admin->tenant_id, (int) $user->tenant_id);
            $this->assertSame('employee', $user->role);
            $this->assertSame(1, (int) $user->status);
            $this->assertSame(EmployeeIdService::generate($user->tenant_id, $user->id), $user->employee_id);
            $this->assertTrue(\Hash::check($password, $user->password));
        }
    }

    public function test_imports_users_from_csv(): void
    {
        $rows = $this->rows();
        $this->actingAs($this->admin)
            ->post(route('employee.import'), ['file' => $this->csv($rows)])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertImported($rows);
    }

    public function test_imports_users_from_xlsx_with_numeric_mobile(): void
    {
        $rows = $this->rows();
        $this->actingAs($this->admin)
            ->post(route('employee.import'), ['file' => $this->xlsx($rows)])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertImported($rows);
    }

    public function test_any_invalid_row_imports_nothing_and_reports_rows(): void
    {
        $rows = $this->rows();
        $rows[] = ['Dup Email', $rows[1][1], $this->mobile(), 'secret3'];     // repeated email
        $rows[] = ['', "bad.{$this->tag}@phpunit.test", '123', 'x'];           // missing name, bad mobile, short password

        $res = $this->actingAs($this->admin)
            ->postJson(route('employee.import'), ['file' => $this->csv($rows)])
            ->assertStatus(422)->assertJson(['success' => false]);

        $this->assertEqualsCanonicalizing([4, 5], array_column($res->json('errors'), 'row'));
        $this->assertSame(0, User::withoutGlobalScopes()->where('email', 'like', "%.{$this->tag}@phpunit.test")->count());
    }

    public function test_template_download_and_role_gate(): void
    {
        $this->actingAs($this->admin)->get(route('employee.import.template'))
            ->assertOk()->assertHeader('content-disposition');

        $employee = User::withoutGlobalScopes()->where('role', 'employee')->where('status', 1)->where('tenant_id', $this->admin->tenant_id)->first();
        if ($employee) {
            $this->actingAs($employee)->post(route('employee.import'), ['file' => $this->csv($this->rows())]);
            $this->assertSame(0, User::withoutGlobalScopes()->where('email', 'like', "%.{$this->tag}@phpunit.test")->count());
        }
    }
}
