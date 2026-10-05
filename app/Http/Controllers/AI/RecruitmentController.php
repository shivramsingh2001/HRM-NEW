<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\AI\Concerns\AiScope;
use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/ai/recruitment — job openings with their application pipeline, interviews in a date
 * range (default today → +14 days) and job offers.
 * company scope (recruitment:view) = everything, incl. salary ranges and offered CTC;
 * anyone else = openings where they are the hiring lead and interviews they take
 * (interviewer or co-interviewer); no offers. Candidate contact details are never returned.
 */
class RecruitmentController extends Controller
{
    use AiScope;

    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();
            $tenantId = (int) $authUser->tenant_id;
            $company = $this->scopeOf($authUser, 'recruitment') === 'company';
            $me = (int) $authUser->id;

            $start = $this->ymd($request->start_date) ?? Carbon::today()->toDateString();
            $end = $this->ymd($request->end_date) ?? Carbon::parse($start)->addDays(14)->toDateString();
            if ($end < $start) {
                [$start, $end] = [$end, $start];
            }

            // Openings
            $openings = DB::table('job_openings as j')
                ->leftJoin('departments as d', 'd.id', '=', 'j.department_id')
                ->leftJoin('designations as g', 'g.id', '=', 'j.designation_id')
                ->where('j.tenant_id', $tenantId)->whereNull('j.deleted_at')
                ->when(! $company, fn ($q) => $q->where('j.hiring_lead', $me))
                ->when($request->filled('status'), fn ($q) => $q->where('j.status', $request->status))
                ->when($request->filled('job_opening_id'), fn ($q) => $q->where('j.id', $request->job_opening_id))
                ->orderByDesc('j.created_at')
                ->get(['j.id', 'j.job_code', 'j.title', 'd.name as department', 'g.name as designation', 'j.employment_type',
                    'j.experience_required', 'j.location', 'j.no_of_vacancies', 'j.status', 'j.published_date', 'j.closed_date',
                    'j.hiring_lead', 'j.salary_range_min', 'j.salary_range_max', 'j.skills_required']);

            $applications = DB::table('job_applications')->where('tenant_id', $tenantId)
                ->whereIn('job_opening_id', $openings->pluck('id')->all() ?: [0])
                ->get(['job_opening_id', 'current_stage', 'status', 'source'])->groupBy('job_opening_id');
            $leads = $this->people($tenantId, $openings->pluck('hiring_lead'));
            $stageLabel = fn ($s) => JobApplication::$stages[$s] ?? ucfirst(str_replace('_', ' ', (string) $s));

            $openingData = $openings->map(function ($j) use ($applications, $leads, $company, $stageLabel) {
                $apps = $applications->get($j->id, collect());
                $hired = $apps->whereIn('current_stage', ['hired', 'onboarding', 'onboarded'])->count();

                return [
                    'id' => $j->id,
                    'job_code' => $j->job_code,
                    'title' => $j->title,
                    'department' => $j->department,
                    'designation' => $j->designation,
                    'employment_type' => $j->employment_type,
                    'experience_required' => $j->experience_required,
                    'skills_required' => $j->skills_required,
                    'location' => $j->location,
                    'status' => $j->status,
                    'published_date' => $j->published_date,
                    'closed_date' => $j->closed_date,
                    'hiring_lead' => $leads[$j->hiring_lead] ?? null,
                    'vacancies' => (int) $j->no_of_vacancies,
                    'positions_filled' => $hired,
                    'applications' => [
                        'total' => $apps->count(),
                        'active' => $apps->where('status', 'active')->count(),
                        'by_stage' => $apps->countBy(fn ($a) => $stageLabel($a->current_stage)),
                        'by_source' => $apps->countBy('source'),
                    ],
                ] + ($company ? ['salary_range' => ['min' => $j->salary_range_min !== null ? (float) $j->salary_range_min : null,
                    'max' => $j->salary_range_max !== null ? (float) $j->salary_range_max : null]] : []);
            })->values();

            // Interviews in range
            $interviews = DB::table('interviews as i')
                ->leftJoin('candidates as c', 'c.id', '=', 'i.candidate_id')
                ->leftJoin('job_applications as a', 'a.id', '=', 'i.job_application_id')
                ->leftJoin('job_openings as j', 'j.id', '=', 'a.job_opening_id')
                ->where('i.tenant_id', $tenantId)
                ->whereBetween('i.scheduled_date', [$start, $end])
                ->when(! $company, fn ($q) => $q->where(fn ($w) => $w->where('i.interviewer_id', $me)
                    ->orWhereRaw('JSON_CONTAINS(i.co_interviewer_ids, ?)', [json_encode($me)])
                    ->orWhereRaw('JSON_CONTAINS(i.co_interviewer_ids, ?)', [json_encode((string) $me)])))
                ->when($request->filled('job_opening_id'), fn ($q) => $q->where('a.job_opening_id', $request->job_opening_id))
                ->orderBy('i.scheduled_date')->orderBy('i.scheduled_time')
                ->get(['i.id', 'i.interview_code', 'i.scheduled_date', 'i.scheduled_time', 'i.duration_minutes', 'i.interview_round',
                    'i.round_name', 'i.interview_type', 'i.meeting_link', 'i.location', 'i.interviewer_id', 'i.co_interviewer_ids',
                    'i.status', 'i.outcome', 'i.rating', 'c.first_name', 'c.last_name', 'c.candidate_code', 'c.total_experience',
                    'c.current_company', 'j.title as job_title', 'j.job_code']);

