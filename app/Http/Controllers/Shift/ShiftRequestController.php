<?php

namespace App\Http\Controllers\Shift;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\ShiftRequest;
use App\Models\User;
use App\Models\UserShift;
use App\Services\Shift\ShiftRequestException;
use App\Services\Shift\ShiftRequestPresenter;
use App\Services\Shift\ShiftRequestService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Shift → Shift Requests (web). Employees raise swaps / change requests and
 * answer colleagues; reporting managers, HR and admins approve; admins / HR /
 * managers (own team) swap or change shifts directly. All rules live in
 * ShiftRequestService — this controller only validates input, scopes lists
 * and shapes JSON for the page.
 */
class ShiftRequestController extends Controller
{
    public function __construct(private ShiftRequestService $service, private ShiftRequestPresenter $presenter)
    {
    }

    public function index()
    {
        $user = Auth::user();
        $tenantId = (int) $user->tenant_id;
        $isApprover = in_array($user->role, ['admin', 'hr', 'manager'], true);

        return view('client.shift.requests', [
            'settings' => $this->service->settings($tenantId),
            // Rest / daily-hours limits live on Company Policies → Working-time thresholds.
            'workingTime' => app(\App\Services\Attendance\PolicyResolver::class)->forTenantDate($tenantId, now()->toDateString()),
            'shifts' => Shift::where('status', 1)->orderBy('start_time')->get(['id', 'name', 'color_code', 'start_time', 'end_time']),
            'isApprover' => $isApprover,
            'canDirect' => $isApprover,
            'canRevert' => in_array($user->role, ['admin', 'hr'], true),
            'directUsers' => $isApprover ? $this->manageableUsers($user) : collect(),
            'counts' => [
                'to_me' => ShiftRequest::where('counterpart_id', $user->id)->where('status', ShiftRequest::STATUS_PENDING_PEER)->count(),
                'approvals' => $isApprover ? $this->approvalQuery($user)->count() : 0,
            ],
        ]);
    }

    /** JSON list for a tab: mine | to_me | approvals | all. */
    public function data(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tab = $request->input('tab', 'mine');

        $query = match ($tab) {
            'to_me' => ShiftRequest::where('counterpart_id', $user->id),
            'approvals' => $this->approvalQuery($user),
            'all' => $this->visibleQuery($user),
            default => $this->presenter->ownQuery($user),
        };

        $query->with(['requester:id,name,employee_id', 'counterpart:id,name,employee_id', 'creator:id,name', 'items.toShift:id,name,color_code,start_time,end_time', 'items.fromShift:id,name,color_code,start_time,end_time']);

        if ($request->filled('status')) {
            $request->status === 'pending' ? $query->whereIn('status', ShiftRequest::PENDING) : $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('mode')) {
            $query->where('mode', $request->mode);
        }
        if ($request->filled('from')) {
            $query->whereHas('items', fn ($q) => $q->whereDate('date', '>=', $request->from));
        }
        if ($request->filled('to')) {
            $query->whereHas('items', fn ($q) => $q->whereDate('date', '<=', $request->to));
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('request_no', 'like', "%{$s}%")
                ->orWhereHas('requester', fn ($u) => $u->where('name', 'like', "%{$s}%")->orWhere('employee_id', 'like', "%{$s}%"))
                ->orWhereHas('counterpart', fn ($u) => $u->where('name', 'like', "%{$s}%")->orWhere('employee_id', 'like', "%{$s}%")));
        }

