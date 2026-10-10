<?php

namespace App\Services\Shift;

use App\Models\Shift;
use App\Models\ShiftRequest;
use App\Models\ShiftRequestSetting;
use App\Models\User;
use App\Notifications\ShiftNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Push + in-app notifications for shift requests and roster changes. Every
 * send runs after the transaction commits and never throws — a failed
 * notification must not undo a shift change.
 */
class ShiftNotificationService
{
    /** Colleague is asked to accept a swap. */
    public function swapRequested(ShiftRequest $req): void
    {
        $this->later(function () use ($req) {
            $req->loadMissing('requester', 'counterpart', 'items');
            $mine = $req->items->where('user_id', $req->counterpart_id);
            $this->send([$req->counterpart_id], '🔁 Shift Swap Request',
                "{$req->requester->name} wants to swap shifts with you {$this->datesText($req->items->pluck('date')->unique())}: you would work {$this->shiftsText($mine, 'to_shift_id')} instead of {$this->shiftsText($mine, 'from_shift_id')}. Please accept or decline.",
                $req, 'swap_requested');
        });
    }

    public function peerResponded(ShiftRequest $req, bool $accepted): void
    {
        $this->later(function () use ($req, $accepted) {
            $req->loadMissing('counterpart');
            $this->send([$req->requester_id],
                $accepted ? '🤝 Swap Accepted' : '🚫 Swap Declined',
                $accepted
                    ? "{$req->counterpart->name} accepted your shift swap ({$req->request_no})" . ($req->status === ShiftRequest::STATUS_PENDING_APPROVAL ? ' — it is now waiting for approval.' : '.')
                    : "{$req->counterpart->name} declined your shift swap ({$req->request_no})" . ($req->peer_remarks ? ": {$req->peer_remarks}" : '.'),
                $req, $accepted ? 'swap_accepted' : 'swap_declined');
        });
    }

    /** Approvers are told a request waits for them (skipped when an approval workflow already notified its level). */
    public function awaitingApproval(ShiftRequest $req, array $approverIds): void
    {
        $this->later(function () use ($req, $approverIds) {
            $req->loadMissing('requester', 'counterpart', 'items');
            $what = $req->type === 'swap'
                ? "{$req->requester->name} and {$req->counterpart->name} want to swap shifts"
                : "{$req->requester->name} asks to change to {$this->shiftsText($req->items, 'to_shift_id')}";
            $this->send($approverIds, '🕒 Shift Request Needs Approval',
                "{$what} {$this->datesText($req->items->pluck('date')->unique())} ({$req->request_no}).",
                $req, 'approval_pending');
        });
    }

    public function decided(ShiftRequest $req): void
    {
        $this->later(function () use ($req) {
            $approved = $req->status === ShiftRequest::STATUS_APPROVED;
            $req->loadMissing('items');
            foreach ($req->involvedUserIds() as $uid) {
                $mine = $req->items->where('user_id', $uid);
                $this->send([$uid],
                    $approved ? '✅ Shift Request Approved' : '❌ Shift Request Rejected',
                    $approved
                        ? "Your {$this->typeText($req)} ({$req->request_no}) is approved. Your shift {$this->datesText($mine->pluck('date'))}: {$this->shiftsText($mine, 'to_shift_id')}."
                        : "Your {$this->typeText($req)} ({$req->request_no}) was rejected" . ($req->approver_remarks ? ": {$req->approver_remarks}" : '.'),
                    $req, $approved ? 'request_approved' : 'request_rejected');
            }
        });
    }

    /** Admin / HR / manager swapped or changed shifts directly. */
    public function changedDirectly(ShiftRequest $req): void
    {
        $this->later(function () use ($req) {
            $req->loadMissing('items', 'creator');
            foreach ($req->involvedUserIds() as $uid) {
                if ($uid === (int) $req->created_by) {
                    continue;
                }
                $mine = $req->items->where('user_id', $uid);
                $this->send([$uid], '🔄 Your Shift Changed',
                    ($req->creator->name ?? 'HR') . ($req->type === 'swap' ? ' swapped your shift' : ' changed your shift')
                        . " {$this->datesText($mine->pluck('date'))}: you now work {$this->shiftsText($mine, 'to_shift_id')} (was {$this->shiftsText($mine, 'from_shift_id')})."
                        . ($req->reason ? " Reason: {$req->reason}" : ''),
                    $req, 'shift_changed');
            }
        });
    }

    public function expired(ShiftRequest $req): void
    {
        $this->later(function () use ($req) {
            $this->send($req->involvedUserIds(), '⌛ Shift Request Expired',
                "The {$this->typeText($req)} {$req->request_no} expired before it was completed. Your shifts are unchanged.",
                $req, 'request_expired');
        });
    }