            $coIds = $interviews->flatMap(fn ($i) => (array) json_decode($i->co_interviewer_ids ?? '[]', true));
            $interviewers = $this->people($tenantId, $interviews->pluck('interviewer_id')->merge($coIds));

            $interviewData = $interviews->map(fn ($i) => [
                'id' => $i->id,
                'interview_code' => $i->interview_code,
                'date' => $i->scheduled_date,
                'time' => $i->scheduled_time,
                'duration_minutes' => $i->duration_minutes,
                'round' => $i->round_name ?: $i->interview_round,
                'type' => $i->interview_type,
                'meeting_link' => $i->meeting_link,
                'location' => $i->location,
                'candidate' => ['name' => trim($i->first_name . ' ' . $i->last_name), 'candidate_code' => $i->candidate_code,
                    'total_experience' => $i->total_experience, 'current_company' => $i->current_company],
                'job' => ['title' => $i->job_title, 'job_code' => $i->job_code],
                'interviewer' => $interviewers[$i->interviewer_id] ?? null,
                'co_interviewers' => collect((array) json_decode($i->co_interviewer_ids ?? '[]', true))
                    ->map(fn ($id) => $interviewers[(int) $id] ?? null)->filter()->values(),
                'is_my_interview' => (int) $i->interviewer_id === $me
                    || in_array($me, array_map('intval', (array) json_decode($i->co_interviewer_ids ?? '[]', true)), true),
                'status' => $i->status,
                'outcome' => $i->outcome,
                'rating' => $i->rating,
            ])->values();

            // Offers (company scope only — contains pay)
            $offerData = collect();
            if ($company) {
                $offerData = DB::table('job_offers as o')
                    ->leftJoin('candidates as c', 'c.id', '=', 'o.candidate_id')
                    ->leftJoin('designations as g', 'g.id', '=', 'o.designation_id')
                    ->leftJoin('departments as d', 'd.id', '=', 'o.department_id')
                    ->where('o.tenant_id', $tenantId)
                    ->orderByDesc('o.offer_date')
                    ->get(['o.id', 'o.offer_code', 'o.offer_date', 'o.joining_date', 'o.offer_status', 'o.offered_ctc', 'o.acceptance_date',
                        'o.rejection_reason', 'c.first_name', 'c.last_name', 'c.candidate_code', 'g.name as designation', 'd.name as department'])
                    ->map(fn ($o) => [
                        'id' => $o->id,
                        'offer_code' => $o->offer_code,
                        'candidate' => ['name' => trim($o->first_name . ' ' . $o->last_name), 'candidate_code' => $o->candidate_code],
                        'designation' => $o->designation,
                        'department' => $o->department,
                        'offer_date' => $o->offer_date,
                        'joining_date' => $o->joining_date,
                        'status' => $o->offer_status,
                        'offered_ctc' => $o->offered_ctc !== null ? (float) $o->offered_ctc : null,
                        'acceptance_date' => $o->acceptance_date,
                        'rejection_reason' => $o->rejection_reason,
                    ])->values();
            }

            return response()->json([
                'success' => true,
                'message' => 'Recruitment data fetched successfully',
                'data' => [
                    'openings' => $openingData,
                    'interviews' => $interviewData,
                    'offers' => $company ? $offerData : null,
                ],
                'summary' => [
                    'openings' => $openingData->count(),
                    'openings_by_status' => $openingData->countBy('status'),
                    'open_vacancies' => $openingData->where('status', 'published')
                        ->sum(fn ($o) => max(0, $o['vacancies'] - $o['positions_filled'])),
                    'total_applications' => $openingData->sum(fn ($o) => $o['applications']['total']),
                    'interviews_in_range' => $interviewData->count(),
                    'interviews_today' => $interviewData->where('date', Carbon::today()->toDateString())->where('status', 'scheduled')->count(),
                    'my_interviews' => $interviewData->where('is_my_interview', true)->count(),
                    'offers_by_status' => $company ? $offerData->countBy('status') : null,
                    'interview_date_range' => ['start' => $start, 'end' => $end],
                ],
                'scope' => $company ? 'company' : 'own',
            ], 200);
        } catch (\Throwable $e) {
            return $this->failed('recruitment', $e);
        }
    }
}
