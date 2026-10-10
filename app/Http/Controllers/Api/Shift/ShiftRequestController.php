<?php

namespace App\Http\Controllers\Api\Shift;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\ShiftRequest;
use App\Models\User;
use App\Models\UserShift;
use App\Services\Shift\ShiftRequestException;
use App\Services\Shift\ShiftRequestPresenter;
use App\Services\Shift\ShiftRequestService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Mobile API (legacy /api, JWT) — shift swaps and shift change requests.
 * Same rules and JSON shape as the web page: everything goes through
 * ShiftRequestService / ShiftRequestPresenter. Responses use the legacy
 * API envelope { success, message, data }; a refused request is 422 with
 * the reason in `message` (and every reason in `errors`).
 */
class ShiftRequestController extends Controller
{
    public function __construct(private ShiftRequestService $service, private ShiftRequestPresenter $presenter)
    {
    }

    /** Company rules + active shifts — what the "new request" screen needs. */
    public function options(): JsonResponse
    {
        $user = Auth::user();
        if ($r = $this->customShiftsOff($user)) {
            return $r;
        }
        $s = $this->service->settings((int) $user->tenant_id);
        $limits = app(\App\Services\Attendance\PolicyResolver::class)->forUserDate((int) $user->tenant_id, (int) $user->id, now()->toDateString());

        return $this->ok('Shift request options', [
            'swap_enabled' => $s->swap_enabled,
            'change_enabled' => $s->change_enabled,
            'requires_approval' => $s->requires_approval,
            'min_notice_hours' => $s->min_notice_hours,
            // From Company Policies → Working-time thresholds (null = no limit).
            'min_rest_hours' => $limits->minRestHours,
            'max_daily_hours' => $limits->maxDailyHours,
            'max_requests_per_month' => $s->max_requests_per_month,
            'peer_response_hours' => $s->peer_response_hours,
            'same_department_only' => $s->same_department_only,
            'same_branch_only' => $s->same_branch_only,
            'shifts' => Shift::where('status', 1)->orderBy('start_time')->get(['id', 'name', 'color_code', 'start_time', 'end_time', 'is_overnight']),
        ]);
    }

    /** My requests: tab=mine (raised by / involving me) | to_me (swaps waiting for my answer). */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = $request->input('tab') === 'to_me'
            ? ShiftRequest::where('counterpart_id', $user->id)
            : $this->presenter->ownQuery($user);

