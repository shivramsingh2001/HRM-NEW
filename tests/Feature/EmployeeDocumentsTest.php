<?php

namespace Tests\Feature;

use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Dynamic employee documents (2026-09-19). Runs against the shared dev DB
 * (no RefreshDatabase) — mirrors FieldTrackingTest's setUp/tearDown pattern.
 * Files land on the real filesystem (the controller uses File::move(), not
 * the Storage facade), so tests clean up both DB rows and written files.
 */
class EmployeeDocumentsTest extends TestCase
{
    private int $tenantId;
    private User $employee;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) \DB::table('users')
            ->whereNotNull('tenant_id')
            ->select('tenant_id')
            ->groupBy('tenant_id')
            ->havingRaw('count(*) >= 2')
            ->orderByRaw('count(*) desc')
            ->value('tenant_id');

        $this->employee = User::where('tenant_id', $this->tenantId)
            ->where('role', 'employee')
            ->whereNotNull('employee_id')
            ->firstOrFail();

        $this->admin = User::where('tenant_id', $this->tenantId)->where('role', 'admin')->first()
            ?? User::where('tenant_id', $this->tenantId)->where('role', 'hr')->first();

        if (!$this->admin) {
            $this->markTestSkipped('no admin/hr user in tenant');
        }

        EmployeeDocument::where('user_id', $this->employee->id)->forceDelete();
    }

    protected function tearDown(): void
    {
        $paths = EmployeeDocument::withTrashed()->where('user_id', $this->employee->id)->pluck('file_path');
        foreach ($paths as $path) {
            $full = public_path($path);
            if (File::exists($full)) {
                File::delete($full);
            }
        }
        EmployeeDocument::withTrashed()->where('user_id', $this->employee->id)->forceDelete();

        parent::tearDown();
    }

    public function test_uploading_a_predefined_and_an_other_document_creates_two_rows(): void
    {
        $res = $this->actingAs($this->admin)->post(route('employee.save.step'), [
            'employee_id' => $this->employee->employee_id,
            'step' => 7,
            'documents' => [
                [
                    'document_type' => 'tenth_marksheet',
                    'document_name' => 'Board Marksheet',
                    'file' => UploadedFile::fake()->create('marksheet.pdf', 100, 'application/pdf'),
                ],
                [
                    'document_type' => 'other',
                    'document_type_other' => 'Certificate of Merit',
                    'file' => UploadedFile::fake()->create('merit.pdf', 100, 'application/pdf'),
                ],
            ],
        ]);

        $res->assertOk()->assertJsonPath('success', true);

        $docs = EmployeeDocument::where('user_id', $this->employee->id)->get();
        $this->assertSame(2, $docs->count());

        $marksheet = $docs->firstWhere('document_type', 'tenth_marksheet');
        $this->assertNotNull($marksheet);
        $this->assertSame('Board Marksheet', $marksheet->document_name);
        $this->assertTrue(File::exists(public_path($marksheet->file_path)));

        $other = $docs->firstWhere('document_type', 'other');
        $this->assertNotNull($other);
        $this->assertSame('Certificate of Merit', $other->document_type_other);
    }

    public function test_other_type_without_specify_text_fails_validation(): void
    {
        $res = $this->actingAs($this->admin)->post(route('employee.save.step'), [
            'employee_id' => $this->employee->employee_id,
            'step' => 7,
            'documents' => [
                [
                    'document_type' => 'other',
                    'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
                ],
            ],
        ]);

        $res->assertStatus(422);
        $this->assertArrayHasKey('documents.0.document_type_other', $res->json('errors'));
        $this->assertSame(0, EmployeeDocument::where('user_id', $this->employee->id)->count());
    }

    public function test_resubmitting_without_a_prior_row_removes_it(): void
    {
        $existing = EmployeeDocument::create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->employee->id,
            'document_type' => 'pan_card',
            'file_path' => 'uploads/users/' . $this->employee->employee_id . '/documents/preexisting.pdf',
        ]);
        File::makeDirectory(public_path('uploads/users/' . $this->employee->employee_id . '/documents'), 0755, true, true);
        File::put(public_path($existing->file_path), 'dummy');

        $res = $this->actingAs($this->admin)->post(route('employee.save.step'), [
            'employee_id' => $this->employee->employee_id,
            'step' => 7,
            'documents' => [],
        ]);

        $res->assertOk()->assertJsonPath('success', true);

        $this->assertNull(EmployeeDocument::find($existing->id));
        $this->assertNotNull(EmployeeDocument::withTrashed()->find($existing->id));
    }

    public function test_show_page_lists_uploaded_documents(): void
    {
        EmployeeDocument::create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->employee->id,
            'document_type' => 'aadhaar_card',
            'file_path' => 'uploads/users/' . $this->employee->employee_id . '/documents/aadhaar.pdf',
        ]);

        $res = $this->actingAs($this->admin)->get(route('employee.show', encrypt($this->employee->id)));

        $res->assertOk();
        $res->assertViewHas('user', function ($user) {
            return collect($user->documents)->contains(fn ($doc) => $doc['label'] === 'Aadhaar Card');
        });
    }
}
