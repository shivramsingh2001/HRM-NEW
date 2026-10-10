<?php

namespace App\Services\Shift;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequest;
use App\Models\ShiftRequestEvent;
use App\Models\ShiftRequestItem;
use App\Models\ShiftRequestSetting;
use App\Models\User;
use App\Models\UserShift;
use App\Models\UserWeekoffs;
use App\Services\Approvals\ApprovalService;
use App\Services\AuditLogger;
use App\Support\ShiftWindow;
use App\Support\WeekOffPredicate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shift swaps and shift change requests — the one place their rules live,
 * used by the web pages and the mobile API alike.
 *
 *  - Direct (admin / HR / a manager for their own team): saved approved and
 *    applied at once — directSwap(), directChange().
 *  - Employee request: swap → colleague accepts → approval → applied
 *    (createSwapRequest, respond, decide); change → approval → applied
 *    (createChangeRequest, decide). cancel(), expireDue(), revert().
 *
 * Approval goes through the generic ApprovalService ('shift_request',
 * ShiftRequestApprovalHandler) when the company configured a workflow for it,
 * else the requester's reporting head / HR / admin decide directly.
 *
 * Applying a request writes one-day overrides (ShiftAssignmentService::
 * applyDayOverride), logs every changed day (ShiftChangeRecorder) and keeps a
 * full timeline in shift_request_events. Swaps cover working days only — a
 * day that is a week-off for either employee can't be swapped.
 */
class ShiftRequestService
{
    public function __construct(
        private ShiftAssignmentService $assignments,
        private ShiftChangeGuard $guard,
        private ShiftOverlapGuard $overlap,
        private ShiftChangeRecorder $recorder,
        private ShiftNotificationService $notify,
    ) {
    }

    public function settings(int $tenantId): ShiftRequestSetting
    {
        return ShiftRequestSetting::forTenant($tenantId);
    }

    // ==================================================================
    // Who may do what
    // ==================================================================

    /** Admin / HR: anyone; a manager: only their own reportees. */
    public function canManage(User $actor, array $userIds): bool
    {
        if (in_array($actor->role, ['admin', 'hr'], true)) {
            return true;
        }
        if ($actor->role !== 'manager') {
            return false;
        }
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (in_array((int) $actor->id, $userIds, true)) {
            return false; // never their own shift
        }

        return User::where('tenant_id', $actor->tenant_id)->whereIn('id', $userIds)->managedBy($actor->id)->count() === count($userIds);
    }

    /** May $actor approve / reject this pending request (no workflow configured)? */
    public function canApprove(User $actor, ShiftRequest $req): bool
    {
        if (in_array((int) $actor->id, $req->involvedUserIds(), true)) {
            return false;
        }
        if (in_array($actor->role, ['admin', 'hr'], true)) {
            return true;
        }

        return $actor->role === 'manager'
            && User::where('id', $req->requester_id)->managedBy($actor->id)->exists();
    }

    /** Requester's reporting heads, else the company's HR and admins. */
    public function approverIds(ShiftRequest $req): array
    {
        $heads = DB::table('user_reporting_heads')->where('user_id', $req->requester_id)->pluck('reporting_head_id')->map(fn ($i) => (int) $i)->all();
        $hr = User::where('tenant_id', $req->tenant_id)->whereIn('role', ['hr', 'admin'])->where('status', 1)->pluck('id')->map(fn ($i) => (int) $i)->all();

        return array_values(array_diff(array_unique(array_merge($heads, $hr)), $req->involvedUserIds()));
    }

    // ==================================================================
    // Building + checking the days a request changes
    // ==================================================================

    /** Y-m-d dates from..to (max 31 days). */
    public function dateRange(string $from, ?string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = $to ? Carbon::parse($to)->startOfDay() : $start->copy();
        if ($end->lt($start)) {
            throw new ShiftRequestException('The end date must be on or after the start date.');
        }
        if ($start->diffInDays($end) > 30) {
            throw new ShiftRequestException('A request can cover at most 31 days.');
        }
        $dates = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dates[] = $d->toDateString();
        }

