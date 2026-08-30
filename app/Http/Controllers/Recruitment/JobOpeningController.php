<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\JobOpening;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Interview;
use App\Models\JobApplication;
use App\Models\JobOffer;
use App\Models\RecruitmentStage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;

// Email Mails
use App\Mail\ApplicationReceivedMail;
use App\Mail\ApplicationShortlistedMail;
use App\Mail\ApplicationRejectedMail;
use App\Mail\InterviewScheduledMail;
use App\Mail\InterviewFeedbackMail;
use App\Mail\OfferReleasedMail;
use App\Mail\OfferAcceptedMail;
use App\Mail\OfferRejectedMail;

class JobOpeningController extends Controller
{

    public function index(Request $request)
    {
        try {
            $query = JobOpening::with(['department', 'designation', 'hiringLead', 'createdBy']);

            // Apply filters
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            if ($request->has('department_id') && $request->department_id) {
                $query->where('department_id', $request->department_id);
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'LIKE', "%{$search}%")
                        ->orWhere('job_code', 'LIKE', "%{$search}%")
                        ->orWhere('location', 'LIKE', "%{$search}%");
                });
            }

            $jobOpenings = $query->orderBy('created_at', 'desc')->paginate(15);

            // Get statistics for dashboard
            $stats = [
                'total' => JobOpening::count(),
                'draft' => JobOpening::where('status', JobOpening::STATUS_DRAFT)->count(),
                'published' => JobOpening::where('status', JobOpening::STATUS_PUBLISHED)->count(),
                'closed' => JobOpening::where('status', JobOpening::STATUS_CLOSED)->count(),
                'on_hold' => JobOpening::where('status', JobOpening::STATUS_ON_HOLD)->count(),
            ];

            return view('client.recruitment.job-openings.index', compact('jobOpenings', 'stats'));
        } catch (\Exception $e) {
            Log::error('Failed to fetch job openings: ' . $e->getMessage());
            return back()->with('error', 'Failed to load job openings.');
        }
    }

    public function create()
    {
        try {
            $departments = Department::where('status', 1)->orderBy('name')->get();
            $designations = Designation::where('status', 1)->orderBy('name')->get();
            $hiringLeads = User::whereIn('role', ['hr', 'manager', 'admin', 'super_admin'])
                ->orderBy('name')
                ->get();

            $employmentTypes = JobOpening::$employmentTypes;
            $statuses = JobOpening::$statuses;

            return view('client.recruitment.job-openings.create', compact(
                'departments',
                'designations',
                'hiringLeads',
                'employmentTypes',
                'statuses'
            ));
        } catch (\Exception $e) {
            Log::error('Failed to load create form: ' . $e->getMessage());
            return back()->with('error', 'Failed to load create form.');
        }
    }

    /**
     * Store a newly created job opening.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'department_id' => 'nullable|exists:departments,id',
            'designation_id' => 'nullable|exists:designations,id',
            'employment_type' => 'required|in:full_time,part_time,contract,internship,temporary',
            'experience_required' => 'nullable|string|max:100',
            'qualification_required' => 'nullable|string',
            'skills_required' => 'nullable|string',
            'description' => 'required|string',
            'responsibilities' => 'nullable|string',
            'requirements' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'salary_range_min' => 'nullable|numeric|min:0',
            'salary_range_max' => 'nullable|numeric|min:0|gte:salary_range_min',
            'no_of_vacancies' => 'required|integer|min:1',
            'hiring_lead' => 'nullable|exists:users,id',
            'status' => 'required|in:draft,published,closed,on_hold'
        ], [
            'title.required' => 'Job title is required',
            'employment_type.required' => 'Employment type is required',
            'description.required' => 'Job description is required',
            'no_of_vacancies.required' => 'Number of vacancies is required',
            'no_of_vacancies.min' => 'Number of vacancies must be at least 1',
            'status.required' => 'Status is required',
            'salary_range_max.gte' => 'Maximum salary must be greater than or equal to minimum salary'
        ]);

        try {
            DB::beginTransaction();

            $validatedData = $validator->validated();
            $validatedData['created_by'] = auth()->id();

            $jobOpening = JobOpening::create($validatedData);

            DB::commit();

            return redirect()
                ->route('job-openings.index')
                ->with('success', 'Job opening created successfully! Job Code: ' . $jobOpening->job_code);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create job opening: ' . $e->getMessage());

            return back()
                ->withInput()
                ->with('error', 'Failed to create job opening. Please try again.');
        }
    }

    /**
     * Display the specified job opening.
     */
    public function show($id)
    {
        try {
            $jobOpening = JobOpening::with([
                'department',
                'designation',
                'hiringLead',
                'createdBy',
                'applications' => function ($query) {
                    $query->latest()->limit(10);
                },
                'applications.candidate'
            ])->findOrFail($id);

            // Get application statistics
            $applicationStats = [
                'total_applications' => $jobOpening->applications()->count(),
                'application_received' => $jobOpening->applications()
                    ->where('current_stage', 'application_received')->count(),
                'shortlisted' => $jobOpening->applications()
                    ->where('current_stage', 'cv_shortlisted')->count(),
                'interview_scheduled' => $jobOpening->applications()
                    ->where('current_stage', 'interview_scheduled')->count(),
                'offered' => $jobOpening->applications()
                    ->whereIn('current_stage', ['offer_released', 'offer_accepted'])->count(),
                'onboarded' => $jobOpening->applications()
                    ->where('current_stage', 'onboarded')->count(),
                'rejected' => $jobOpening->applications()
                    ->whereIn('current_stage', ['cv_rejected', 'rejected', 'offer_rejected'])->count()
            ];

            $fillRate = $jobOpening->no_of_vacancies > 0
                ? round(($applicationStats['onboarded'] / $jobOpening->no_of_vacancies) * 100, 2)
                : 0;

            return view('client.recruitment.job-openings.show', compact('jobOpening', 'applicationStats', 'fillRate'));
        } catch (\Exception $e) {
            Log::error('Failed to fetch job opening: ' . $e->getMessage());
            return redirect()
                ->route('job-openings.index')
                ->with('error', 'Job opening not found.');
        }
    }

    /**
     * Show the form for editing the specified job opening.
     */
    public function edit($id)
    {
        try {
            $jobOpening = JobOpening::findOrFail($id);
            // Check if job opening has applications and is not in draft
            if ($jobOpening->applications()->exists() && $jobOpening->status !== JobOpening::STATUS_DRAFT) {
                return redirect()
                    ->route('job-openings.show', $id)
                    ->with('warning', 'This job opening has applications. Some fields cannot be edited.');
            }

            $departments = Department::where('status', 1)->orderBy('name')->get();
            $designations = Designation::where('status', 1)->orderBy('name')->get();
            $hiringLeads = User::whereIn('role', ['hr', 'manager', 'admin', 'super_admin'])
                ->orderBy('name')
                ->get();

            $employmentTypes = JobOpening::$employmentTypes;
            $statuses = JobOpening::$statuses;

            return view('client.recruitment.job-openings.update', compact(
                'jobOpening',
                'departments',
                'designations',
                'hiringLeads',
                'employmentTypes',
                'statuses'
            ));
        } catch (\Exception $e) {
            return redirect()
                ->route('job-openings.index')
                ->with('error', 'Job opening not found.');
        }
    }

    /**
     * Update the specified job opening.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'department_id' => 'nullable|exists:departments,id',
            'designation_id' => 'nullable|exists:designations,id',
            'employment_type' => 'required|in:full_time,part_time,contract,internship,temporary',
            'experience_required' => 'nullable|string|max:100',
            'qualification_required' => 'nullable|string',
            'skills_required' => 'nullable|string',
            'description' => 'required|string',
            'responsibilities' => 'nullable|string',
            'requirements' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'salary_range_min' => 'nullable|numeric|min:0',
            'salary_range_max' => 'nullable|numeric|min:0|gte:salary_range_min',
            'no_of_vacancies' => 'required|integer|min:1',
            'hiring_lead' => 'nullable|exists:users,id',
            'status' => 'required|in:draft,published,closed,on_hold'
        ], [
            'title.required' => 'Job title is required',
            'employment_type.required' => 'Employment type is required',
            'description.required' => 'Job description is required',
            'no_of_vacancies.required' => 'Number of vacancies is required',
            'no_of_vacancies.min' => 'Number of vacancies must be at least 1',
            'status.required' => 'Status is required',
            'salary_range_max.gte' => 'Maximum salary must be greater than or equal to minimum salary'
        ]);

        try {
            DB::beginTransaction();

            $jobOpening = JobOpening::findOrFail($id);

            // Check if job opening has applications
            $hasApplications = $jobOpening->applications()->exists();
            $newStatus = $request->input('status');

            if ($hasApplications && $newStatus === JobOpening::STATUS_DRAFT) {
                return back()
                    ->withInput()
                    ->with('error', 'Cannot change status to draft when applications exist.');
            }

            $validatedData = $validator->validated();

            // If status is being set to published and it was draft, set published_date
            if (
                $newStatus === JobOpening::STATUS_PUBLISHED &&
                $jobOpening->status !== JobOpening::STATUS_PUBLISHED
            ) {
                $validatedData['published_date'] = now();
            }

            // If status is being set to closed, set closed_date
            if (
                $newStatus === JobOpening::STATUS_CLOSED &&
                $jobOpening->status !== JobOpening::STATUS_CLOSED
            ) {
                $validatedData['closed_date'] = now();
            }

            $jobOpening->update($validatedData);

            DB::commit();

            return redirect()
                ->route('job-openings.index')
                ->with('success', 'Job opening updated successfully!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->with('error', 'Failed to update job opening. Please try again.');
        }
    }


    /**
     * Remove the specified job opening.
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $jobOpening = JobOpening::findOrFail($id);

            // Check if there are any applications
            if ($jobOpening->applications()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete job opening with existing applications. Archive it instead.'
                ], 422);
            }

            $jobOpening->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Job opening deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Job opening not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete job opening: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete job opening',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Publish a job opening.
     */
    public function publish($id)
    {
        try {
            DB::beginTransaction();

            $jobOpening = JobOpening::findOrFail($id);

            $jobOpening->update([
                'status' => JobOpening::STATUS_PUBLISHED,
                'published_date' => now()
            ]);

            DB::commit();

            return redirect()
                ->route('job-openings.index')
                ->with('success', 'Job opening published successfully!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->with('error', 'Failed to publish job opening. Please try again.');
        }
    }

    /**
     * Close a job opening.
     */
    public function close($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'reason' => 'nullable|string|max:500'
            ]);

            $jobOpening = JobOpening::findOrFail($id);

            if ($jobOpening->status === JobOpening::STATUS_CLOSED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job opening is already closed'
                ], 422);
            }

            $jobOpening->update([
                'status' => JobOpening::STATUS_CLOSED,
                'closed_date' => now()
            ]);

            // Optional: Add closing note in logs or metadata
            if ($request->reason) {
                Log::info("Job opening {$jobOpening->job_code} closed. Reason: " . $request->reason);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Job opening closed successfully',
                'data' => $jobOpening
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Job opening not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to close job opening: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to close job opening',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get single job opening for public view.
     */
    public function getPublicJobDetails($id)
    {
        try {
            $job = JobOpening::with(['department', 'designation'])
                ->where('status', JobOpening::STATUS_PUBLISHED)
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $job
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to fetch public job details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch job details',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Duplicate a job opening.
     */
    public function duplicate($id)
    {
        try {
            DB::beginTransaction();

            $originalJob = JobOpening::findOrFail($id);

            // Create duplicate
            $newJob = $originalJob->replicate();
            $newJob->status = JobOpening::STATUS_DRAFT;
            $newJob->title = $originalJob->title . ' (Copy)';
            $newJob->published_date = null;
            $newJob->closed_date = null;
            $newJob->created_by = auth()->id();
            $newJob->save();

            DB::commit();

            $newJob->load(['department', 'designation', 'hiringLead', 'createdBy']);

            return response()->json([
                'success' => true,
                'message' => 'Job opening duplicated successfully',
                'data' => $newJob
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Job opening not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to duplicate job opening: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate job opening',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function getApplications($id, Request $request)
    {
        try {
            $jobOpening = JobOpening::findOrFail($id);

            $query = $jobOpening->applications()->with(['candidate', 'latestInterview']);

            if ($request->filled('stage')) {
                $query->where('current_stage', $request->stage);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('candidate', function ($q) use ($search) {
                    $q->where('first_name', 'LIKE', "%{$search}%")
                        ->orWhere('last_name', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%")
                        ->orWhere('phone', 'LIKE', "%{$search}%");
                });
            }

            $applications = $query->orderBy('created_at', 'desc')->paginate(15);

            // Stats — each bucket is mutually exclusive
            $applicationStats = [
                'total_applications'    => $jobOpening->applications()->count(),
                'application_received'  => $jobOpening->applications()->where('current_stage', 'application_received')->count(),
                'shortlisted'           => $jobOpening->applications()->where('current_stage', 'cv_shortlisted')->count(),
                'interview_scheduled'   => $jobOpening->applications()->where('current_stage', 'interview_scheduled')->count(),
                'interview_completed'   => $jobOpening->applications()->where('current_stage', 'interview_completed')->count(),
                'offered'               => $jobOpening->applications()->whereIn('current_stage', ['offer_released', 'offer_accepted'])->count(),
                'onboarded'             => $jobOpening->applications()->where('current_stage', 'onboarded')->count(),
                'rejected'              => $jobOpening->applications()
                    ->whereIn('current_stage', ['cv_rejected', 'rejected', 'offer_rejected'])
                    ->count(),
            ];

            return view('client.recruitment.application.index', compact(
                'jobOpening',
                'applications',
                'applicationStats'
            ));
        } catch (\Exception $e) {
            Log::error('Failed to fetch applications: ' . $e->getMessage());
            return redirect()->route('job-openings.show', $id)->with('error', 'Failed to load applications.');
        }
    }

    // ─────────────────────────────────────────────
    //  SHORTLIST WITH EMAIL
    // ─────────────────────────────────────────────

    public function shortlist(Request $request, $id)
    {
        try {
            $request->validate(['remarks' => 'nullable|string|max:500']);

            $application = JobApplication::with(['candidate', 'jobOpening'])->findOrFail($id);

            if ($application->current_stage !== 'application_received') {
                return response()->json([
                    'success' => false,
                    'message' => 'Application cannot be shortlisted at this stage.',
                ], 422);
            }

            DB::beginTransaction();
            $application->moveToStage('cv_shortlisted', $request->remarks);
            $application->candidate?->update(['status' => Candidate::STATUS_SCREENING]);
            DB::commit();

            // ✅ Send email to candidate
            try {
                Mail::to($application->candidate->email)->send(new ApplicationShortlistedMail(
                    $application->candidate,
                    $application->jobOpening,
                    $request->remarks
                ));
            } catch (\Exception $e) {
                Log::error('Failed to send shortlist email: ' . $e->getMessage());
            }

            return response()->json(['success' => true, 'message' => 'Application shortlisted successfully!']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Shortlist failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to shortlist application.'], 500);
        }
    }

    // ─────────────────────────────────────────────
    //  REJECT WITH EMAIL
    // ─────────────────────────────────────────────

    public function reject(Request $request, $id)
    {
    
        try {
            $request->validate(['remarks' => 'required|string|min:5|max:500']);

            $application = JobApplication::with(['candidate', 'jobOpening'])->findOrFail($id);

            $rejectableStages = [
                'application_received',
                'cv_shortlisted',
                'interview_scheduled',
                'interview_completed',
            ];

            if (!in_array($application->current_stage, $rejectableStages)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application cannot be rejected at this stage.',
                ], 422);
            }

            DB::beginTransaction();
            $newStage = $application->current_stage === 'application_received' ? 'cv_rejected' : 'rejected';
            $application->moveToStage($newStage, $request->remarks);
            $application->candidate?->update(['status' => Candidate::STATUS_REJECTED]);
            DB::commit();

            // ✅ Send rejection email to candidate
            try {
                Mail::to($application->candidate->email)->send(new ApplicationRejectedMail(
                    $application->candidate,
                    $application->jobOpening,
                    $request->remarks
                ));
            } catch (\Exception $e) {
                Log::error('Failed to send rejection email: ' . $e->getMessage());
            }

            return response()->json(['success' => true, 'message' => 'Application rejected successfully!']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reject failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to reject application.'], 500);
        }
    }

    // ─────────────────────────────────────────────
    //  SCHEDULE INTERVIEW WITH EMAIL
    // ─────────────────────────────────────────────

    public function scheduleInterview(Request $request, $id)
    {
        try {
            $request->validate([
                'interview_round'       => 'required|integer|min:1',
                'round_name'            => 'required|string|max:255',
                'interview_type'        => 'required|in:online,offline,telephonic,video',
                'interviewer_id'        => 'required|exists:users,id',
                'co_interviewer_ids'    => 'nullable|array',
                'co_interviewer_ids.*'  => 'exists:users,id',
                'scheduled_date'        => 'required|date|after_or_equal:today',
                'scheduled_time'        => 'required|date_format:H:i',
                'duration_minutes'      => 'required|integer|min:15|max:240',
                'meeting_link'          => 'nullable|string|max:500',
                'location'              => 'nullable|string|max:255',
                'instructions'          => 'nullable|string',
                'recruitment_stage_id'  => 'nullable|exists:recruitment_stages,id',
            ]);

            $application = JobApplication::with(['candidate', 'jobOpening'])->findOrFail($id);

            $schedulableStages = ['cv_shortlisted', 'interview_completed'];

            if (!in_array($application->current_stage, $schedulableStages)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Interview cannot be scheduled at this stage (' . $application->current_stage . ').',
                ], 422);
            }

            DB::beginTransaction();

            $interview = Interview::create([
                'tenant_id'             => $application->tenant_id,
                'interview_code'        => $this->generateInterviewCode(),
                'job_application_id'    => $application->id,
                'candidate_id'          => $application->candidate_id,
                'recruitment_stage_id'  => $request->recruitment_stage_id,
                'interview_round'       => $request->interview_round,
                'round_name'            => $request->round_name,
                'interview_type'        => $request->interview_type,
                'interviewer_id'        => $request->interviewer_id,
                'co_interviewer_ids'    => json_encode($request->co_interviewer_ids ?? []),
                'scheduled_date'        => $request->scheduled_date,
                'scheduled_time'        => $request->scheduled_time,
                'duration_minutes'      => $request->duration_minutes,
                'meeting_link'          => $request->meeting_link,
                'location'              => $request->location,
                'instructions'          => $request->instructions,
                'status'                => 'scheduled',
                'created_by'            => auth()->id(),
            ]);

            $application->moveToStage(
                'interview_scheduled',
                "Interview scheduled: Round {$request->interview_round} – {$request->round_name}"
            );

            $application->candidate?->update(['status' => Candidate::STATUS_INTERVIEWING]);

            DB::commit();

            // ✅ Send interview invitation email to candidate
            try {
                Mail::to($application->candidate->email)->send(new InterviewScheduledMail(
                    $application->candidate,
                    $application->jobOpening,
                    $interview
                ));
            } catch (\Exception $e) {
                Log::error('Failed to send interview schedule email: ' . $e->getMessage());
            }

            // ✅ Also send email to interviewer
            try {
                $interviewer = User::find($request->interviewer_id);
                // if ($interviewer) {
                //     Mail::to($interviewer->email)->send(new \App\Mail\InterviewerInvitationMail(
                //         $application->candidate,
                //         $application->jobOpening,
                //         $interview
                //     ));
                // }
            } catch (\Exception $e) {
                Log::error('Failed to send interviewer invitation email: ' . $e->getMessage());
            }

            return response()->json([
                'success'   => true,
                'message'   => 'Interview scheduled successfully!',
                'data'      => [
                    'interview_id'   => $interview->id,
                    'interview_code' => $interview->interview_code,
                    'scheduled_date' => $interview->scheduled_date->format('d M Y'),
                    'scheduled_time' => $interview->scheduled_time->format('h:i A'),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Schedule interview failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to schedule interview: ' . $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────
    //  SUBMIT FEEDBACK & DECISION WITH EMAIL
    // ─────────────────────────────────────────────

    public function submitFeedback(Request $request, $id)
    {
        try {
            $request->validate([
                'technical_skill'       => 'nullable|integer|min:1|max:5',
                'communication_skill'   => 'nullable|integer|min:1|max:5',
                'problem_solving'       => 'nullable|integer|min:1|max:5',
                'cultural_fit'          => 'nullable|integer|min:1|max:5',
                'experience_relevance'  => 'nullable|integer|min:1|max:5',
                'overall_rating'        => 'nullable|numeric|min:1|max:5',
                'strengths'             => 'nullable|string',
                'weaknesses'            => 'nullable|string',
                'comments'              => 'required|string',
                'recommendation'        => 'required|in:strong_hire,hire,maybe,no_hire',
                'decision'              => 'required|in:selected,rejected,next_round,on_hold',
                'next_round_suggested'  => 'nullable|string|max:255',
            ]);

            $interview = Interview::with(['application.candidate', 'application.jobOpening'])->findOrFail($id);

            if ($interview->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Feedback has already been submitted for this interview.',
                ], 422);
            }

            $application = $interview->application;

            DB::beginTransaction();

            // Mark interview as completed
            $interview->update([
                'status'       => 'completed',
                'outcome'      => $request->decision,
                'feedback'     => $request->comments,
                'rating'       => $request->overall_rating,
                'completed_at' => now(),
            ]);

            // Detailed feedback record
            $interview->feedbacks()->create([
                'tenant_id'            => $interview->tenant_id,
                'interviewer_id'       => auth()->id(),
                'technical_skill'      => $request->technical_skill,
                'communication_skill'  => $request->communication_skill,
                'problem_solving'      => $request->problem_solving,
                'cultural_fit'         => $request->cultural_fit,
                'experience_relevance' => $request->experience_relevance,
                'overall_rating'       => $request->overall_rating,
                'strengths'            => $request->strengths,
                'weaknesses'           => $request->weaknesses,
                'comments'             => $request->comments,
                'recommendation'       => $request->recommendation,
                'next_round_suggested' => $request->next_round_suggested,
            ]);

            // ── Process decision and send emails ──────────────────────────

            switch ($request->decision) {

                case 'selected':
                    $application->moveToStage(
                        'interview_completed',
                        "Selected in {$interview->round_name}. Ready for offer."
                    );
                    $application->candidate?->update(['status' => Candidate::STATUS_OFFERED]);
                    
                    // ✅ Send selection email
                    try {
                        Mail::to($application->candidate->email)->send(new InterviewFeedbackMail(
                            $application->candidate,
                            $application->jobOpening,
                            $interview,
                            'selected',
                            $request->comments
                        ));
                    } catch (\Exception $e) {
                        Log::error('Failed to send selection email: ' . $e->getMessage());
                    }
                    break;

                case 'rejected':
                    $application->moveToStage(
                        'rejected',
                        "Rejected in {$interview->round_name}: {$request->comments}"
                    );
                    $application->candidate?->update(['status' => Candidate::STATUS_REJECTED]);
                    
                    // ✅ Send rejection email
                    try {
                        Mail::to($application->candidate->email)->send(new InterviewFeedbackMail(
                            $application->candidate,
                            $application->jobOpening,
                            $interview,
                            'rejected',
                            $request->comments
                        ));
                    } catch (\Exception $e) {
                        Log::error('Failed to send rejection email: ' . $e->getMessage());
                    }
                    break;

                case 'next_round':
                    $fromStage = $application->current_stage;
                    $application->update(['current_stage' => 'cv_shortlisted']);
                    $application->candidate?->update(['status' => Candidate::STATUS_INTERVIEWING]);

                    $application->logs()->create([
                        'tenant_id'          => $application->tenant_id,
                        'job_application_id' => $application->id,
                        'from_stage'         => $fromStage,
                        'to_stage'           => 'cv_shortlisted',
                        'action'             => 'next_round',
                        'action_by'          => auth()->id(),
                        'remarks'            => "Advancing to next round after {$interview->round_name}"
                            . ($request->next_round_suggested ? ". Next: {$request->next_round_suggested}" : ''),
                    ]);
                    
                    // ✅ Send next round email
                    try {
                        Mail::to($application->candidate->email)->send(new InterviewFeedbackMail(
                            $application->candidate,
                            $application->jobOpening,
                            $interview,
                            'next_round',
                            $request->comments
                        ));
                    } catch (\Exception $e) {
                        Log::error('Failed to send next round email: ' . $e->getMessage());
                    }
                    break;

                case 'on_hold':
                    $application->logs()->create([
                        'tenant_id'          => $application->tenant_id,
                        'job_application_id' => $application->id,
                        'from_stage'         => $application->current_stage,
                        'to_stage'           => $application->current_stage,
                        'action'             => 'on_hold',
                        'action_by'          => auth()->id(),
                        'remarks'            => "On hold after {$interview->round_name}: {$request->comments}",
                    ]);
                    $application->update(['current_stage' => 'cv_shortlisted']);
                    break;
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Feedback submitted successfully!']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Submit feedback failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to submit feedback.'], 500);
        }
    }

    // ─────────────────────────────────────────────
    //  RELEASE OFFER WITH EMAIL
    // ─────────────────────────────────────────────

    public function releaseOffer(Request $request, $id)
    {
        try {
            $request->validate([
                'joining_date'      => 'required|date|after_or_equal:today',
                'designation_id'    => 'required|exists:designations,id',
                'department_id'     => 'required|exists:departments,id',
                'reporting_head'    => 'nullable|exists:users,id',
                'employment_type'   => 'required|in:full_time,part_time,contract,internship',
                'offered_ctc'       => 'required|numeric|min:0',
                'basic_salary'      => 'nullable|numeric|min:0',
                'hra'               => 'nullable|numeric|min:0',
                'other_allowances'  => 'nullable|numeric|min:0',
                'variable_pay'      => 'nullable|numeric|min:0',
            ]);

            $application = JobApplication::with(['candidate', 'jobOpening'])->findOrFail($id);

            if ($application->current_stage !== 'interview_completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Offer can only be released after interview is completed.',
                ], 422);
            }

            DB::beginTransaction();

            $offer = JobOffer::create([
                'tenant_id'         => $application->tenant_id,
                'offer_code'        => $this->generateOfferCode(),
                'job_application_id' => $application->id,
                'candidate_id'      => $application->candidate_id,
                'offer_date'        => now(),
                'joining_date'      => $request->joining_date,
                'designation_id'    => $request->designation_id,
                'department_id'     => $request->department_id,
                'reporting_head'    => $request->reporting_head,
                'employment_type'   => $request->employment_type,
                'offer_status'      => 'sent',
                'offered_ctc'       => $request->offered_ctc,
                'basic_salary'      => $request->basic_salary,
                'hra'               => $request->hra,
                'other_allowances'  => $request->other_allowances,
                'variable_pay'      => $request->variable_pay,
                'sent_at'           => now(),
                'created_by'        => auth()->id(),
            ]);

            $application->moveToStage(
                'offer_released',
                'Offer letter released. CTC: ₹' . number_format($request->offered_ctc)
            );
            $application->candidate?->update(['status' => Candidate::STATUS_OFFERED]);

            DB::commit();

            // ✅ Send offer letter email to candidate
            try {
                Mail::to($application->candidate->email)->send(new OfferReleasedMail(
                    $application->candidate,
                    $application->jobOpening,
                    $offer,
                    $request->joining_date
                ));
            } catch (\Exception $e) {
                Log::error('Failed to send offer email: ' . $e->getMessage());
            }

            return response()->json([
                'success'   => true,
                'message'   => 'Offer released successfully!',
                'data'      => [
                    'offer_id'    => $offer->id,
                    'offer_code'  => $offer->offer_code,
                    'offered_ctc' => number_format($offer->offered_ctc),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Release offer failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to release offer.'.$e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────
    //  OFFER ACCEPTED WITH EMAIL
    // ─────────────────────────────────────────────

    public function offerAccepted(Request $request, $id)
    {
        try {
            $application = JobApplication::with(['candidate', 'jobOpening'])->findOrFail($id);
            
            if ($application->current_stage !== 'offer_released') {
                return response()->json(['success' => false, 'message' => 'Invalid stage.'], 422);
            }
            
            DB::beginTransaction();
            $application->moveToStage('offer_accepted', 'Candidate accepted the offer.');
            $application->candidate?->update(['status' => Candidate::STATUS_HIRED]);
            
            $offer = $application->offer()->latest()->first();
            if ($offer) {
                $offer->update(['offer_status' => 'accepted', 'accepted_at' => now()]);
                $joiningDate = $offer->joining_date;
            } else {
                $joiningDate = now()->addDays(15);
            }
            
            DB::commit();

            // ✅ Send offer accepted confirmation email to candidate
            try {
                Mail::to($application->candidate->email)->send(new OfferAcceptedMail(
                    $application->candidate,
                    $application->jobOpening,
                    $joiningDate
                ));
            } catch (\Exception $e) {
                Log::error('Failed to send offer acceptance email: ' . $e->getMessage());
            }

            // ✅ Notify HR team
            try {
                $hrUsers = User::whereIn('role', ['hr', 'admin'])->get();
                // foreach ($hrUsers as $hr) {
                //     Mail::to($hr->email)->send(new \App\Mail\OfferAcceptedHRMail(
                //         $application->candidate,
                //         $application->jobOpening
                //     ));
                // }
            } catch (\Exception $e) {
                Log::error('Failed to send HR notification email: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true, 
                'message' => 'Offer marked as accepted! Onboarding process will begin shortly.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Offer accepted failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to process offer acceptance.'.$e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────
    //  OFFER REJECTED WITH EMAIL
    // ─────────────────────────────────────────────

    public function offerRejected(Request $request, $id)
    {
        try {
            $request->validate(['remarks' => 'nullable|string|max:500']);
            
            $application = JobApplication::with(['candidate', 'jobOpening'])->findOrFail($id);
            
            if ($application->current_stage !== 'offer_released') {
                return response()->json(['success' => false, 'message' => 'Invalid stage.'], 422);
            }
            
            DB::beginTransaction();
            $application->moveToStage('offer_rejected', $request->remarks ?? 'Candidate rejected the offer.');
            $application->candidate?->update(['status' => Candidate::STATUS_REJECTED]);
            
            $offer = $application->offers()->latest()->first();
            if ($offer) {
                $offer->update(['offer_status' => 'rejected', 'rejected_at' => now()]);
            }
            
            DB::commit();

            // ✅ Send offer rejected notification email to HR
            try {
                $hrUsers = User::whereIn('role', ['hr', 'admin'])->get();
                foreach ($hrUsers as $hr) {
                    Mail::to($hr->email)->send(new OfferRejectedMail(
                        $application->candidate,
                        $application->jobOpening,
                        $request->remarks
                    ));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send offer rejection email: ' . $e->getMessage());
            }

            return response()->json(['success' => true, 'message' => 'Offer marked as rejected.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Offer rejected failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to process offer rejection.'], 500);
        }
    }

    // ─────────────────────────────────────────────
    //  INTERVIEW DETAILS PAGE
    // ─────────────────────────────────────────────

    public function interviewDetails($applicationId)
    {
        try {
            $application = JobApplication::with([
                'candidate',
                'jobOpening',
                'interviews' => function ($q) {
                    $q->with([
                        'interviewer',
                        'feedbacks.interviewer',
                    ])
                        ->orderBy('interview_round', 'asc')
                        ->orderBy('scheduled_date', 'asc');
                },
                'logs' => function ($q) {
                    $q->with('actionBy')->orderBy('created_at', 'asc');
                },
            ])->findOrFail($applicationId);

            $candidate  = $application->candidate;
            $jobOpening = $application->jobOpening;
            $interviews = $application->interviews;

            // Decode co_interviewer_ids JSON for display
            $interviews->each(function ($interview) {
                $interview->co_interviewer_ids_decoded = json_decode($interview->co_interviewer_ids ?? '[]', true);
                if (!empty($interview->co_interviewer_ids_decoded)) {
                    $interview->coInterviewers = User::whereIn('id', $interview->co_interviewer_ids_decoded)
                        ->select('id', 'name')
                        ->get();
                } else {
                    $interview->coInterviewers = collect();
                }
            });

            return view('client.recruitment.application.interview-details', compact(
                'application',
                'candidate',
                'jobOpening',
                'interviews'
            ));
        } catch (\Exception $e) {
            Log::error('Failed to load interview details: ' . $e->getMessage());
            return redirect()
                ->route('job-openings.index')
                ->with('error', 'Failed to load interview details.');
        }
    }

    // ─────────────────────────────────────────────
    //  HELPERS
    // ─────────────────────────────────────────────

   private function generateInterviewCode(): string
{
    $lastId = Interview::max('id') ?? 0;

    return 'INT' . str_pad($lastId + 1, 6, '0', STR_PAD_LEFT);
}

    private function generateOfferCode(): string
    {
        $prefix = 'OFF';
        $year   = date('Y');
        $lastId = JobOffer::max('id') ?? 0;
        return $prefix . $year . str_pad($lastId + 1, 5, '0', STR_PAD_LEFT);
    }
}