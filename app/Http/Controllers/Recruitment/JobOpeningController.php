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
use App\Services\Recruitment\RecruitmentPipelineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class JobOpeningController extends Controller
{
    public function __construct(protected RecruitmentPipelineService $pipeline)
    {
    }

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

            $departments = Department::where('status', 1)->orderBy('name')->get();
            $designations = Designation::where('status', 1)->orderBy('name')->get();
            $hiringLeads = User::whereIn('role', ['hr', 'manager', 'admin', 'super_admin'])
                ->orderBy('name')
                ->get();
            $employmentTypes = JobOpening::$employmentTypes;
            $statuses = JobOpening::$statuses;

            return view('client.recruitment.job-openings.index', compact(
                'jobOpenings',
                'stats',
                'departments',
                'designations',
                'hiringLeads',
                'employmentTypes',
                'statuses'
            ));
        } catch (\Exception $e) {
            Log::error('Failed to fetch job openings: ' . $e->getMessage());
            return back()->with('error', 'Failed to load job openings.');
        }
    }

    /**
     * JSON payload for the Add/Edit Job drawer on the index page (see
     * openEditJobDrawer() in job-openings/index.blade.php). Kept on the
     * existing `edit` route/name — no page render happens here anymore,
     * job create/edit now lives in a 480px drawer on the index view.
     */
    public function edit($id)
    {
        try {
            $jobOpening = JobOpening::findOrFail($id);
            return response()->json(['success' => true, 'data' => $jobOpening]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Job opening not found'], 404);
        }
    }

    /**
     * Store a newly created job opening.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules(), $this->messages());

        try {
            DB::beginTransaction();

            $validatedData = $validator->validate();
            $validatedData['created_by'] = auth()->id();
            $validatedData['job_code'] = $this->generateJobCode();

            $jobOpening = JobOpening::create($validatedData);

            DB::commit();

            return redirect()
                ->route('job-openings.index')
                ->with('success', 'Job opening created successfully! Job Code: ' . $jobOpening->job_code);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
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
                // withTrashed() + allTenants(): a candidate can be
                // soft-deleted, or (data-integrity bug — candidate_id
                // pointing at a row whose tenant_id doesn't match the job
                // opening's) hidden by the tenant global scope; either way
                // a plain belongsTo silently comes back null and crashes
                // the view on ->full_name. The HR user viewing this page
                // already has legitimate access to the application row, so
                // showing whichever candidate it's actually linked to isn't
                // a new exposure.
                'applications.candidate' => function ($query) {
                    $query->withTrashed()->allTenants();
                },
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
     * Update the specified job opening.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), $this->rules(), $this->messages());

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

            $validatedData = $validator->validate();

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
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
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
     * Close a job opening. Once closed, RecruitmentPipelineService blocks
     * further forward movement (shortlist/schedule/offer) on its
     * applications — see RecruitmentPipelineService::assertJobOpen().
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
                'closed_date' => now(),
                'close_reason' => $request->reason,
            ]);

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
            $newJob->job_code = $this->generateJobCode();
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

            // withTrashed() + allTenants(): a candidate can be soft-deleted,
            // or (data-integrity bug — candidate_id pointing at a row whose
            // tenant_id doesn't match this job opening's) hidden by the
            // tenant global scope; either way a plain belongsTo silently
            // comes back null and crashes the view on ->full_name.
            $query = $jobOpening->applications()->with([
                'candidate' => fn ($q) => $q->withTrashed()->allTenants(),
                'latestInterview',
            ]);

            if ($request->filled('stage')) {
                // Comma-separated for the stat cards that group several
                // stages together (Offered/Onboarded/Rejected) — the
                // dropdown still only ever submits a single value.
                $query->whereIn('current_stage', explode(',', $request->stage));
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
                'onboarded'             => $jobOpening->applications()->whereIn('current_stage', ['onboarding', 'onboarded', 'hired'])->count(),
                'rejected'              => $jobOpening->applications()
                    ->whereIn('current_stage', ['cv_rejected', 'rejected', 'offer_rejected'])
                    ->count(),
            ];

            // Passed server-side instead of the view running its own
            // User::whereIn(...) query inline — keeps data-fetching out of Blade.
            $interviewers = User::whereIn('role', ['hr', 'manager', 'admin', 'super_admin', 'employee'])
                ->orderBy('name')
                ->get(['id', 'name', 'role']);
            $recruitmentStages = RecruitmentStage::orderBy('stage_order')->get();
            $departments = Department::where('status', 1)->orderBy('name')->get();
            $designations = Designation::where('status', 1)->orderBy('name')->get();

            return view('client.recruitment.application.index', compact(
                'jobOpening',
                'applications',
                'applicationStats',
                'interviewers',
                'recruitmentStages',
                'departments',
                'designations'
            ));
        } catch (\Exception $e) {
            Log::error('Failed to fetch applications: ' . $e->getMessage());
            return redirect()->route('job-openings.show', $id)->with('error', 'Failed to load applications.');
        }
    }

    // ─────────────────────────────────────────────
    //  RECRUITMENT PIPELINE — all transitions delegate to
    //  RecruitmentPipelineService (see app/Services/Recruitment).
    // ─────────────────────────────────────────────

    public function shortlist(Request $request, $id)
    {
        try {
            $request->validate(['remarks' => 'nullable|string|max:500']);
            $this->pipeline->shortlist(JobApplication::findOrFail($id), $request->remarks);
            return response()->json(['success' => true, 'message' => 'Application shortlisted successfully!']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Shortlist failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to shortlist application.'], 500);
        }
    }

    public function reject(Request $request, $id)
    {
        try {
            $request->validate(['remarks' => 'required|string|min:5|max:500']);
            $this->pipeline->rejectApplication(JobApplication::findOrFail($id), $request->remarks);
            return response()->json(['success' => true, 'message' => 'Application rejected successfully!']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Reject failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to reject application.'], 500);
        }
    }

    public function scheduleInterview(Request $request, $id)
    {
        try {
            $data = $request->validate([
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

            $interview = $this->pipeline->scheduleInterview(JobApplication::findOrFail($id), $data);

            return response()->json([
                'success'   => true,
                'message'   => 'Interview scheduled successfully!',
                'data'      => [
                    'interview_id'   => $interview->id,
                    'interview_code' => $interview->interview_code,
                    'interview_round' => $interview->interview_round,
                    'scheduled_date' => $interview->scheduled_date->format('d M Y'),
                    'scheduled_time' => $interview->scheduled_time->format('h:i A'),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Schedule interview failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to schedule interview: ' . $e->getMessage()], 500);
        }
    }

    public function cancelInterview(Request $request, $id)
    {
        try {
            $request->validate(['reason' => 'nullable|string|max:500']);
            $this->pipeline->cancelInterview(Interview::findOrFail($id), $request->reason);
            return response()->json(['success' => true, 'message' => 'Interview cancelled.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Cancel interview failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to cancel interview.'], 500);
        }
    }

    public function rescheduleInterview(Request $request, $id)
    {
        try {
            $data = $request->validate([
                'scheduled_date' => 'required|date|after_or_equal:today',
                'scheduled_time' => 'required|date_format:H:i',
                'reason'         => 'nullable|string|max:500',
            ]);

            $newInterview = $this->pipeline->rescheduleInterview(
                Interview::findOrFail($id),
                $data['scheduled_date'],
                $data['scheduled_time'],
                $data['reason'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Interview rescheduled.',
                'data' => ['interview_id' => $newInterview->id],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Reschedule interview failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to reschedule interview.'], 500);
        }
    }

    public function submitFeedback(Request $request, $id)
    {
        try {
            $data = $request->validate([
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

            $interview = $this->pipeline->submitInterviewFeedback(Interview::findOrFail($id), $data);

            return response()->json([
                'success' => true,
                'message' => 'Feedback submitted successfully!',
                'data' => [
                    'decision' => $data['decision'],
                    // Lets the UI immediately offer "Schedule Round N+1" for next_round,
                    // instead of a separate nextRound() endpoint.
                    'next_round' => $data['decision'] === 'next_round' ? $interview->interview_round + 1 : null,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Submit feedback failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to submit feedback.'], 500);
        }
    }

    public function releaseOffer(Request $request, $id)
    {
        try {
            $data = $request->validate([
                'joining_date'      => 'required|date|after_or_equal:today',
                'designation_id'    => 'required|exists:designations,id',
                'department_id'     => 'required|exists:departments,id',
                'reporting_head'    => 'nullable|exists:users,id',
                'employment_type'   => 'required|in:full_time,part_time,contract,internship,temporary',
                'offered_ctc'       => 'required|numeric|min:0',
                'basic_salary'      => 'nullable|numeric|min:0',
                'hra'               => 'nullable|numeric|min:0',
                'other_allowances'  => 'nullable|numeric|min:0',
                'variable_pay'      => 'nullable|numeric|min:0',
            ]);

            $offer = $this->pipeline->releaseOffer(JobApplication::findOrFail($id), $data);

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
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Release offer failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to release offer: ' . $e->getMessage()], 500);
        }
    }

    public function offerAccepted(Request $request, $id)
    {
        try {
            $request->validate(['negotiation_details' => 'nullable|string|max:1000']);

            $application = JobApplication::with('offer')->findOrFail($id);
            if (!$application->offer) {
                return response()->json(['success' => false, 'message' => 'No offer found for this application.'], 422);
            }

            $this->pipeline->markOfferAccepted($application->offer, $request->negotiation_details);

            return response()->json([
                'success' => true,
                'message' => 'Offer marked as accepted! Onboarding has started.'
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Offer accepted failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to process offer acceptance: ' . $e->getMessage()], 500);
        }
    }

    public function offerRejected(Request $request, $id)
    {
        try {
            $request->validate(['remarks' => 'nullable|string|max:500']);

            $application = JobApplication::with('offer')->findOrFail($id);
            if (!$application->offer) {
                return response()->json(['success' => false, 'message' => 'No offer found for this application.'], 422);
            }

            $this->pipeline->markOfferRejected($application->offer, $request->remarks ?? 'Candidate rejected the offer.');

            return response()->json(['success' => true, 'message' => 'Offer marked as rejected.']);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
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
                'candidate' => fn ($q) => $q->withTrashed()->allTenants(),
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

    protected function generateJobCode(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $nextId = (JobOpening::max('id') ?? 0) + 1 + $attempt;
            $code = 'JOB-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
            if (!JobOpening::where('job_code', $code)->exists()) {
                return $code;
            }
        }
        return 'JOB-' . date('YmdHis') . rand(100, 999);
    }

    protected function rules(): array
    {
        return [
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
        ];
    }

    protected function messages(): array
    {
        return [
            'title.required' => 'Job title is required',
            'employment_type.required' => 'Employment type is required',
            'description.required' => 'Job description is required',
            'no_of_vacancies.required' => 'Number of vacancies is required',
            'no_of_vacancies.min' => 'Number of vacancies must be at least 1',
            'status.required' => 'Status is required',
            'salary_range_max.gte' => 'Maximum salary must be greater than or equal to minimum salary'
        ];
    }
}
