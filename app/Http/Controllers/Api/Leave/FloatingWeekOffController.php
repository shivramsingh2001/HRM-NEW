<?php

namespace App\Http\Controllers\Api\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ApprovalDecisionRequest;
use App\Http\Requests\Api\PlanFloatingWeekOffRequest;
use App\Models\FloatingWeekoffDay;
use App\Services\Attendance\FloatingWeekOffRequestService;
use App\Services\RbacService;
use App\Support\SelfApproval;
use App\Traits\AuthorizesByScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Mobile API (legacy /api, JWT) — floating week-offs: this / next month's
 * count, plan a day off ahead, approvers decide. Same rules as the web page
 * (Leave → Floating Week-offs) via FloatingWeekOffRequestService. Envelope
 * { success, message, data }; refused = 422. New endpoints only.
 */
class FloatingWeekOffController extends Controller
{
    use AuthorizesByScope;

    public function __construct(private FloatingWeekOffRequestService $planner) {}

    /** This and next month's count (left / used / waiting) and my days off from last month on. */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $tenantId = (int) $user->tenant_id;
        $months = collect([now(), now()->addMonthNoOverflow()])->map(function ($m) use ($tenantId, $user) {
            $s = $this->planner->monthStatus($tenantId, (int) $user->id, $m->format('Y-m'));

            return ['month' => $m->format('Y-m'), 'label' => $m->format('F Y')] + $s + ['left' => max(0, $s['quota'] - $s['used'] - $s['pending'])];
        });

        return $this->ok('Floating week-offs', [
            'enabled' => $months->contains(fn ($m) => $m['enabled']),
            'months' => $months->values()->all(),
            'days' => FloatingWeekoffDay::with('decider:id,name')->where('user_id', $user->id)
                ->where('date', '>=', now()->subMonthNoOverflow()->startOfMonth()->toDateString())->orderByDesc('date')->limit(100)->get()
                ->map(fn (FloatingWeekoffDay $d) => $d->toApi())->all(),
        ]);
    }

    /** Can this day be planned? data.allowed + the reason when not. */
    public function check(Request $request): JsonResponse
    {
        $date = (string) $request->query('date', '');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! strtotime($date)) {
            return $this->refused('date must be a day in Y-m-d format.');
        }
        $why = $this->planner->reason((int) Auth::user()->tenant_id, (int) Auth::id(), $date);

        return $this->ok($why ?? 'You can ask for this day.', ['date' => $date, 'allowed' => $why === null, 'reason' => $why]);
    }

    public function store(PlanFloatingWeekOffRequest $request): JsonResponse
    {
        try {
            $row = $this->planner->request(Auth::user(), $request->validated('date'), $request->validated('reason'));
        } catch (\RuntimeException $e) {
            return $this->refused($e->getMessage());
        }

        return $this->ok('Floating week-off requested.', $row->load('decider:id,name')->toApi());
    }

    /** The employee withdraws their own; an approver may cancel an employee's approved day. */
    public function cancel($id): JsonResponse
    {
        $user = Auth::user();
        $row = FloatingWeekoffDay::findOrFail($id);
        $own = (int) $row->user_id === (int) $user->id;
        if (! $own && ! (app(RbacService::class)->can($user, 'leave', 'approve') && $this->scopeCoversOwner($user, 'leave', 'approve', (int) $row->user_id))) {
            return response()->json(['success' => false, 'message' => 'You cannot cancel this.'], 403);
        }
        try {
            $this->planner->cancel($row, $user);
        } catch (\RuntimeException $e) {
            return $this->refused($e->getMessage());
        }

        return $this->ok('Cancelled.', []);
    }

    /** Planned days waiting for this manager / HR / admin (status=pending by default). */
    public function approvals(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! app(RbacService::class)->can($user, 'leave', 'approve')) {
            return response()->json(['success' => false, 'message' => 'You cannot approve floating week-offs.'], 403);
        }
        $query = FloatingWeekoffDay::with(['user:id,name,employee_id', 'decider:id,name'])->where('source', 'planned')
            ->where('user_id', '!=', $user->id)->where('status', $request->input('status', 'pending'));
        $page = $this->applyScope($query, 'user_id', $user, 'leave', 'approve')->orderBy('date')
            ->paginate(min(50, (int) $request->input('per_page', 20)));

        return $this->ok('Floating week-off requests', [
            'requests' => array_map(fn ($row) => self::row($row), $page->items()),
            'pagination' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage()],
        ]);
    }

    public function decide(ApprovalDecisionRequest $request, $id): JsonResponse
    {
        $user = Auth::user();
        $row = FloatingWeekoffDay::where('source', 'planned')->findOrFail($id);
        if (! app(RbacService::class)->can($user, 'leave', 'approve') || ! $this->scopeCoversOwner($user, 'leave', 'approve', (int) $row->user_id)) {
            return response()->json(['success' => false, 'message' => SelfApproval::blocks($user, (int) $row->user_id)
                ? SelfApproval::MESSAGE : 'You cannot decide for this employee.'], 403);
        }
        try {
            $row = $this->planner->decide($row, $user, $request->validated('action'), $request->validated('remarks'));
        } catch (\RuntimeException $e) {
            return $this->refused($e->getMessage());
        }

        return $this->ok($row->status === 'pending' ? 'Recorded — waiting for the next approval level.' : "Request {$row->status}.",
            $row->load(['user:id,name,employee_id', 'decider:id,name'])->toApi(true));
    }

    private function ok(string $message, array $data): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    private function refused(string $reason): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $reason, 'errors' => [$reason]], 422);
    }

    /** One approver-list row (the scope helper hides the model type from static analysis). */
    private static function row(mixed $row): array
    {
        /** @var FloatingWeekoffDay $row */
        return $row->toApi(true);
    }
}
