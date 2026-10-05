<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\AI\Concerns\AiScope;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/ai/meeting — meetings in a date range (default: today + next 30 days) with organiser,
 * participants and their responses, the caller's own role / response, and minutes status.
 * Same visibility as the mobile meetings list: company scope sees every meeting, anyone else
 * only meetings they created or were invited to.
 */
class MeetingController extends Controller
{
    use AiScope;

    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();
            $tenantId = (int) $authUser->tenant_id;

            $scope = $this->scopeOf($authUser, 'meetings');
            if ($scope === null) {
                return $this->forbidden();
            }

            $start = $this->ymd($request->start_date) ?? Carbon::today()->toDateString();
            $end = $this->ymd($request->end_date) ?? Carbon::parse($start)->addDays(30)->toDateString();
            if ($end < $start) {
                [$start, $end] = [$end, $start];
            }

            $query = DB::table('meetings as m')
                ->where('m.tenant_id', $tenantId)
                ->whereNull('m.deleted_at')
                ->whereBetween('m.meeting_date', [$start, $end]);

            if ($scope !== 'company') {
                $query->where(fn ($q) => $q->where('m.created_by', $authUser->id)
                    ->orWhereExists(fn ($s) => $s->from('meeting_participants as p')->whereColumn('p.meeting_id', 'm.id')
                        ->where('p.user_id', $authUser->id)));
            }
            if ($request->filled('status') && $request->status !== 'all') {
                $query->where('m.status', $request->status);
            }

            $meetings = $query->orderBy('m.meeting_date')->orderBy('m.start_time')->get([
                'm.id', 'm.meeting_id', 'm.title', 'm.description', 'm.agenda', 'm.meeting_type', 'm.meeting_mode',
                'm.virtual_meeting_link', 'm.location', 'm.meeting_date', 'm.start_time', 'm.end_time', 'm.duration_minutes',
                'm.created_by', 'm.status', 'm.cancellation_reason', 'm.mom_status', 'm.decisions', 'm.recurrence_pattern',
            ]);

            $participants = DB::table('meeting_participants as p')->join('users as u', 'u.id', '=', 'p.user_id')
                ->where('p.tenant_id', $tenantId)->whereIn('p.meeting_id', $meetings->pluck('id')->all() ?: [0])
                ->get(['p.meeting_id', 'p.user_id', 'u.name', 'u.employee_id', 'p.role', 'p.attendance_status', 'p.is_mom_writer'])
                ->groupBy('meeting_id');
            $people = $this->people($tenantId, $meetings->pluck('created_by'));
            $today = Carbon::today()->toDateString();

            $data = $meetings->map(function ($m) use ($participants, $people, $authUser) {
                $list = $participants->get($m->id, collect());
                $mine = $list->firstWhere('user_id', $authUser->id);

                return [
                    'id' => $m->id,
                    'meeting_code' => $m->meeting_id,
                    'title' => $m->title,
                    'description' => $m->description,
                    'agenda' => $m->agenda,
                    'date' => $m->meeting_date,
                    'start_time' => $m->start_time,
                    'end_time' => $m->end_time,
                    'duration_minutes' => $m->duration_minutes,
                    'type' => $m->meeting_type,
                    'mode' => $m->meeting_mode,
                    'location' => $m->location,
                    'meeting_link' => $m->virtual_meeting_link,
                    'status' => $m->status,
                    'cancellation_reason' => $m->cancellation_reason,
                    'recurrence' => $m->recurrence_pattern,
                    'organiser' => $people[$m->created_by] ?? null,
                    'my_role' => $mine->role ?? ($m->created_by == $authUser->id ? 'organizer' : null),
                    'my_response' => $mine->attendance_status ?? null,
                    'participants' => $list->map(fn ($p) => [
                        'id' => $p->user_id, 'name' => $p->name, 'employee_id' => $p->employee_id,
                        'role' => $p->role, 'response' => $p->attendance_status, 'is_mom_writer' => (bool) $p->is_mom_writer,
                    ])->values(),
                    'responses' => $list->countBy('attendance_status'),
                    'minutes_status' => $m->mom_status,
                    'decisions' => $m->decisions,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Meeting data fetched successfully',
                'data' => $data,
                'summary' => [
                    'total_meetings' => $data->count(),
                    'today' => $data->where('date', $today)->whereNotIn('status', ['cancelled'])->count(),
                    'by_status' => $data->countBy('status'),
                    'awaiting_my_response' => $data->where('my_response', 'pending')->whereIn('status', ['scheduled', 'postponed'])->count(),
                    'minutes_pending' => $data->where('status', 'completed')->where('minutes_status', '!=', 'finalized')->count(),
                    'date_range' => ['start' => $start, 'end' => $end],
                ],
                'scope' => $scope,
            ], 200);
        } catch (\Throwable $e) {
            return $this->failed('meetings', $e);
        }
    }
}