        return $this->list($query, $request, $user, 'Shift requests');
    }

    /** Requests waiting for this manager / HR / admin. */
    public function approvals(Request $request): JsonResponse
    {
        $user = Auth::user();

        return $this->list($this->presenter->approvalQuery($user), $request, $user, 'Shift requests waiting for approval');
    }

    public function show($id): JsonResponse
    {
        $user = Auth::user();

        return $this->ok('Shift request', $this->presenter->detail($this->presenter->find($id, $user), $user));
    }

    /** Colleagues I may swap with on a date, with their shift that day. */
    public function candidates(Request $request): JsonResponse
    {
        if ($r = $this->invalid($request, ['date' => 'required|date'])) {
            return $r;
        }
        $user = Auth::user();
        $settings = $this->service->settings((int) $user->tenant_id);
        $date = Carbon::parse($request->date)->toDateString();

        $job = DB::table('user_job_details')->where('user_id', $user->id)->first(['department', 'branch_id']);
        $query = User::where('status', 1)->where('id', '!=', $user->id)->where('role', '!=', 'admin');
        if ($settings->same_department_only) {
            $query->whereHas('jobDetails', fn ($q) => $q->where('department', $job->department ?? null));
        }
        if ($settings->same_branch_only) {
            $query->whereHas('jobDetails', fn ($q) => $q->where('branch_id', $job->branch_id ?? null));
        }
        $users = $query->orderBy('name')->get(['id', 'name', 'employee_id']);
        $rows = UserShift::with('shift:id,name,color_code,start_time,end_time')
            ->whereIn('user_id', $users->pluck('id')->push($user->id))
            ->where('date', $date)->where('is_additional', 0)->get()->keyBy('user_id');
        $label = fn ($s) => $s ? ['id' => $s->id, 'name' => $s->name, 'color_code' => $s->color_code ?: '#4f46e5', 'time' => Carbon::parse($s->start_time)->format('h:i A') . ' – ' . Carbon::parse($s->end_time)->format('h:i A')] : null;
        $mine = $rows->get($user->id)?->shift;

        return $this->ok('Swap candidates', [
            'date' => $date,
            'my_shift' => $label($mine),
            'colleagues' => $users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'employee_id' => $u->employee_id,
                'shift' => $label($s = $rows->get($u->id)?->shift),
                'can_swap' => (bool) ($s && $mine && $s->id !== $mine->id),
            ])->values()->all(),
        ]);
    }

    /** Check a swap / change without saving: the before → after table plus every problem. */
    public function preview(Request $request): JsonResponse
    {
        if ($r = $this->invalid($request, $this->formRules())) {
            return $r;
        }
        $user = Auth::user();
        try {
            $dates = $this->service->dateRange($request->start_date, $request->end_date);
            $items = $request->type === 'swap'
                ? $this->service->buildSwapItems((int) $user->tenant_id, (int) $user->id, (int) $request->counterpart_id, $dates)
                : $this->service->buildChangeItems((int) $user->tenant_id, (int) $user->id, $dates, (int) $request->to_shift_id);
            $check = $this->service->check((int) $user->tenant_id, $items, 'request');
        } catch (ShiftRequestException $e) {
            return $this->refused($e);
        }

        return $this->ok($check['errors'] ? $check['errors'][0] : 'This request can be sent.', ['items' => $this->service->describeItems($items)] + $check);
    }

    public function storeSwap(Request $request): JsonResponse
    {
        $request->merge(['type' => 'swap']);

        return $this->store($request);
    }

    public function storeChange(Request $request): JsonResponse
    {
        $request->merge(['type' => 'change']);

        return $this->store($request);
    }

    public function respond(Request $request, $id): JsonResponse
    {
        if ($r = $this->invalid($request, ['accept' => 'required|boolean', 'remarks' => 'nullable|string|max:500'])) {
            return $r;
        }
        $user = Auth::user();

        return $this->attempt(fn () => $this->service->respond($this->presenter->find($id, $user), $user, $request->boolean('accept'), $request->remarks, 'mobile'),
            $request->boolean('accept') ? 'You accepted the swap.' : 'You declined the swap.');
    }

    public function cancel(Request $request, $id): JsonResponse
    {
        $user = Auth::user();

        return $this->attempt(fn () => $this->service->cancel($this->presenter->find($id, $user), $user, $request->input('remarks'), 'mobile'), 'Request cancelled.');
    }

    /** Manager / HR / admin decision: action = approved | rejected. */
    public function decide(Request $request, $id): JsonResponse
    {
        if ($r = $this->invalid($request, ['action' => 'required|in:approved,rejected', 'remarks' => 'nullable|string|max:500'])) {
            return $r;
        }
        $user = Auth::user();

        return $this->attempt(function () use ($request, $id, $user) {
            return $this->service->decide($this->presenter->find($id, $user), $user, $request->action, $request->remarks, 'mobile');
        }, null);
    }

    // ------------------------------------------------------------------

    private function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if ($r = $this->customShiftsOff($user)) {
            return $r;
        }
        if ($r = $this->invalid($request, $this->formRules())) {
            return $r;
        }

        return $this->attempt(function () use ($request, $user) {
            $dates = $this->service->dateRange($request->start_date, $request->end_date);

            return $request->type === 'swap'
                ? $this->service->createSwapRequest($user, (int) $request->counterpart_id, $dates, $request->reason, 'mobile')
                : $this->service->createChangeRequest($user, $dates, (int) $request->to_shift_id, $request->reason, 'mobile');
        }, null);
    }

    private function list($query, Request $request, User $user, string $message): JsonResponse
    {
        if ($request->filled('status')) {
            $request->status === 'pending' ? $query->whereIn('status', ShiftRequest::PENDING) : $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        $page = $query->orderByDesc('id')->paginate(min(50, (int) $request->input('per_page', 20)));

        return $this->ok($message, [
            'requests' => collect($page->items())->map(fn ($r) => $this->presenter->row($r, $user))->all(),
            'pagination' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage()],
        ]);
    }

    private function attempt(callable $fn, ?string $message): JsonResponse
    {
        try {
            $req = $fn()->refresh();
        } catch (ShiftRequestException $e) {
            return $this->refused($e);
        }

        $message ??= match ($req->status) {
            ShiftRequest::STATUS_PENDING_PEER => "Swap request {$req->request_no} sent — waiting for your colleague to accept.",
            ShiftRequest::STATUS_PENDING_APPROVAL => "Request {$req->request_no} is waiting for approval.",
            ShiftRequest::STATUS_APPROVED => "Approved — the shifts are updated ({$req->request_no}).",
            ShiftRequest::STATUS_REJECTED => "Request {$req->request_no} rejected.",
            default => "Request {$req->request_no}: " . strtolower($req->statusLabel()) . '.',
        };

        return $this->ok($message, $this->presenter->detail($req, Auth::user()));
    }

    private function formRules(): array
    {
        return [
            'type' => 'required|in:swap,change',
            'counterpart_id' => 'nullable|required_if:type,swap|integer|exists:users,id',
            'to_shift_id' => 'nullable|required_if:type,change|integer|exists:shifts,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:500',
        ];
    }

    private function customShiftsOff(User $user): ?JsonResponse
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        return $tenant && ! $tenant->custom_shifts_enabled
            ? response()->json(['success' => false, 'message' => 'Your company uses one fixed shift — shift swaps and changes are not available.'], 403)
            : null;
    }

    private function invalid(Request $request, array $rules): ?JsonResponse
    {
        $v = Validator::make($request->all(), $rules, [
            'counterpart_id.required_if' => 'Pick the colleague to swap with.',
            'to_shift_id.required_if' => 'Pick the shift you want.',
        ]);

        return $v->fails()
            ? response()->json(['success' => false, 'message' => $v->errors()->first(), 'errors' => $v->errors()], 422)
            : null;
    }

    private function refused(ShiftRequestException $e): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors ?: [$e->getMessage()]], 422);
    }

    private function ok(string $message, array $data): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }
}
