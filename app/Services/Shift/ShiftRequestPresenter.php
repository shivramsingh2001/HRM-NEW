<?php

namespace App\Services\Shift;

use App\Models\ShiftRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who may see which shift requests, and the JSON shape of a request — shared
 * by the web page (Shift\ShiftRequestController) and the mobile API
 * (Api\Shift\ShiftRequestController) so both always agree.
 */
class ShiftRequestPresenter
{
    public function __construct(private ShiftRequestService $service)
    {
    }

    /** Admin / HR: every request; manager: requests involving their reportees; others: none beyond their own. */
    public function visibleQuery(User $user): Builder
    {
        $query = ShiftRequest::query();
        if (in_array($user->role, ['admin', 'hr'], true)) {
            return $query;
        }
        if ($user->role === 'manager') {
            $team = User::managedBy($user->id)->pluck('id');

            return $query->where(fn ($q) => $q->whereIn('requester_id', $team)->orWhereIn('counterpart_id', $team));
        }

        return $query->whereRaw('1 = 0');
    }

    /** Requests waiting for this approver (never their own). */
    public function approvalQuery(User $user): Builder
    {
        return $this->visibleQuery($user)->where('status', ShiftRequest::STATUS_PENDING_APPROVAL)
            ->where('requester_id', '!=', $user->id)
            ->where(fn ($q) => $q->whereNull('counterpart_id')->orWhere('counterpart_id', '!=', $user->id));
    }

    /** Own (raised or asked of me) requests. */
    public function ownQuery(User $user): Builder
    {
        return ShiftRequest::where(fn ($q) => $q->where('requester_id', $user->id)->orWhere('counterpart_id', $user->id));
    }

    /** A request the user may open: involved, raised it, or an approver in scope — else 404. */
    public function find($id, User $user): ShiftRequest
    {
        $req = ShiftRequest::with('items')->findOrFail($id);
        $visible = in_array((int) $user->id, array_merge($req->involvedUserIds(), [(int) $req->created_by]), true)
            || $this->visibleQuery($user)->whereKey($req->id)->exists();
        abort_unless($visible, 404, 'Shift request not found.');

        return $req;
    }

    public function row(ShiftRequest $r, User $viewer): array
    {
        $r->loadMissing(['requester:id,name,employee_id', 'counterpart:id,name,employee_id', 'creator:id,name', 'items.fromShift:id,name,color_code,start_time,end_time', 'items.toShift:id,name,color_code,start_time,end_time']);
        $dates = $r->items->pluck('date')->unique(fn ($d) => $d->toDateString())->sortBy(fn ($d) => $d->timestamp)->values();
        $mine = $r->items->where('user_id', $r->requester_id);
        $shift = fn ($s) => $s ? [
            'id' => $s->id,
            'name' => $s->name,
            'color_code' => $s->color_code ?: '#4f46e5',
            'time' => Carbon::parse($s->start_time)->format('h:i A') . ' – ' . Carbon::parse($s->end_time)->format('h:i A'),
        ] : null;

        return [
            'id' => $r->id,
            'request_no' => $r->request_no,
            'type' => $r->type,
            'type_label' => $r->typeLabel(),
            'mode' => $r->mode,
            'status' => $r->status,
            'status_label' => $r->statusLabel(),
            'status_badge' => $r->statusBadge(),
            'requester' => $r->requester?->only(['id', 'name', 'employee_id']),
            'counterpart' => $r->counterpart?->only(['id', 'name', 'employee_id']),
            'created_by' => $r->creator?->name,
            'on_behalf' => (int) $r->created_by !== (int) $r->requester_id && $r->mode === 'request',
            'reason' => $r->reason,
            'dates_label' => $dates->isEmpty() ? '—' : ($dates->count() === 1 ? $dates[0]->format('d M Y') : $dates->first()->format('d M') . ' – ' . $dates->last()->format('d M Y') . ' (' . $dates->count() . ' days)'),
            'start_date' => $dates->first()?->toDateString(),
            'end_date' => $dates->last()?->toDateString(),
            'from_shift' => $shift($mine->first()?->fromShift),
            'to_shift' => $shift($mine->first()?->toShift),
            'created_at' => $r->created_at?->format('d M Y, h:i A'),
            'expires_at' => $r->isPending() ? $r->expires_at?->format('d M Y, h:i A') : null,
            'can' => [
                'respond' => $r->status === ShiftRequest::STATUS_PENDING_PEER && (int) $r->counterpart_id === (int) $viewer->id,
                'decide' => $r->status === ShiftRequest::STATUS_PENDING_APPROVAL && ! in_array((int) $viewer->id, $r->involvedUserIds(), true)
                    && ($this->service->canApprove($viewer, $r) || (bool) $r->approval_request_id),
                'cancel' => $r->isPending() && (in_array((int) $viewer->id, [(int) $r->requester_id, (int) $r->created_by], true) || in_array($viewer->role, ['admin', 'hr'], true)),
                'revert' => $r->status === ShiftRequest::STATUS_APPROVED && in_array($viewer->role, ['admin', 'hr'], true)
                    && $dates->first()?->gte(Carbon::today()),
            ],
        ];
    }

    /** row() + every day's before / after + the full timeline. */
    public function detail(ShiftRequest $req, User $viewer): array
    {
        $req->loadMissing(['decider:id,name', 'events.actor:id,name,role']);

        return $this->row($req, $viewer) + [
            'items' => $this->service->describeItems($req->items->map(fn ($i) => [
                'user_id' => $i->user_id, 'date' => $i->date->toDateString(), 'from_shift_id' => $i->from_shift_id, 'to_shift_id' => $i->to_shift_id,
            ])->all()),
            'peer_remarks' => $req->peer_remarks,
            'approver_remarks' => $req->approver_remarks,
            'decided_by' => $req->decider?->name,
            'decided_at' => $req->decided_at?->format('d M Y, h:i A'),
            'channel' => $req->channel,
            'events' => $req->events->map(fn ($e) => [
                'event' => $e->event,
                'label' => $e->label(),
                'actor' => $e->actor?->name ?? 'System',
                'actor_role' => $e->actor_role,
                'remarks' => $e->remarks,
                'channel' => $e->channel,
                'ip' => $e->ip,
                'at' => $e->created_at?->format('d M Y, h:i A'),
            ])->all(),
        ];
    }
}
