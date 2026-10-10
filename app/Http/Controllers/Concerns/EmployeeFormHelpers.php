<?php

namespace App\Http\Controllers\Concerns;

use App\Models\EmployeeDocument;
use App\Models\User;
use App\Models\UserBasicDetail;
use App\Services\Payroll\PayrollStructureAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Shared by the Add / Edit Employee wizards: file uploads, job location input,
 * reporting heads, documents, the dynamic salary structure from the wizard.
 * Moved out of UserController unchanged (code-quality plan, Phase 4).
 */
trait EmployeeFormHelpers
{
    /**
     * Handle file uploads. Documents (experience letter, marksheets, etc.)
     * are handled separately by saveEmployeeDocuments() since an employee
     * can now have any number of them — see docs/modules.md.
     */
    private function handleFileUploads($request, $employeeId)
    {
        $filePaths = [];

        if ($request->hasFile('profile_photo')) {
            // Replace: the new photo is stored first, the old one removed after commit.
            $oldPhoto = $employeeId
                ? UserBasicDetail::whereHas('user', fn ($q) => $q->where('employee_id', $employeeId))->value('profile_image')
                : null;

            $filePaths['profile_photo'] = file_storage()
                ->replace($oldPhoto, $request->file('profile_photo'), 'profile_photo')
                ->path;
        }

        return $filePaths;
    }

    /**
     * Attendance Location and Work Type are optional. "All Locations" (legacy
     * value 0) and an empty choice are both stored as NULL — office_branch has
     * a FK to attendance_locations, so 0 can't be saved. NULL = any location.
     */
    private function normalizeJobLocationInput(Request $request): void
    {
        $branch = $request->input('branch');
        if ($branch === '' || $branch === '0' || $branch === 0) {
            $request->merge(['branch' => null]);
        }
        if ($request->input('type') === '') {
            $request->merge(['type' => null]);
        }
    }

    private function jobLocationRules(): array
    {
        return [
            'type' => 'nullable|in:office,field',
            'branch' => [
                'nullable',
                Rule::exists('attendance_locations', 'id')->where('tenant_id', auth()->user()->tenant_id),
            ],
        ];
    }

    /**
     * Persist the full multiselect reporting-head set for an employee.
     * The first id is treated as primary; returns the primary id (or null)
     * so callers can keep user_job_details.reporting_head in sync for the
     * ~100 existing single-head read call sites. Rejects self-reporting.
     */
    private function syncReportingHeads(User $user, array $reportingHeadIds): ?int
    {
        $reportingHeadIds = array_values(array_unique(array_filter(
            $reportingHeadIds,
            fn ($id) => (int) $id !== (int) $user->id
        )));

        // sync() writes pivot rows via a raw query, bypassing UserReportingHead's
        // TenantTrait::creating() hook — tenant_id must be set explicitly here.
        $syncData = [];
        foreach ($reportingHeadIds as $index => $headId) {
            $syncData[(int) $headId] = ['is_primary' => $index === 0, 'tenant_id' => $user->tenant_id];
        }

        $user->reportingHeads()->sync($syncData);

        return $reportingHeadIds[0] ?? null;
    }

