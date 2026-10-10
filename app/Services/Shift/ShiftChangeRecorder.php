<?php

namespace App\Services\Shift;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Writes `shift_change_logs` — the who / when / why / before → after trail of
 * every change to an employee's shift on a day.
 *
 * It works on the NET effect: snapshot the `user_shifts` rows of the affected
 * employees and dates, run the write, snapshot again and log only the days
 * whose shift actually changed. That way it covers every write path the same
 * way (model saves, query-builder deletes, the materializer's rebuilds) and a
 * delete-then-recreate of an unchanged day logs nothing.
 *
 *   $recorder->track($tenantId, $userIds, $from, $to, ['source' => 'roster_edit', 'reason' => …], fn () => …write…);
 *
 * Context keys: source (required), change_type (optional; derived per day as
 * assign / change / remove when absent), reason, shift_request_id, actor_id,
 * actor_role, channel (web / mobile / system).
 */
class ShiftChangeRecorder
{
    /** Rows logged by this instance so far (used to send one roster-change notification per employee). */
    public array $lastRows = [];

    /**
     * Run $write and log what it changed for $userIds between $from and $to
     * (inclusive, Y-m-d). Returns whatever $write returns.
     */
    public function track(int $tenantId, array $userIds, string $from, string $to, array $context, callable $write)
    {
        $before = $this->snapshot($tenantId, $userIds, $from, $to);
        $result = $write();
        $this->record($tenantId, $before, $this->snapshot($tenantId, $userIds, $from, $to), $context);

        return $result;
    }

    /**
     * [user|date => ['primary' => [shift_id, assignment_id] | null, 'extra' => [shift_id, …]]]
     */
    public function snapshot(int $tenantId, array $userIds, string $from, string $to): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if ($userIds === [] || $from > $to) {
            return [];
        }

        $map = [];
        foreach (array_chunk($userIds, 500) as $chunk) {
            DB::table('user_shifts')
                ->where('tenant_id', $tenantId)
                ->whereIn('user_id', $chunk)
                ->whereBetween('date', [$from, $to])
                ->orderBy('id')
                ->get(['user_id', 'date', 'shift_id', 'shift_assignment_id', 'is_additional'])
                ->each(function ($r) use (&$map) {
                    $key = $r->user_id . '|' . substr((string) $r->date, 0, 10);
                    $map[$key] ??= ['primary' => null, 'extra' => []];
                    if ($r->is_additional) {
                        $map[$key]['extra'][] = (int) $r->shift_id;
                    } else {
                        $map[$key]['primary'] = [(int) $r->shift_id, $r->shift_assignment_id ? (int) $r->shift_assignment_id : null];
                    }
                });
        }

        return $map;
    }

    /** Diff two snapshots and insert one log row per changed shift. Returns the number of rows logged. */
    public function record(int $tenantId, array $before, array $after, array $context): int
    {
        $base = $this->baseRow($tenantId, $context);
        $rows = [];

        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            [$userId, $date] = explode('|', $key);
            $old = $before[$key] ?? ['primary' => null, 'extra' => []];
            $new = $after[$key] ?? ['primary' => null, 'extra' => []];

            $oldShift = $old['primary'][0] ?? null;
            $newShift = $new['primary'][0] ?? null;
            if ($oldShift !== $newShift) {
                $rows[] = $base + [
                    'user_id' => (int) $userId,
                    'date' => $date,
                    'is_additional' => 0,
                    'from_shift_id' => $oldShift,
                    'to_shift_id' => $newShift,
                    'change_type' => $context['change_type'] ?? $this->derivedType($oldShift, $newShift),
                    'shift_assignment_id' => $new['primary'][1] ?? ($old['primary'][1] ?? null),
                ];
            }

            // Additional (2nd+) shifts: log what was added and what was removed.
            $added = $this->multisetDiff($new['extra'], $old['extra']);
            $removed = $this->multisetDiff($old['extra'], $new['extra']);
            foreach ($added as $shiftId) {
                $rows[] = $base + ['user_id' => (int) $userId, 'date' => $date, 'is_additional' => 1, 'from_shift_id' => null, 'to_shift_id' => $shiftId, 'change_type' => 'assign', 'shift_assignment_id' => null];
            }
            foreach ($removed as $shiftId) {
                $rows[] = $base + ['user_id' => (int) $userId, 'date' => $date, 'is_additional' => 1, 'from_shift_id' => $shiftId, 'to_shift_id' => null, 'change_type' => 'remove', 'shift_assignment_id' => null];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('shift_change_logs')->insert($chunk);
        }
        $this->lastRows = array_merge($this->lastRows, $rows);

        return count($rows);
    }

    private function derivedType(?int $from, ?int $to): string
    {
        if ($from === null) {
            return 'assign';
        }

        return $to === null ? 'remove' : 'change';
    }

    private function multisetDiff(array $a, array $b): array
    {
        $out = [];
        $counts = array_count_values($b);
        foreach ($a as $v) {
            if (! empty($counts[$v])) {
                $counts[$v]--;
            } else {
                $out[] = $v;
            }
        }

        return $out;
    }

    private function baseRow(int $tenantId, array $context): array
    {
        $actor = Auth::user();
        $request = app()->runningInConsole() ? null : request();

        $channel = $context['channel'] ?? match (true) {
            $request === null => 'system',
            $request->is('api/*') => 'mobile',
            default => 'web',
        };

        return [
            'tenant_id' => $tenantId,
            'source' => $context['source'] ?? 'unknown',
            'shift_request_id' => $context['shift_request_id'] ?? null,
            'actor_id' => $context['actor_id'] ?? $actor?->id,
            'actor_role' => $context['actor_role'] ?? $actor?->role,
            'reason' => isset($context['reason']) ? mb_substr((string) $context['reason'], 0, 500) : null,
            'channel' => $channel,
            'ip' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            'created_at' => now(),
        ];
    }
}
