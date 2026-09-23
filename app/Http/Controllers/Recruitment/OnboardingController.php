<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLocation;
use App\Models\CandidateDocument;
use App\Models\CompanyBranch;
use App\Models\JobApplication;
use App\Models\LeaveType;
use App\Models\OnboardingAssignment;
use App\Models\OnboardingTaskItem;
use App\Services\Recruitment\EmployeeProvisioningService;
use App\Services\Recruitment\OnboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class OnboardingController extends Controller
{
    public function __construct(
        protected OnboardingService $onboarding,
        protected EmployeeProvisioningService $provisioning
    ) {
    }

    /**
     * The onboarding checklist workspace for one application — document
     * upload/verify + the per-category task_items list + the "Create
     * Employee" action (only enabled once the checklist is complete).
     */
    public function show($applicationId)
    {
        try {
            $application = JobApplication::with(['candidate', 'jobOpening'])->findOrFail($applicationId);
            $assignment = $application->onboardingAssignment;

            if (!$assignment) {
                return redirect()
                    ->route('recruitment.interview-details', $applicationId)
                    ->with('error', 'Onboarding has not started for this application yet.');
            }

            $assignment->load(['taskItems.task', 'taskItems.completedBy', 'taskItems.assignedTo', 'jobOffer']);
            $documents = CandidateDocument::where('candidate_id', $assignment->candidate_id)
                ->with(['uploadedBy', 'verifiedBy'])
                ->orderBy('created_at', 'desc')
                ->get();

            $branches = AttendanceLocation::where('status', 1)->orderBy('name')->get();
            $companyBranches = CompanyBranch::where('status', 1)->orderBy('name')->get();
            $leaveTypes = LeaveType::where('status', 1)->orderBy('name')->get();

            return view('client.recruitment.application.onboarding', compact(
                'application',
                'assignment',
                'documents',
                'branches',
                'companyBranches',
                'leaveTypes'
            ));
        } catch (\Exception $e) {
            Log::error('Failed to load onboarding checklist: ' . $e->getMessage());
            return redirect()->route('job-openings.index')->with('error', 'Failed to load onboarding checklist.');
        }
    }

    public function uploadDocument(Request $request, $assignmentId)
    {
        try {
            $data = $request->validate([
                'document_type' => 'required|in:' . implode(',', array_keys(CandidateDocument::$documentTypes)),
                'document_name' => 'nullable|string|max:255',
                'file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            ]);

            $assignment = OnboardingAssignment::findOrFail($assignmentId);

            $file = $request->file('file');
            $directory = public_path('uploads/candidate_documents/' . $assignment->candidate_id);
            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true, true);
            }
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move($directory, $fileName);

            $document = $this->onboarding->uploadDocument($assignment, [
                'document_type' => $data['document_type'],
                'document_name' => $data['document_name'] ?? $file->getClientOriginalName(),
                'file_url' => 'uploads/candidate_documents/' . $assignment->candidate_id . '/' . $fileName,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getClientMimeType(),
            ]);

            return response()->json(['success' => true, 'message' => 'Document uploaded.', 'data' => $document]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Document upload failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to upload document.'], 500);
        }
    }

    public function verifyDocument($documentId)
    {
        try {
            $this->onboarding->verifyDocument(CandidateDocument::findOrFail($documentId));
            return response()->json(['success' => true, 'message' => 'Document verified.']);
        } catch (\Exception $e) {
            Log::error('Document verify failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to verify document.'], 500);
        }
    }

    public function rejectDocument($documentId)
    {
        try {
            $this->onboarding->rejectDocument(CandidateDocument::findOrFail($documentId));
            return response()->json(['success' => true, 'message' => 'Document marked unverified.']);
        } catch (\Exception $e) {
            Log::error('Document reject failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update document.'], 500);
        }
    }

    public function updateTaskItem(Request $request, $itemId)
    {
        try {
            $data = $request->validate([
                'status' => 'required|in:pending,in_progress,completed,skipped,overdue',
                'remarks' => 'nullable|string|max:500',
            ]);

            $item = $this->onboarding->updateTaskItem(OnboardingTaskItem::findOrFail($itemId), $data['status'], $data['remarks'] ?? null);

            return response()->json(['success' => true, 'message' => 'Task updated.', 'data' => $item]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Task item update failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update task.'], 500);
        }
    }

    public function complete($assignmentId)
    {
        try {
            $this->onboarding->completeOnboarding(OnboardingAssignment::findOrFail($assignmentId));
            return response()->json(['success' => true, 'message' => 'Onboarding checklist completed. Ready to create employee.']);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Complete onboarding failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to complete onboarding.'], 500);
        }
    }

    public function hire(Request $request, $assignmentId)
    {
        try {
            $data = $request->validate([
                'type' => 'required|in:office,field',
                'branch' => 'required|string',
                'company_branch' => 'nullable|exists:company_branches,id',
                'role' => 'nullable|in:employee,manager,admin,hr',
                'leave_type_assigned' => 'nullable|array',
                'leave_type_assigned.*' => 'exists:leave_types,id',
            ]);

            $assignment = OnboardingAssignment::findOrFail($assignmentId);
            $user = $this->provisioning->hire($assignment, $data);

            return response()->json([
                'success' => true,
                'message' => "Employee {$user->name} ({$user->employee_id}) created successfully!",
                'data' => ['user_id' => $user->id, 'employee_id' => $user->employee_id],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Hire failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create employee: ' . $e->getMessage()], 500);
        }
    }
}