        $page = $query->orderByDesc('id')->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'status' => true,
            'data' => collect($page->items())->map(fn ($r) => $this->row($r, $user))->all(),
            'pagination' => [
                'total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
                'from' => $page->firstItem(), 'to' => $page->lastItem(),
            ],
        ]);
    }

    public function show($id): JsonResponse
    {
        $user = Auth::user();

        return response()->json(['status' => true, 'data' => $this->presenter->detail($this->find($id, $user), $user)]);
    }

    /** Validate without saving — the preview card in the swap / change forms. */
    public function preview(Request $request): JsonResponse
    {
        $user = Auth::user();
        $data = $this->validateForm($request, true);

        try {
            $dates = $this->service->dateRange($data['start_date'], $data['end_date'] ?? null);
            $direct = $data['mode'] === 'direct';
            if ($direct && ! $this->service->canManage($user, array_filter([$data['user_a'] ?? null, $data['user_b'] ?? null, $data['user_id'] ?? null]))) {
                throw new ShiftRequestException('You can only change shifts of employees who report to you.');
            }
            $requesterId = $direct ? (int) ($data['user_a'] ?? $data['user_id'] ?? 0) : (int) ($this->onBehalfUser($request, $user)?->id ?? $user->id);
            $items = $data['type'] === 'swap'
                ? $this->service->buildSwapItems((int) $user->tenant_id, $requesterId, (int) ($direct ? $data['user_b'] : $data['counterpart_id']), $dates)
                : $this->service->buildChangeItems((int) $user->tenant_id, $direct ? (int) $data['user_id'] : $requesterId, $dates, (int) $data['to_shift_id']);
            $check = $this->service->check((int) $user->tenant_id, $items, $direct ? 'direct' : 'request');
        } catch (ShiftRequestException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage(), 'errors' => $e->errors ?: [$e->getMessage()]], 422);
        }

        return response()->json([
            'status' => true,
            'data' => ['items' => $this->service->describeItems($items)] + $check,
        ]);
    }

    /** Employee raises a swap / change request (HR / admin may raise one for an employee). */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        $data = $this->validateForm($request);

        return $this->attempt(function () use ($request, $user, $data) {
            $dates = $this->service->dateRange($data['start_date'], $data['end_date'] ?? null);
            $onBehalf = $this->onBehalfUser($request, $user);
            $requester = $onBehalf ?? $user;
            $req = $data['type'] === 'swap'
                ? $this->service->createSwapRequest($requester, (int) $data['counterpart_id'], $dates, $data['reason'] ?? null, 'web', $onBehalf ? $user : null)
                : $this->service->createChangeRequest($requester, $dates, (int) $data['to_shift_id'], $data['reason'] ?? null, 'web', $onBehalf ? $user : null);

            return [$req, $req->type === 'swap'
                ? "Swap request {$req->request_no} sent — waiting for your colleague to accept."
                : ($req->status === ShiftRequest::STATUS_APPROVED ? "Shift changed ({$req->request_no})." : "Request {$req->request_no} sent for approval.")];
        });
    }

    public function directSwap(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_a' => 'required|integer|exists:users,id',
            'user_b' => 'required|integer|exists:users,id|different:user_a',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'reason' => 'required|string|max:500',
        ], ['reason.required' => 'Please enter the reason for this swap.']);

        return $this->attempt(function () use ($data) {
            $req = $this->service->directSwap(Auth::user(), (int) $data['user_a'], (int) $data['user_b'], $this->service->dateRange($data['start_date'], $data['end_date'] ?? null), $data['reason']);

            return [$req, "Shifts swapped ({$req->request_no})."];
        });
    }

    public function directChange(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'to_shift_id' => 'required|integer|exists:shifts,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'reason' => 'required|string|max:500',
        ], ['reason.required' => 'Please enter the reason for this change.']);

        return $this->attempt(function () use ($data) {
            $req = $this->service->directChange(Auth::user(), (int) $data['user_id'], $this->service->dateRange($data['start_date'], $data['end_date'] ?? null), (int) $data['to_shift_id'], $data['reason']);

            return [$req, "Shift changed ({$req->request_no})."];
        });
    }

    public function respond(Request $request, $id): JsonResponse
    {
        $data = $request->validate(['accept' => 'required|boolean', 'remarks' => 'nullable|string|max:500']);
        $req = $this->find($id, Auth::user());

        return $this->attempt(function () use ($req, $data) {
            $accept = (bool) $data['accept'];
            $req = $this->service->respond($req, Auth::user(), $accept, $data['remarks'] ?? null);

            return [$req, $accept ? 'You accepted the swap.' : 'You declined the swap.'];
        });
    }

    public function decide(Request $request, $id): JsonResponse
    {
        $data = $request->validate(['action' => 'required|in:approved,rejected', 'remarks' => 'nullable|string|max:500']);
        $req = $this->find($id, Auth::user());

        return $this->attempt(function () use ($req, $data) {
            $req = $this->service->decide($req, Auth::user(), $data['action'], $data['remarks'] ?? null);

            return [$req, match ($req->status) {
                ShiftRequest::STATUS_APPROVED => 'Approved — the shifts are updated.',
                ShiftRequest::STATUS_REJECTED => 'Request rejected.',
                default => 'Approved at your level — waiting for the next approver.',
            }];
        });
    }

    /** Approve / reject several pending requests (each one goes through the same checks). */
    public function bulkDecide(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer', 'action' => 'required|in:approved,rejected', 'remarks' => 'nullable|string|max:500']);
        $done = 0;
        $failed = [];
        foreach ($data['ids'] as $id) {
            try {
                $req = $this->find($id, Auth::user());
                $this->service->decide($req, Auth::user(), $data['action'], $data['remarks'] ?? null);
                $done++;
            } catch (ShiftRequestException $e) {
                $failed[] = ($req->request_no ?? "#{$id}") . ': ' . $e->getMessage();
            }
        }

        return response()->json([
            'status' => $done > 0 || ! $failed,
            'message' => "{$done} request(s) " . ($data['action'] === 'approved' ? 'approved' : 'rejected') . '.' . ($failed ? ' Not done: ' . implode(' ', array_slice($failed, 0, 3)) : ''),
            'data' => ['done' => $done, 'failed' => $failed],
        ], $done > 0 || ! $failed ? 200 : 422);
    }

    public function cancel(Request $request, $id): JsonResponse
    {
        $data = $request->validate(['remarks' => 'nullable|string|max:500']);
        $req = $this->find($id, Auth::user());

        return $this->attempt(fn () => [$this->service->cancel($req, Auth::user(), $data['remarks'] ?? null), 'Request cancelled.']);
    }

    public function revert(Request $request, $id): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], ['reason.required' => 'Please enter why this change is being reverted.']);
        $req = $this->find($id, Auth::user());

        return $this->attempt(fn () => [$this->service->revert($req, Auth::user(), $data['reason']), 'Reverted — the earlier shifts are back.']);
    }

    /** Colleagues the employee may swap with on a date, with their shift that day. */
    public function candidates(Request $request): JsonResponse
    {
        $request->validate(['date' => 'required|date']);
        $user = $this->onBehalfUser($request, Auth::user()) ?? Auth::user();
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
        $mine = $rows->get($user->id)?->shift;

        return response()->json([
            'status' => true,
            'data' => [
                'my_shift' => $mine ? $this->shiftLabel($mine) : null,
                'colleagues' => $users->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'employee_id' => $u->employee_id,
                    'shift' => ($s = $rows->get($u->id)?->shift) ? $this->shiftLabel($s) : null,
                    'can_swap' => $s && $mine && $s->id !== $mine->id,
                ])->values()->all(),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        abort_unless(in_array($user->role, ['admin', 'hr', 'manager'], true), 403);
        $rows = $this->visibleQuery($user)->with(['requester', 'counterpart', 'creator', 'decider', 'items.fromShift', 'items.toShift', 'items.user'])
            ->when($request->filled('status'), fn ($q) => $request->status === 'pending' ? $q->whereIn('status', ShiftRequest::PENDING) : $q->where('status', $request->status))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('from'), fn ($q) => $q->whereHas('items', fn ($i) => $i->whereDate('date', '>=', $request->from)))
            ->when($request->filled('to'), fn ($q) => $q->whereHas('items', fn ($i) => $i->whereDate('date', '<=', $request->to)))
            ->orderByDesc('id')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Request No', 'Type', 'Mode', 'Status', 'Employee', 'Employee ID', 'Date', 'From Shift', 'To Shift', 'Colleague', 'Raised By', 'Raised At', 'Reason', 'Decided By', 'Decided At', 'Approver Remarks']);
            foreach ($rows as $r) {
                foreach ($r->items as $i) {
                    fputcsv($out, [
                        $r->request_no, $r->typeLabel(), $r->mode === 'direct' ? 'Direct' : 'Request', $r->statusLabel(),
                        $i->user->name ?? '', $i->user->employee_id ?? '', $i->date->format('Y-m-d'),
                        $i->fromShift->name ?? '—', $i->toShift->name ?? '—',
                        $r->type === 'swap' ? (($i->user_id == $r->requester_id ? $r->counterpart?->name : $r->requester?->name) ?? '') : '',
                        $r->creator->name ?? '', $r->created_at?->format('Y-m-d H:i'), $r->reason,
                        $r->decider->name ?? '', $r->decided_at?->format('Y-m-d H:i'), $r->approver_remarks,
                    ]);
                }
            }
            fclose($out);
        }, 'shift_requests_' . now()->format('Y_m_d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    // ------------------------------------------------------------------

    private function attempt(callable $fn): JsonResponse
    {
        try {
            [$req, $message] = $fn();
        } catch (ShiftRequestException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage(), 'errors' => $e->errors ?: [$e->getMessage()]], 422);
        }

        return response()->json(['status' => true, 'message' => $message, 'data' => ['id' => $req->id, 'request_no' => $req->request_no, 'status' => $req->status]]);
    }

    private function validateForm(Request $request, bool $preview = false): array
    {
        return $request->validate([
            'mode' => $preview ? 'required|in:request,direct' : 'nullable',
            'type' => 'required|in:swap,change',
            'counterpart_id' => 'nullable|required_if:type,swap|integer|exists:users,id',
            'to_shift_id' => 'nullable|required_if:type,change|integer|exists:shifts,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'user_a' => 'nullable|integer|exists:users,id',
            'user_b' => 'nullable|integer|exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:500',
            'on_behalf_user_id' => 'nullable|integer|exists:users,id',
        ], [
            'counterpart_id.required_if' => 'Pick the colleague to swap with.',
            'to_shift_id.required_if' => 'Pick the shift you want.',
        ]);
    }

    /** HR / admin raising a request for an employee ("on behalf"). */
    private function onBehalfUser(Request $request, User $actor): ?User
    {
        if (! $request->filled('on_behalf_user_id') || ! in_array($actor->role, ['admin', 'hr'], true)) {
            return null;
        }

        return User::where('status', 1)->findOrFail($request->on_behalf_user_id);
    }

    private function find($id, User $user): ShiftRequest
    {
        return $this->presenter->find($id, $user);
    }

    private function visibleQuery(User $user): Builder
    {
        return $this->presenter->visibleQuery($user);
    }

    private function approvalQuery(User $user): Builder
    {
        return $this->presenter->approvalQuery($user);
    }

    private function row(ShiftRequest $r, User $viewer): array
    {
        return $this->presenter->row($r, $viewer);
    }

    private function manageableUsers(User $user)
    {
        $query = User::where('status', 1)->where('role', '!=', 'admin')->orderBy('name');
        if ($user->role === 'manager') {
            $query->managedBy($user->id);
        }

        return $query->get(['id', 'name', 'employee_id']);
    }

    private function shiftLabel($s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'color' => $s->color_code ?: '#4f46e5',
            'time' => Carbon::parse($s->start_time)->format('h:i A') . ' – ' . Carbon::parse($s->end_time)->format('h:i A'),
        ];
    }
}
