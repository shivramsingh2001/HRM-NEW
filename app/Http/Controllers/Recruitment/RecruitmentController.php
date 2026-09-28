<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\JobOpening;
use App\Models\Candidate;
use App\Models\JobApplication;
use App\Mail\ApplicationReceivedMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RecruitmentController extends Controller
{
    public function index(Request $request)
    {

        try {
            $query = JobOpening::with(['department', 'designation'])
                ->where('status', 'published');
                // ->where('published_date', '<=', now());

            // Apply search filter
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%")
                        ->orWhere('job_code', 'LIKE', "%{$search}%")
                        ->orWhere('skills_required', 'LIKE', "%{$search}%");
                });
            }

            // Apply location filter
            if ($request->filled('location')) {
                $query->where('location', 'LIKE', "%{$request->location}%");
            }

            // Apply employment type filter
            if ($request->filled('employment_type')) {
                $query->where('employment_type', $request->employment_type);
            }

            $jobs = $query->orderBy('created_at', 'desc')->paginate(12);

            // Statistics for display
            $stats = [
                'total' => JobOpening::where('status', 'published')->count(),
                'departments' => JobOpening::where('status', 'published')->distinct('department_id')->count('department_id'),
                'locations' => JobOpening::where('status', 'published')->whereNotNull('location')->distinct('location')->count('location'),
                'hired' => JobApplication::where('current_stage', 'hired')->count(),
            ];

            return view('client.recruitment.recruitment.job', compact('jobs', 'stats'));
        } catch (\Exception $e) {
            Log::error('Failed to fetch jobs: ' . $e->getMessage());
            return view('public.jobs.index', ['jobs' => collect([]), 'stats' => []])
                ->with('error', 'Unable to load jobs at the moment.');
        }
    }

    /**
     * Show job application form
     */
    public function showApplyForm($id)
    {
        try {
            $job = JobOpening::with(['department', 'designation'])
                ->where('status', 'published')
                ->findOrFail($id);

            if (request()->has('success')) {
                return view('client.recruitment.recruitment.application-form', [
                    'job' => $job,
                    'success' => true
                ]);
            }

            return view('client.recruitment.recruitment.application-form', compact('job'));
        } catch (\Exception $e) {
            Log::error('Job not found: ' . $e->getMessage());
            abort(404, 'Job opening not found.');
        }
    }

    /**
     * Process job application submission
     */
    public function submitApplication(Request $request)
    {
        try {
            // Validation rules
            $rules = [
                'job_opening_id' => 'required|exists:job_openings,id',
                'first_name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:20',
                'alternate_phone' => 'nullable|string|max:20',
                'current_location' => 'nullable|string|max:255',
                'preferred_location' => 'nullable|string|max:255',
                'total_experience' => 'nullable|string|max:50',
                'current_company' => 'nullable|string|max:255',
                'current_ctc' => 'nullable|numeric|min:0',
                'expected_ctc' => 'nullable|numeric|min:0',
                'notice_period' => 'nullable|string|max:50',
                'qualification' => 'nullable|string',
                'skills' => 'nullable|string',
                'source' => 'nullable|string|in:career_page,linkedin,naukri,indeed,referral,walkin,other',
                'source_detail' => 'nullable|string|max:255',
                'cover_letter' => 'nullable|string',
                'resume' => 'required|file|mimes:pdf,doc,docx|max:5120',
            ];

            $validated = $request->validate($rules);

            DB::beginTransaction();

            $jobOpening = JobOpening::findOrFail($validated['job_opening_id']);

            // Check if candidate already exists
            $existingCandidate = Candidate::where('email', $validated['email'])->first();

            // Upload resume
            $resumeFile = $request->file('resume');
            $resumePath = $this->uploadResume($resumeFile);
            $resumeOriginalName = $resumeFile->getClientOriginalName();

            if ($existingCandidate) {
                // Check if already applied for this job
                $existingApplication = JobApplication::where('candidate_id', $existingCandidate->id)
                    ->where('job_opening_id', $jobOpening->id)
                    ->exists();

                if ($existingApplication) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'You have already applied for this position.'
                    ], 422);
                }

                $candidate = $existingCandidate;

                // Update candidate information
                $candidate->update([
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'phone' => $validated['phone'],
                    'alternate_phone' => $validated['alternate_phone'] ?? $candidate->alternate_phone,
                    'current_location' => $validated['current_location'] ?? $candidate->current_location,
                    'preferred_location' => $validated['preferred_location'] ?? $candidate->preferred_location,
                    'total_experience' => $validated['total_experience'] ?? $candidate->total_experience,
                    'current_company' => $validated['current_company'] ?? $candidate->current_company,
                    'current_ctc' => $validated['current_ctc'] ?? $candidate->current_ctc,
                    'expected_ctc' => $validated['expected_ctc'] ?? $candidate->expected_ctc,
                    'notice_period' => $validated['notice_period'] ?? $candidate->notice_period,
                    'qualification' => $validated['qualification'] ?? $candidate->qualification,
                    'skills' => $validated['skills'] ?? $candidate->skills,
                    'resume_url' => $resumePath,
                    'resume_original_name' => $resumeOriginalName,
                ]);
            } else {
                // Create new candidate
                $candidate = Candidate::create([
                    'candidate_code' => $this->generateCandidateCode(),
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                    'alternate_phone' => $validated['alternate_phone'] ?? null,
                    'current_location' => $validated['current_location'] ?? null,
                    'preferred_location' => $validated['preferred_location'] ?? null,
                    'total_experience' => $validated['total_experience'] ?? null,
                    'current_company' => $validated['current_company'] ?? null,
                    'current_ctc' => $validated['current_ctc'] ?? null,
                    'expected_ctc' => $validated['expected_ctc'] ?? null,
                    'notice_period' => $validated['notice_period'] ?? null,
                    'qualification' => $validated['qualification'] ?? null,
                    'skills' => $validated['skills'] ?? null,
                    'source' => $validated['source'] ?? 'career_page',
                    'source_detail' => $validated['source_detail'] ?? null,
                    'resume_url' => $resumePath,
                    'resume_original_name' => $resumeOriginalName,
                    'status' => 'new'
                ]);
            }

            // Create job application
            $application = JobApplication::create([
                'application_code' => $this->generateApplicationCode(),
                'job_opening_id' => $jobOpening->id,
                'candidate_id' => $candidate->id,
                'source' => 'external',
                'applied_date' => now(),
                'expected_salary' => $validated['expected_ctc'] ?? null,
                'cover_letter' => $validated['cover_letter'] ?? null,
                'current_stage' => 'application_received',
                'status' => 'active'
            ]);

            DB::commit();

            try {
                Mail::to($candidate->email)->send(new ApplicationReceivedMail($candidate, $jobOpening));
            } catch (\Exception $e) {
                Log::error('Failed to send application received email: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Application submitted successfully!',
                'data' => [
                    'application_id' => $application->id,
                    'candidate_name' => $candidate->full_name
                ]
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Application submission failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit application. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    private function generateCandidateCode()
    {
        $prefix = 'CAN';
        $year = date('Y');
        $maxAttempts = 10;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $maxId = Candidate::max('id') ?? 0;
            $nextNumber = $maxId + 1 + $attempt;
            $code = $prefix . $year . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            if (!Candidate::where('candidate_code', $code)->exists()) {
                return $code;
            }
        }

        // Fallback
        return $prefix . $year . date('YmdHis') . rand(100, 999);
    }
    private function generateApplicationCode()
    {
        $prefix = 'APP';
        $year = date('Y');
        $maxAttempts = 10;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $maxId = JobApplication::max('id') ?? 0;
            $nextNumber = $maxId + 1 + $attempt;
            $code = $prefix . $year . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            if (!JobApplication::where('application_code', $code)->exists()) {
                return $code;
            }
        }

        // Fallback: Use timestamp
        return $prefix . $year . date('YmdHis') . rand(100, 999);
    }

    /**
     * Upload resume file
     */
    private function uploadResume($file)
    {
        return $file ? file_storage()->upload($file, 'candidate_resume')->path : null;
    }
}