        return $dates;
    }

    /**
     * Swap: on each date the two employees exchange their main shifts. Dates
     * where both already work the same shift are skipped.
     *
     * @return array<int, array{user_id:int, date:string, from_shift_id:?int, to_shift_id:int, user_shift_id:?int}>
     */
    public function buildSwapItems(int $tenantId, int $userA, int $userB, array $dates): array
    {
        if ($userA === $userB) {
            throw new ShiftRequestException('Pick two different employees to swap.');
        }
        $rows = $this->primaryRows($tenantId, [$userA, $userB], $dates);
        $names = User::whereIn('id', [$userA, $userB])->pluck('name', 'id');
        $items = [];
        $errors = [];

        foreach ($dates as $date) {
            $a = $rows->get("{$userA}|{$date}");
            $b = $rows->get("{$userB}|{$date}");
            $day = Carbon::parse($date)->format('d M');
            if (! $a || ! $b) {
                $errors[] = ($names[$a ? $userB : $userA] ?? 'An employee') . " has no shift on {$day} — only working days can be swapped.";
                continue;
            }
            if ((int) $a->shift_id === (int) $b->shift_id) {
                continue;
            }
            $items[] = ['user_id' => $userA, 'date' => $date, 'from_shift_id' => (int) $a->shift_id, 'to_shift_id' => (int) $b->shift_id, 'user_shift_id' => (int) $a->id];
            $items[] = ['user_id' => $userB, 'date' => $date, 'from_shift_id' => (int) $b->shift_id, 'to_shift_id' => (int) $a->shift_id, 'user_shift_id' => (int) $b->id];
        }

        if ($errors) {
            throw new ShiftRequestException($errors[0], $errors);
        }
        if (! $items) {
            throw new ShiftRequestException('Both employees already work the same shift on the selected date(s) — there is nothing to swap.');
        }

        return $items;
    }

    /** Change: the employee works $toShiftId on each date (an unassigned working day may be filled). */
    public function buildChangeItems(int $tenantId, int $userId, array $dates, int $toShiftId): array
    {
        $shift = Shift::where('tenant_id', $tenantId)->where('status', 1)->find($toShiftId);
        if (! $shift) {
            throw new ShiftRequestException('Pick an active shift.');
        }
        $rows = $this->primaryRows($tenantId, [$userId], $dates);
        $items = [];
        foreach ($dates as $date) {
            $row = $rows->get("{$userId}|{$date}");
            if ($row && (int) $row->shift_id === $toShiftId) {
                continue;
            }
            $items[] = ['user_id' => $userId, 'date' => $date, 'from_shift_id' => $row ? (int) $row->shift_id : null, 'to_shift_id' => $toShiftId, 'user_shift_id' => $row ? (int) $row->id : null];
        }
        if (! $items) {
            throw new ShiftRequestException("{$shift->name} is already the shift on the selected date(s).");
        }

        return $items;
    }

    /**
     * Every rule a day must pass. mode 'request' (employee) enforces notice
     * and rest hours; 'direct' (admin / HR / manager) turns the rest-hours
     * rule into a warning. Returns ['errors' => [...], 'warnings' => [...]].
     */
    public function check(int $tenantId, array $items, string $mode, ?int $ignoreRequestId = null): array
    {
        $settings = $this->settings($tenantId);
        $errors = [];
        $warnings = [];
        $now = now();
        $today = Carbon::today()->toDateString();

        $userIds = array_values(array_unique(array_column($items, 'user_id')));
        $names = User::whereIn('id', $userIds)->pluck('name', 'id');
        $shifts = Shift::withoutGlobalScopes()->whereIn('id', array_filter(array_merge(array_column($items, 'to_shift_id'), array_column($items, 'from_shift_id'))))->get()->keyBy('id');
        $weekoffs = UserWeekoffs::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereIn('user_id', $userIds)->where('status', 1)->get()->groupBy('user_id');

        foreach ($items as $item) {
            $uid = (int) $item['user_id'];
            $date = $item['date'];
            $who = $names[$uid] ?? 'The employee';
            $day = Carbon::parse($date)->format('d M Y');
            $to = $shifts->get($item['to_shift_id']);

            if ($date < $today) {
                $errors[] = "{$day} is in the past — only today or later can be changed.";
                continue;
            }
            if ($e = $this->guard->dateError($tenantId, $uid, $date, false, $who)) {
                $errors[] = $e;
                continue;
            }
            if (WeekOffPredicate::isWeekOff($weekoffs->get($uid, collect()), Carbon::parse($date))) {
                $errors[] = "{$day} is a week-off for {$who} — only working days can be changed.";
                continue;
            }
            if ($mode === 'request') {
                $starts = collect([$to, $shifts->get($item['from_shift_id'])])->filter()->map(fn ($s) => ShiftWindow::window($date, $s)[0]);
                $hoursLeft = $now->diffInMinutes($starts->min(), false) / 60;
                if ($hoursLeft < $settings->min_notice_hours) {
                    $errors[] = "The shift on {$day} starts too soon — requests need at least {$settings->min_notice_hours} hours' notice.";
                    continue;
                }
            }
            $leave = DB::table('leaves')->where('tenant_id', $tenantId)->where('user_id', $uid)
                ->whereIn('status', ['approved', 'pending'])
                ->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->value('status');
            if ($leave) {
                $errors[] = "{$who} has " . ($leave === 'approved' ? 'approved' : 'a pending') . " leave on {$day}.";
                continue;
            }
            $pending = ShiftRequestItem::withoutGlobalScopes()
                ->join('shift_requests', 'shift_requests.id', '=', 'shift_request_items.shift_request_id')
                ->where('shift_request_items.tenant_id', $tenantId)
                ->where('shift_request_items.user_id', $uid)
                ->whereDate('shift_request_items.date', $date)
                ->whereIn('shift_requests.status', ShiftRequest::PENDING)
                ->when($ignoreRequestId, fn ($q) => $q->where('shift_requests.id', '!=', $ignoreRequestId))
                ->value('shift_requests.request_no');
            if ($pending) {
                // An admin / manager may still change the day; that request then can't be approved (stale).
                if ($mode === 'direct') {
                    $warnings[] = "{$who} has a pending shift request ({$pending}) for {$day} — it will no longer be approvable after this change.";
                } else {
                    $errors[] = "{$who} already has a pending shift request ({$pending}) for {$day}.";
                    continue;
                }
            }
            if ($to && ($clash = $this->additionalClash($tenantId, $uid, $to, $date))) {
                $errors[] = "{$to->name} would overlap {$who}'s {$clash} around {$day}.";
            }
        }

        // Main shifts next to each changed day: overlap = error, short rest = error (request) / warning (direct).
        foreach ($this->restIssues($tenantId, $items) as $issue) {
            if ($issue['overlap'] || $mode === 'request') {
                $errors[] = $issue['message'];
            } else {
                $warnings[] = $issue['message'];
            }
        }

        return ['errors' => array_values(array_unique($errors)), 'warnings' => array_values(array_unique($warnings))];
    }

    /** Item rows with names / times for previews and API responses. */
    public function describeItems(array $items): array
    {
        $names = User::whereIn('id', array_unique(array_column($items, 'user_id')))->get(['id', 'name', 'employee_id'])->keyBy('id');
        $shifts = Shift::withoutGlobalScopes()->whereIn('id', array_filter(array_merge(array_column($items, 'to_shift_id'), array_column($items, 'from_shift_id'))))->get()->keyBy('id');
        $label = fn ($id) => $id && $shifts->get($id) ? [
            'id' => (int) $id,
            'name' => $shifts[$id]->name,
            'color' => $shifts[$id]->color_code ?: '#4f46e5',
            'time' => Carbon::parse($shifts[$id]->start_time)->format('h:i A') . ' – ' . Carbon::parse($shifts[$id]->end_time)->format('h:i A'),
        ] : null;

        return array_map(fn ($i) => [
            'user_id' => (int) $i['user_id'],
            'user_name' => $names[$i['user_id']]->name ?? '—',
            'employee_id' => $names[$i['user_id']]->employee_id ?? null,
            'date' => $i['date'],
            'date_label' => Carbon::parse($i['date'])->format('D, d M Y'),
            'from' => $label($i['from_shift_id']),
            'to' => $label($i['to_shift_id']),
        ], $items);
    }

    // ==================================================================
    // Direct swap / change (admin, HR, manager for their team)
    // ==================================================================

    public function directSwap(User $actor, int $userA, int $userB, array $dates, string $reason, string $channel = 'web'): ShiftRequest
    {
        if (! $this->canManage($actor, [$userA, $userB])) {
            throw new ShiftRequestException('You can only swap shifts of employees who report to you.');
        }
        $items = $this->buildSwapItems((int) $actor->tenant_id, $userA, $userB, $dates);

        return $this->saveDirect($actor, 'swap', $userA, $userB, $items, $reason, $channel);
    }

    public function directChange(User $actor, int $userId, array $dates, int $toShiftId, string $reason, string $channel = 'web'): ShiftRequest
    {
        if (! $this->canManage($actor, [$userId])) {
            throw new ShiftRequestException("You can only change shifts of employees who report to you.");
        }
        $items = $this->buildChangeItems((int) $actor->tenant_id, $userId, $dates, $toShiftId);

        return $this->saveDirect($actor, 'change', $userId, null, $items, $reason, $channel);
    }

    private function saveDirect(User $actor, string $type, int $requesterId, ?int $counterpartId, array $items, string $reason, string $channel): ShiftRequest
    {
        $tenantId = (int) $actor->tenant_id;
        $check = $this->check($tenantId, $items, 'direct');
        if ($check['errors']) {
            throw new ShiftRequestException($check['errors'][0], $check['errors']);
        }

        $req = DB::transaction(function () use ($actor, $type, $requesterId, $counterpartId, $items, $reason, $channel, $tenantId, $check) {
            $req = $this->createRow($tenantId, $type, 'direct', $requesterId, $counterpartId, ShiftRequest::STATUS_APPROVED, $reason, $actor, $channel, [
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ]);
            $this->saveItems($req, $items);
            $this->event($req, 'created_direct', $actor, $reason, ['warnings' => $check['warnings'], 'items' => $this->describeItems($items)], $channel);
            $this->apply($req, $actor, $type === 'swap' ? 'admin_swap' : 'admin_change', $channel);
            $this->audit($actor, $req, $type === 'swap' ? 'shift.swapped_direct' : 'shift.changed_direct');

            return $req;
        });

        $this->notify->changedDirectly($req);

        return $req;
    }

    // ==================================================================
    // Employee requests
    // ==================================================================

    public function createSwapRequest(User $requester, int $counterpartId, array $dates, ?string $reason, string $channel = 'web', ?User $raisedBy = null): ShiftRequest
    {
        $tenantId = (int) $requester->tenant_id;
        $settings = $this->settings($tenantId);
        if (! $settings->swap_enabled) {
            throw new ShiftRequestException('Shift swaps are switched off for your company.');
        }
        $counterpart = User::where('tenant_id', $tenantId)->where('status', 1)->find($counterpartId);
        if (! $counterpart || $counterpart->id === $requester->id) {
            throw new ShiftRequestException('Pick an active colleague to swap with.');
        }
        if ($e = $this->colleagueRuleError($requester, $counterpart, $settings)) {
            throw new ShiftRequestException($e);
        }
        $this->checkMonthlyLimit($requester, $settings);

        $items = $this->buildSwapItems($tenantId, (int) $requester->id, (int) $counterpart->id, $dates);
        $check = $this->check($tenantId, $items, 'request');
        if ($check['errors']) {
            throw new ShiftRequestException($check['errors'][0], $check['errors']);
        }

        $req = DB::transaction(function () use ($requester, $counterpart, $items, $reason, $channel, $raisedBy, $tenantId, $settings) {
            $firstStart = $this->firstShiftStart($items);
            $req = $this->createRow($tenantId, 'swap', 'request', (int) $requester->id, (int) $counterpart->id, ShiftRequest::STATUS_PENDING_PEER, $reason, $raisedBy ?? $requester, $channel, [
                'expires_at' => min($firstStart, now()->addHours($settings->peer_response_hours)),
            ]);
            $this->saveItems($req, $items);
            $this->event($req, $raisedBy ? 'created_on_behalf' : 'created', $raisedBy ?? $requester, $reason, ['items' => $this->describeItems($items)], $channel);
            $this->audit($raisedBy ?? $requester, $req, 'shift.swap_requested');

            return $req;
        });

        $this->notify->swapRequested($req);

        return $req;
    }

    public function createChangeRequest(User $requester, array $dates, int $toShiftId, ?string $reason, string $channel = 'web', ?User $raisedBy = null): ShiftRequest
    {
        $tenantId = (int) $requester->tenant_id;
        $settings = $this->settings($tenantId);
        if (! $settings->change_enabled) {
            throw new ShiftRequestException('Shift change requests are switched off for your company.');
        }
        $this->checkMonthlyLimit($requester, $settings);

        $items = $this->buildChangeItems($tenantId, (int) $requester->id, $dates, $toShiftId);
        $check = $this->check($tenantId, $items, 'request');
        if ($check['errors']) {
            throw new ShiftRequestException($check['errors'][0], $check['errors']);
        }

        $req = DB::transaction(function () use ($requester, $items, $reason, $channel, $raisedBy, $tenantId) {
            $req = $this->createRow($tenantId, 'change', 'request', (int) $requester->id, null, ShiftRequest::STATUS_PENDING_APPROVAL, $reason, $raisedBy ?? $requester, $channel, [
                'expires_at' => $this->firstShiftStart($items),
            ]);
            $this->saveItems($req, $items);
            $this->event($req, $raisedBy ? 'created_on_behalf' : 'created', $raisedBy ?? $requester, $reason, ['items' => $this->describeItems($items)], $channel);
            $this->audit($raisedBy ?? $requester, $req, 'shift.change_requested');
            $this->sendForApproval($req, $raisedBy ?? $requester, $channel);

            return $req;
        });

        return $req->refresh();
    }

    /** The colleague accepts or declines a swap. */
    public function respond(ShiftRequest $req, User $peer, bool $accept, ?string $remarks, string $channel = 'web'): ShiftRequest
    {
        if ($req->status !== ShiftRequest::STATUS_PENDING_PEER || (int) $req->counterpart_id !== (int) $peer->id) {
            throw new ShiftRequestException('This swap is not waiting for your answer.');
        }

        if (! $accept) {
            DB::transaction(function () use ($req, $peer, $remarks, $channel) {
                $req->update(['status' => ShiftRequest::STATUS_PEER_DECLINED, 'peer_remarks' => $remarks, 'peer_responded_at' => now()]);
                $this->event($req, 'peer_declined', $peer, $remarks, [], $channel);
            });
            $this->notify->peerResponded($req, false);

            return $req;
        }

        $items = $this->itemArrays($req);
        $check = $this->check((int) $req->tenant_id, $items, 'request', $req->id);
        if ($check['errors']) {
            throw new ShiftRequestException($check['errors'][0], $check['errors']);
        }

        DB::transaction(function () use ($req, $peer, $remarks, $channel, $items) {
            $req->update([
                'status' => ShiftRequest::STATUS_PENDING_APPROVAL,
                'peer_remarks' => $remarks,
                'peer_responded_at' => now(),
                'expires_at' => $this->firstShiftStart($items),
            ]);
            $this->event($req, 'peer_accepted', $peer, $remarks, [], $channel);
            $this->sendForApproval($req, $peer, $channel);
        });
        $this->notify->peerResponded($req->refresh(), true);

        return $req;
    }

    /** Approver decision (approved / rejected). */
    public function decide(ShiftRequest $req, User $actor, string $action, ?string $remarks, string $channel = 'web'): ShiftRequest
    {
        if (! in_array($action, ['approved', 'rejected'], true)) {
            throw new ShiftRequestException('Unknown decision.');
        }
        if ($req->status !== ShiftRequest::STATUS_PENDING_APPROVAL) {
            throw new ShiftRequestException('This request is not waiting for approval (it is ' . strtolower($req->statusLabel()) . ').');
        }
        if (in_array((int) $actor->id, $req->involvedUserIds(), true)) {
            throw new ShiftRequestException("You can't decide on your own shift request.");
        }

        $workflow = app(ApprovalService::class);
        $decidedHere = false;
        try {
            $result = DB::transaction(function () use ($workflow, $req, $actor, $action, $remarks, $channel, &$decidedHere) {
                $approval = $workflow->decide('shift_request', $req, $actor, $action, $remarks);
                if ($approval === null) {
                    // No workflow configured for the company: reporting head / HR / admin decide in one step.
                    if (! $this->canApprove($actor, $req)) {
                        throw new ShiftRequestException('Only the employee\'s reporting manager, HR or an admin can decide on this request.');
                    }
                    $action === 'approved'
                        ? $this->finalizeApproved($req, $actor, $remarks, $channel)
                        : $this->finalizeRejected($req, $actor, $remarks, $channel);
                    $decidedHere = true;

                    return $req;
                }
                if (! $req->approval_request_id) {
                    $req->update(['approval_request_id' => $approval->id]);
                }
                if ($approval->status === 'pending') {
                    $this->event($req, 'level_approved', $actor, $remarks, ['level' => $approval->current_level - 1], $channel);
                }

                return $req;
            });
        } catch (ShiftRequestException $e) {
            if ($e->stale) {
                $this->event($req, 'stale_blocked', $actor, $e->getMessage(), [], $channel);
            }
            throw $e;
        } catch (\RuntimeException $e) {
            throw new ShiftRequestException($e->getMessage());
        }

        // A finished workflow is announced by ShiftRequestApprovalHandler.
        if ($decidedHere) {
            $this->notify->decided($result);
        }

        return $result->refresh();
    }

    /** Called by the decide() fallback and by ShiftRequestApprovalHandler (workflow finished). */
    public function finalizeApproved(ShiftRequest $req, User $actor, ?string $remarks, string $channel = 'web'): void
    {
        $req->update([
            'status' => ShiftRequest::STATUS_APPROVED,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'approver_remarks' => $remarks,
        ]);
        $this->event($req, 'approved', $actor, $remarks, [], $channel);
        $this->apply($req, $actor, $req->type === 'swap' ? 'swap_request' : 'change_request', $channel);
        $this->audit($actor, $req, 'shift.request_approved');
    }

    public function finalizeRejected(ShiftRequest $req, User $actor, ?string $remarks, string $channel = 'web'): void
    {
        $req->update([
            'status' => ShiftRequest::STATUS_REJECTED,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'approver_remarks' => $remarks,
        ]);
        $this->event($req, 'rejected', $actor, $remarks, [], $channel);
        $this->audit($actor, $req, 'shift.request_rejected');
    }

    /** The requester (or whoever raised it, or HR / admin) withdraws a pending request. */
    public function cancel(ShiftRequest $req, User $actor, ?string $remarks, string $channel = 'web'): ShiftRequest
    {
        if (! $req->isPending()) {
            throw new ShiftRequestException('Only a pending request can be cancelled.');
        }
        $allowed = in_array((int) $actor->id, [(int) $req->requester_id, (int) $req->created_by], true) || in_array($actor->role, ['admin', 'hr'], true);
        if (! $allowed) {
            throw new ShiftRequestException('Only the employee who raised this request can cancel it.');
        }

        DB::transaction(function () use ($req, $actor, $remarks, $channel) {
            $req->update(['status' => ShiftRequest::STATUS_CANCELLED]);
            $this->closeWorkflow($req);
            $this->event($req, 'cancelled', $actor, $remarks, [], $channel);
            $this->audit($actor, $req, 'shift.request_cancelled');
        });
        $this->notify->cancelled($req);

        return $req;
    }

    /**
     * Undo an approved request / direct change (admin or HR): each day goes
     * back to what it was. Only while none of its days has started or has attendance.
     */
    public function revert(ShiftRequest $req, User $actor, string $reason, string $channel = 'web'): ShiftRequest
    {
        if (! in_array($actor->role, ['admin', 'hr'], true)) {
            throw new ShiftRequestException('Only HR or an admin can revert a shift change.');
        }
        if ($req->status !== ShiftRequest::STATUS_APPROVED) {
            throw new ShiftRequestException('Only an approved request can be reverted.');
        }
        $req->loadMissing('items');
        $today = Carbon::today()->toDateString();
        foreach ($req->items as $item) {
            $date = $item->date->toDateString();
            if ($date < $today) {
                throw new ShiftRequestException('This change includes a past day (' . $item->date->format('d M') . ') and can no longer be reverted.');
            }
            if ($e = $this->guard->dateError((int) $req->tenant_id, (int) $item->user_id, $date)) {
                throw new ShiftRequestException($e);
            }
        }

        DB::transaction(function () use ($req, $actor, $reason, $channel) {
            [$from, $to] = [$req->items->min('date')->toDateString(), $req->items->max('date')->toDateString()];
            $this->recorder->track((int) $req->tenant_id, $req->involvedUserIds(), $from, $to, [
                'source' => 'request_revert', 'change_type' => 'revert', 'reason' => $reason, 'shift_request_id' => $req->id, 'channel' => $this->logChannel($channel),
            ], function () use ($req, $actor, $reason) {
                foreach ($req->items as $item) {
                    $override = $item->applied_assignment_id ? ShiftAssignment::withoutGlobalScopes()->find($item->applied_assignment_id) : null;
                    if ($override && $override->status === 'active') {
                        $this->assignments->cancelDayOverride($override, (int) $actor->id, $reason, $item->previous_assignment_id ? null : $item->from_shift_id);
                    }
                }
            });
            $req->update(['status' => ShiftRequest::STATUS_REVERTED, 'reverted_by' => $actor->id, 'reverted_at' => now()]);
            $this->event($req, 'reverted', $actor, $reason, [], $channel);
            $this->audit($actor, $req, 'shift.request_reverted');
        });
        $this->notify->reverted($req);

        return $req;
    }

    /**
     * Scheduled (shift-requests:expire): a colleague who didn't answer in
     * time, or any pending request whose first shift has started → expired.
     */
    public function expireDue(): int
    {
        $count = 0;
        ShiftRequest::withoutGlobalScopes()
            ->whereIn('status', ShiftRequest::PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->each(function (ShiftRequest $req) use (&$count) {
                DB::transaction(function () use ($req) {
                    $wasWaitingForPeer = $req->status === ShiftRequest::STATUS_PENDING_PEER;
                    $req->update(['status' => ShiftRequest::STATUS_EXPIRED]);
                    $this->closeWorkflow($req);
                    $this->event($req, 'expired', null, $wasWaitingForPeer ? 'The colleague did not answer in time.' : 'Not decided before the shift started.', [], 'system');
                });
                $this->notify->expired($req);
                $count++;
            });

        return $count;
    }

    // ==================================================================
    // Internals
    // ==================================================================

    /** Re-check that nothing changed since the request was raised, then write the overrides. */
    private function apply(ShiftRequest $req, User $actor, string $logSource, string $channel): void
    {
        $req->loadMissing('items');
        $tenantId = (int) $req->tenant_id;
        $dates = $req->items->map(fn ($i) => $i->date->toDateString())->unique()->values()->all();
        $current = $this->primaryRows($tenantId, $req->involvedUserIds(), $dates, true);

        foreach ($req->items as $item) {
            $row = $current->get($item->user_id . '|' . $item->date->toDateString());
            if (($row ? (int) $row->shift_id : null) !== ($item->from_shift_id ? (int) $item->from_shift_id : null)) {
                throw new ShiftRequestException(
                    'The shifts on ' . $item->date->format('d M') . ' have changed since this request was raised — it can no longer be applied. Please raise a new request.',
                    [], true
                );
            }
        }

        $this->recorder->track($tenantId, $req->involvedUserIds(), min($dates), max($dates), [
            'source' => $logSource,
            'change_type' => $req->type === 'swap' ? 'swap' : 'request_change',
            'reason' => $req->reason,
            'shift_request_id' => $req->id,
            'channel' => $this->logChannel($channel),
        ], function () use ($req, $actor, $tenantId) {
            foreach ($req->items as $item) {
                $result = $this->assignments->applyDayOverride(
                    $tenantId,
                    (int) $item->user_id,
                    $item->date->copy(),
                    (int) $item->to_shift_id,
                    (int) $actor->id,
                    $req->type === 'swap' ? 'swap' : 'change_request',
                    $req->reason,
                    $req->id
                );
                $item->update([
                    'previous_assignment_id' => $result['previous_assignment_id'],
                    'applied_assignment_id' => $result['assignment']->id,
                ]);
            }
        });

        $this->event($req, 'applied', $actor, null, [], $channel);
    }

    private function sendForApproval(ShiftRequest $req, User $actor, string $channel): void
    {
        if (! $this->settings((int) $req->tenant_id)->requires_approval) {
            $system = $actor;
            $this->finalizeApproved($req, $system, 'Approved automatically — the company does not require approval.', $channel);
            $this->notify->decided($req);

            return;
        }

        $this->event($req, 'submitted_for_approval', $actor, null, [], $channel);
        $approval = app(ApprovalService::class)->open('shift_request', $req, User::find($req->requester_id) ?? $actor);
        if ($approval) {
            $req->update(['approval_request_id' => $approval->id]); // the workflow notifies its first level
        } else {
            $this->notify->awaitingApproval($req, $this->approverIds($req));
        }
    }

    private function closeWorkflow(ShiftRequest $req): void
    {
        if ($req->approval_request_id) {
            DB::table('approval_requests')->where('id', $req->approval_request_id)->where('status', 'pending')
                ->update(['status' => 'rejected', 'resolved_at' => now(), 'updated_at' => now()]);
        }
    }

    private function createRow(int $tenantId, string $type, string $mode, int $requesterId, ?int $counterpartId, string $status, ?string $reason, User $creator, string $channel, array $extra = []): ShiftRequest
    {
        $year = now()->format('Y');
        $prefix = "SR-{$year}-";
        $last = ShiftRequest::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('request_no', 'like', $prefix . '%')
            ->lockForUpdate()->orderByDesc('id')->value('request_no');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return ShiftRequest::create(array_merge([
            'tenant_id' => $tenantId,
            'request_no' => $prefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT),
            'type' => $type,
            'mode' => $mode,
            'requester_id' => $requesterId,
            'counterpart_id' => $counterpartId,
            'status' => $status,
            'reason' => $reason ? mb_substr($reason, 0, 500) : null,
            'created_by' => $creator->id,
            'channel' => $channel === 'mobile' ? 'mobile' : 'web',
        ], $extra));
    }

    private function saveItems(ShiftRequest $req, array $items): void
    {
        foreach ($items as $i) {
            ShiftRequestItem::create([
                'tenant_id' => $req->tenant_id,
                'shift_request_id' => $req->id,
                'user_id' => $i['user_id'],
                'date' => $i['date'],
                'from_shift_id' => $i['from_shift_id'],
                'to_shift_id' => $i['to_shift_id'],
            ]);
        }
    }

    private function itemArrays(ShiftRequest $req): array
    {
        $req->loadMissing('items');
        $rows = $this->primaryRows((int) $req->tenant_id, $req->involvedUserIds(), $req->items->map(fn ($i) => $i->date->toDateString())->unique()->all());

        return $req->items->map(fn ($i) => [
            'user_id' => (int) $i->user_id,
            'date' => $i->date->toDateString(),
            'from_shift_id' => $i->from_shift_id ? (int) $i->from_shift_id : null,
            'to_shift_id' => (int) $i->to_shift_id,
            'user_shift_id' => optional($rows->get($i->user_id . '|' . $i->date->toDateString()))->id,
        ])->all();
    }

    public function event(ShiftRequest $req, string $event, ?User $actor, ?string $remarks, array $meta = [], string $channel = 'web'): void
    {
        $request = app()->runningInConsole() ? null : request();
        ShiftRequestEvent::create([
            'tenant_id' => $req->tenant_id,
            'shift_request_id' => $req->id,
            'event' => $event,
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->role,
            'remarks' => $remarks ? mb_substr($remarks, 0, 500) : null,
            'meta' => $meta ?: null,
            'channel' => $this->logChannel($channel),
            'ip' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }

    private function audit(User $actor, ShiftRequest $req, string $action): void
    {
        try {
            app(AuditLogger::class)->record('tenant_user', (int) $actor->id, (int) $req->tenant_id, $action, 'shift_request', (int) $req->id, [], [
                'request_no' => $req->request_no,
                'type' => $req->type,
                'mode' => $req->mode,
                'status' => $req->status,
                'requester_id' => $req->requester_id,
                'counterpart_id' => $req->counterpart_id,
                'reason' => $req->reason,
            ]);
        } catch (\Throwable $e) {
            // audit trail failures never block the change (shift_request_events still has it)
        }
    }

    private function logChannel(string $channel): string
    {
        return in_array($channel, ['web', 'mobile', 'system'], true) ? $channel : 'web';
    }

    /** Main user_shifts rows keyed "user|Y-m-d". */
    private function primaryRows(int $tenantId, array $userIds, array $dates, bool $lock = false): Collection
    {
        if (! $userIds || ! $dates) {
            return collect();
        }

        return UserShift::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('user_id', $userIds)
            ->whereIn('date', $dates)
            ->where('is_additional', 0)
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->get(['id', 'user_id', 'date', 'shift_id', 'shift_assignment_id'])
            ->keyBy(fn ($r) => $r->user_id . '|' . substr((string) $r->date, 0, 10));
    }

    /** Name of an additional (2nd+) shift the new main shift would overlap, else null. */
    private function additionalClash(int $tenantId, int $userId, Shift $shift, string $date): ?string
    {
        $primaryIds = UserShift::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->whereBetween('date', [Carbon::parse($date)->subDay()->toDateString(), Carbon::parse($date)->addDay()->toDateString()])
            ->where('is_additional', 0)->pluck('id')->all();
        $conflicts = $this->overlap->conflicts($tenantId, $userId, $shift, [$date], $primaryIds);

        return $conflicts[$date] ?? null;
    }

    /**
     * Company Policies → Working-time thresholds, checked for every changed day
     * (the policy in force on that day):
     *  - minimum rest between this day's main shift and the main shifts the day
     *    before / after (as they will be once the request is applied);
     *  - maximum hours scheduled on the day (main + additional shifts).
     * An overlap is always flagged.
     */
    private function restIssues(int $tenantId, array $items): array
    {
        $issues = [];
        $policies = app(\App\Services\Attendance\PolicyResolver::class);
        $byUser = collect($items)->groupBy('user_id');
        foreach ($byUser as $userId => $userItems) {
            $dates = $userItems->pluck('date')->all();
            $around = collect($dates)->flatMap(fn ($d) => [Carbon::parse($d)->subDay()->toDateString(), $d, Carbon::parse($d)->addDay()->toDateString()])->unique()->values()->all();
            $final = $this->primaryRows($tenantId, [(int) $userId], $around)->mapWithKeys(fn ($r) => [substr((string) $r->date, 0, 10) => (int) $r->shift_id])->all();
            foreach ($userItems as $i) {
                $final[$i['date']] = (int) $i['to_shift_id'];
            }
            $shifts = Shift::withoutGlobalScopes()->whereIn('id', array_unique(array_values($final)))->get()->keyBy('id');
            $name = User::find($userId)?->name ?? 'The employee';

            foreach ($dates as $d) {
                $limits = $policies->forUserDate($tenantId, (int) $userId, $d);
                $minRestHours = (float) ($limits->minRestHours ?? 0);

                // Maximum scheduled hours that day: the new main shift + any additional shifts.
                if (($maxDaily = (float) ($limits->maxDailyHours ?? 0)) > 0 && ($main = $shifts->get($final[$d] ?? 0))) {
                    $extraIds = UserShift::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $userId)
                        ->where('date', $d)->where('is_additional', 1)->pluck('shift_id');
                    $hours = ShiftWindow::workingHours($main->start_time, $main->end_time, $main->break_time, (bool) $main->is_overnight)
                        + Shift::withoutGlobalScopes()->whereIn('id', $extraIds)->get()
                            ->sum(fn ($s) => ShiftWindow::workingHours($s->start_time, $s->end_time, $s->break_time, (bool) $s->is_overnight));
                    if ($hours > $maxDaily) {
                        $issues[] = ['overlap' => false, 'message' => "{$name} would be scheduled " . rtrim(rtrim(number_format($hours, 1), '0'), '.') . " h on " . Carbon::parse($d)->format('d M') . " (maximum {$maxDaily} h a day)."];
                    }
                }

                foreach ([[Carbon::parse($d)->subDay()->toDateString(), $d], [$d, Carbon::parse($d)->addDay()->toDateString()]] as [$prev, $next]) {
                    $a = $shifts->get($final[$prev] ?? 0);
                    $b = $shifts->get($final[$next] ?? 0);
                    if (! $a || ! $b) {
                        continue;
                    }
                    $prevEnd = ShiftWindow::window($prev, $a)[1];
                    $nextStart = ShiftWindow::window($next, $b)[0];
                    $gap = $prevEnd->diffInMinutes($nextStart, false) / 60;
                    if ($gap < 0) {
                        $issues[] = ['overlap' => true, 'message' => "{$name}: {$a->name} on " . Carbon::parse($prev)->format('d M') . " would overlap {$b->name} on " . Carbon::parse($next)->format('d M') . '.'];
                    } elseif ($minRestHours > 0 && $gap < $minRestHours) {
                        $issues[] = ['overlap' => false, 'message' => "{$name} would get only " . rtrim(rtrim(number_format($gap, 1), '0'), '.') . " h rest between {$a->name} on " . Carbon::parse($prev)->format('d M') . " and {$b->name} on " . Carbon::parse($next)->format('d M') . " (minimum {$minRestHours} h)."];
                    }
                }
            }
        }

        return $issues;
    }

    private function colleagueRuleError(User $a, User $b, ShiftRequestSetting $settings): ?string
    {
        $jobs = DB::table('user_job_details')->whereIn('user_id', [$a->id, $b->id])->get(['user_id', 'department', 'branch_id'])->keyBy('user_id');
        $ja = $jobs->get($a->id);
        $jb = $jobs->get($b->id);
        if ($settings->same_department_only && (string) ($ja->department ?? '') !== (string) ($jb->department ?? '')) {
            return 'You can only swap with a colleague from your own department.';
        }
        if ($settings->same_branch_only && (string) ($ja->branch_id ?? '') !== (string) ($jb->branch_id ?? '')) {
            return 'You can only swap with a colleague from your own branch.';
        }

        return null;
    }

    private function checkMonthlyLimit(User $requester, ShiftRequestSetting $settings): void
    {
        if ($settings->max_requests_per_month <= 0) {
            return;
        }
        $used = ShiftRequest::withoutGlobalScopes()->where('tenant_id', $requester->tenant_id)
            ->where('requester_id', $requester->id)->where('mode', 'request')
            ->whereNotIn('status', [ShiftRequest::STATUS_CANCELLED])
            ->where('created_at', '>=', now()->startOfMonth())->count();
        if ($used >= $settings->max_requests_per_month) {
            throw new ShiftRequestException("You have reached this month's limit of {$settings->max_requests_per_month} shift requests.");
        }
    }

    /** Start of the earliest shift a request touches (its decision deadline). */
    private function firstShiftStart(array $items): Carbon
    {
        $shifts = Shift::withoutGlobalScopes()->whereIn('id', array_filter(array_merge(array_column($items, 'from_shift_id'), array_column($items, 'to_shift_id'))))->get()->keyBy('id');

        return collect($items)->flatMap(fn ($i) => collect([$i['from_shift_id'], $i['to_shift_id']])->filter()->map(fn ($id) => $shifts->get($id) ? ShiftWindow::window($i['date'], $shifts[$id])[0] : Carbon::parse($i['date'])))->min();
    }
}