    public function cancelled(ShiftRequest $req): void
    {
        $this->later(function () use ($req) {
            $others = array_values(array_diff($req->involvedUserIds(), [(int) $req->requester_id]));
            if ($others) {
                $req->loadMissing('requester');
                $this->send($others, '🚫 Shift Swap Cancelled', "{$req->requester->name} cancelled the shift swap {$req->request_no}.", $req, 'request_cancelled');
            }
        });
    }

    public function reverted(ShiftRequest $req): void
    {
        $this->later(function () use ($req) {
            $req->loadMissing('items');
            foreach ($req->involvedUserIds() as $uid) {
                $mine = $req->items->where('user_id', $uid);
                $this->send([$uid], '↩️ Shift Change Reverted',
                    "The {$this->typeText($req)} {$req->request_no} was reverted. Your shift {$this->datesText($mine->pluck('date'))} is back to {$this->shiftsText($mine, 'from_shift_id')}.",
                    $req, 'request_reverted');
            }
        });
    }

    /**
     * Roster assign / edit / delete by admin, HR or a manager: one message per
     * employee per action ("Your shift changes on 12–18 Oct: Night"), today
     * and future days only, main shifts only. $rows = ShiftChangeRecorder::$lastRows.
     */
    public function rosterChanged(int $tenantId, array $rows, ?int $actorId): void
    {
        if (! $rows || ! ShiftRequestSetting::forTenant($tenantId)->notify_on_roster_change) {
            return;
        }

        $today = Carbon::today()->toDateString();
        $byUser = collect($rows)->filter(fn ($r) => ! $r['is_additional'] && $r['date'] >= $today && (int) $r['user_id'] !== (int) $actorId)->groupBy('user_id');
        if ($byUser->isEmpty()) {
            return;
        }

        $this->later(function () use ($byUser) {
            $names = Shift::withoutGlobalScopes()->whereIn('id', $byUser->flatten(1)->pluck('to_shift_id')->filter()->unique())->pluck('name', 'id');
            foreach ($byUser as $userId => $userRows) {
                $parts = $userRows->groupBy(fn ($r) => (string) $r['to_shift_id'])->map(function ($g, $shiftId) use ($names) {
                    return ($shiftId === '' ? 'no shift' : ($names[(int) $shiftId] ?? 'a new shift')) . ' ' . $this->datesText($g->pluck('date'));
                })->values()->all();
                $this->send([(int) $userId], '🗓️ Your Shift Schedule Changed', 'Your shift: ' . implode('; ', $parts) . '.', null, 'roster_changed');
            }
        });
    }

    // ------------------------------------------------------------------

    /** "on 12 Oct" / "on 12–14 Oct (3 days)" / "on 3 days (12 Oct – 2 Nov)". */
    public function datesText($dates): string
    {
        $list = collect($dates)->map(fn ($d) => Carbon::parse($d)->startOfDay())->unique(fn ($d) => $d->toDateString())->sort()->values();
        if ($list->isEmpty()) {
            return '';
        }
        if ($list->count() === 1) {
            return 'on ' . $list[0]->format('d M');
        }
        $first = $list->first();
        $last = $list->last();
        $consecutive = (int) $first->diffInDays($last) === $list->count() - 1;

        return $consecutive
            ? 'on ' . $first->format('d') . '–' . $last->format('d M') . ' (' . $list->count() . ' days)'
            : 'on ' . $list->count() . ' days (' . $first->format('d M') . ' – ' . $last->format('d M') . ')';
    }

    private function shiftsText($items, string $column): string
    {
        $ids = collect($items)->pluck($column)->filter()->unique();
        if ($ids->isEmpty()) {
            return 'no shift';
        }

        return Shift::withoutGlobalScopes()->whereIn('id', $ids)->get()
            ->map(fn ($s) => $s->name . ' (' . Carbon::parse($s->start_time)->format('h:i A') . '–' . Carbon::parse($s->end_time)->format('h:i A') . ')')
            ->implode(' / ');
    }

    private function typeText(ShiftRequest $req): string
    {
        return $req->type === 'swap' ? 'shift swap' : 'shift change request';
    }

    private function send(array $userIds, string $title, string $message, ?ShiftRequest $req, string $event): void
    {
        $users = User::withoutGlobalScopes()->whereIn('id', array_unique(array_map('intval', $userIds)))->where('status', 1)->get();
        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new ShiftNotification($title, $message, [
            'event' => $event,
            'shift_request_id' => $req?->id,
            'request_no' => $req?->request_no,
        ]));
    }

    private function later(callable $fn): void
    {
        DB::afterCommit(function () use ($fn) {
            try {
                $fn();
            } catch (\Throwable $e) {
                Log::warning('Shift notification failed: ' . $e->getMessage());
            }
        });
    }
}
