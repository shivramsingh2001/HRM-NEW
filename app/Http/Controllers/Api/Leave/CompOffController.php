<?php

namespace App\Http\Controllers\Api\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ApprovalDecisionRequest;
use App\Http\Requests\Api\ClaimCompOffRequest;
use App\Models\CompOffCredit;
use App\Services\Attendance\PolicyResolver;
use App\Services\Leave\CompOffService;
use App\Services\RbacService;
use App\Support\SelfApproval;
use App\Traits\AuthorizesByScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Mobile API (legacy /api, JWT) — comp-off. Same rules as the web page
 * (Leave → Comp-off): everything goes through CompOffService. Envelope
 * { success, message, data }; a refused request is 422 with the reason in
 * `message`. New endpoints only — no existing response changes.
 */
class CompOffController extends Controller
{
    use AuthorizesByScope;

    public function __construct(private CompOffService $compOff) {}

    /** My comp-off: rules, balance, worked days I can still claim (last 60 days), my requests. */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $tenantId = (int) $user->tenant_id;
        $policy = app(PolicyResolver::class)->forUserDate($tenantId, (int) $user->id, now()->toDateString());

        return $this->ok('Comp-off', [
            'enabled' => $policy->compOffEnabled,
            'mode' => $policy->compOffMode,
            'full_day_hours' => $policy->compOffFullDayHours,
            'half_day_hours' => $policy->compOffHalfDayHours,
            'expiry_days' => $policy->compOffExpiryDays,
            'monthly_cap' => $policy->compOffMonthlyCap,
            'balance' => $this->compOff->balance($tenantId, (int) $user->id),
            'claimable' => array_map(fn ($d) => ['date' => $d['date'], 'day_type' => $d['day_type'], 'hours' => $d['hours'], 'days' => $d['days']],
                $this->compOff->claimableDays($tenantId, (int) $user->id, now()->subDays(60)->toDateString(), now()->subDay()->toDateString())),
            'requests' => CompOffCredit::with('decider:id,name')->where('user_id', $user->id)->orderByDesc('worked_date')->limit(100)->get()
                ->map(fn (CompOffCredit $c) => $c->toApi())->all(),
        ]);
    }

    public function store(ClaimCompOffRequest $request): JsonResponse
    {
        try {
            $credit = $this->compOff->request(Auth::user(), $request->validated('worked_date'), $request->validated('reason'));
        } catch (\RuntimeException $e) {
            return $this->refused($e->getMessage());
        }

        return $this->ok("Comp-off requested for {$credit->days} day(s).", $credit->load('decider:id,name')->toApi());
    }

    public function cancel($id): JsonResponse
    {
        try {
            $this->compOff->cancel(CompOffCredit::findOrFail($id), Auth::user());
        } catch (\RuntimeException $e) {
            return $this->refused($e->getMessage());
        }

        return $this->ok('Request cancelled.', []);
    }

    /** Requests of the employees this manager / HR / admin may decide for (status=pending by default). */
    public function approvals(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! app(RbacService::class)->can($user, 'leave', 'approve')) {
            return response()->json(['success' => false, 'message' => 'You cannot approve comp-off.'], 403);
        }
        $query = CompOffCredit::with(['user:id,name,employee_id', 'decider:id,name'])->where('user_id', '!=', $user->id)
            ->where('status', $request->input('status', 'pending'));
        $page = $this->applyScope($query, 'user_id', $user, 'leave', 'approve')->orderByDesc('worked_date')
            ->paginate(min(50, (int) $request->input('per_page', 20)));

        return $this->ok('Comp-off requests', [
            'requests' => array_map(fn ($row) => self::row($row), $page->items()),
            'pagination' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage()],
        ]);
    }

    public function decide(ApprovalDecisionRequest $request, $id): JsonResponse
    {
        $user = Auth::user();
        $credit = CompOffCredit::findOrFail($id);
        if (! app(RbacService::class)->can($user, 'leave', 'approve') || ! $this->scopeCoversOwner($user, 'leave', 'approve', (int) $credit->user_id)) {
            return response()->json(['success' => false, 'message' => SelfApproval::blocks($user, (int) $credit->user_id)
                ? SelfApproval::MESSAGE : 'You cannot decide comp-off for this employee.'], 403);
        }
        try {
            $credit = $this->compOff->decide($credit, $user, $request->validated('action'), $request->validated('remarks'));
        } catch (\RuntimeException $e) {
            return $this->refused($e->getMessage());
        }

        return $this->ok($credit->status === 'pending' ? 'Recorded — waiting for the next approval level.' : "Comp-off {$credit->status}.",
            $credit->load(['user:id,name,employee_id', 'decider:id,name'])->toApi(true));
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
        /** @var CompOffCredit $row */
        return $row->toApi(true);
    }
}