    /**
     * Persist the employee's dynamic document list. $rows is the validated
     * `documents` array (each row: optional id, document_type,
     * document_type_other, document_name, optional file). Mirrors the
     * wizard's existing "this step's payload is the full authoritative
     * state" pattern — any previously-uploaded document not represented by
     * a row in $rows is removed.
     */
    private function saveEmployeeDocuments(User $user, array $rows): void
    {
        $keptIds = [];

        foreach ($rows as $row) {
            $file = $row['file'] ?? null;
            $existingId = $row['id'] ?? null;

            if ($existingId) {
                $document = EmployeeDocument::where('user_id', $user->id)->find($existingId);
                if (! $document) {
                    continue;
                }
            } elseif ($file) {
                $document = new EmployeeDocument(['user_id' => $user->id]);
            } else {
                // New row with no file attached — nothing to store.
                continue;
            }

            $document->document_type = $row['document_type'];
            $document->document_type_other = $row['document_type'] === EmployeeDocument::TYPE_OTHER
                ? ($row['document_type_other'] ?? null)
                : null;
            $document->document_name = $row['document_name'] ?? null;

            if ($file) {
                $stored = file_storage()->replace($document->file_path, $file, 'employee_document', [
                    'tenant' => $user->tenant_id,
                    'user' => $user->id,
                ]);
                $document->file_path = $stored->path;
                $document->original_filename = $stored->originalName;
                $document->mime_type = $stored->mimeType;
                $document->file_size = $stored->size;
            }

            $document->tenant_id = $document->tenant_id ?? $user->tenant_id;
            $document->uploaded_by = auth()->id();
            $document->user_id = $user->id;
            $document->save();

            $keptIds[] = $document->id;
        }

        EmployeeDocument::where('user_id', $user->id)
            ->whereNotIn('id', $keptIds)
            ->get()
            ->each(function (EmployeeDocument $document) {
                file_storage()->deleteAfterCommit($document->file_path, 'employee_document');
                $document->delete();
            });
    }

    /**
     * Shared validation rules for the dynamic documents array, used by
     * every entry point that saves documents (wizard step 7, full update).
     */
    private function documentRows(Request $request): array
    {
        $validTypes = implode(',', array_keys(EmployeeDocument::$documentTypes));

        $validated = $request->validate([
            'documents' => 'nullable|array',
            'documents.*.id' => 'nullable|integer',
            'documents.*.document_type' => 'required_with:documents|string|in:'.$validTypes,
            'documents.*.document_type_other' => 'nullable|required_if:documents.*.document_type,other|string|max:100',
            'documents.*.document_name' => 'nullable|string|max:150',
            'documents.*.file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
        ]);

        return $validated['documents'] ?? [];
    }

    /**
     * Payroll rebuild — Phase 9. Once a tenant is on the dynamic engine, the
     * wizard/profile payroll step must stop writing into the legacy
     * user_payrolls table — that write was the one remaining bypass of the
     * per-tenant cutover flag, since it happened outside
     * UserPayrollController entirely. Creates/revises a real
     * PayrollEmployeeStructure instead, using the same flat wizard fields
     * mapped onto the tenant's own component catalog.
     *
     * @param  string  $defaultRevisionType  used only when the employee doesn't
     *                                       already have a current dynamic structure at a different date than
     *                                       the one submitted here — otherwise 'initial' (no prior structure) or
     *                                       an in-place update (identical effective date resubmitted) takes over.
     */
    private function assignDynamicStructureFromWizardValues(int $tenantId, User $user, array $validated, string $defaultRevisionType, string $reason): void
    {
        $service = app(PayrollStructureAssignmentService::class);
        $components = $service->componentsFromFlatValues($tenantId, $validated);
        $effectiveFrom = $validated['salary_effective_date'] ?? now()->toDateString();
        $ctc = (float) $validated['annual_ctc'];

        $existingForDate = $service->findForExactDate($tenantId, $user->id, $effectiveFrom);

        if ($existingForDate) {
            // Same date resubmitted — update the snapshot in place rather
            // than colliding with the (tenant_id, user_id, effective_from)
            // unique constraint or creating a duplicate revision.
            $service->updateComponentsInPlace($existingForDate, $components, $ctc);

            Log::info('Dynamic payroll structure updated in place for employee: '.$user->employee_id);

            return;
        }

        $hasCurrentStructure = \App\Models\PayrollEmployeeStructure::forUser($user->id)->current()->exists();

        $structure = $service->assign($tenantId, $components, [
            'user_id' => $user->id,
            'ctc' => $ctc,
            'effective_from' => $effectiveFrom,
            'revision_type' => $hasCurrentStructure ? $defaultRevisionType : 'initial',
            'revision_reason' => $reason,
            'created_by' => auth()->id(),
            'source' => 'manual',
            'payroll_structure_id' => $validated['payroll_structure_id'] ?? null,
        ]);

        Log::info('Dynamic payroll structure '.$structure->status.' for employee: '.$user->employee_id);
    }
}
