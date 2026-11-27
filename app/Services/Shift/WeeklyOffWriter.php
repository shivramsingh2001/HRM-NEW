<?php

namespace App\Services\Shift;

use App\Models\UserWeekoffs;

/**
 * Saves a recurring (day_based) week-off from the Assign Shift form.
 *
 * `user_weekoffs` has the unique key `unique_weekoff` (user_id, off_type,
 * day_name, start_date) — end_date is NOT part of it. So "same employee, same
 * day, same start date" is the same row: when it already exists (even with a
 * different end date, or switched off by a rotation) it is updated instead of
 * a second insert that the database would refuse with a 1062 duplicate error.
 */
class WeeklyOffWriter
{
    /** @return 'created'|'updated'|'unchanged' */
    public function save(int $userId, int $tenantId, string $day, ?string $startDate, ?string $endDate, int $createdBy): string
    {
        $existing = UserWeekoffs::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('off_type', 'day_based')
            ->where('day_name', $day)
            ->where('start_date', $startDate)   // null → IS NULL
            ->first();

        if (! $existing) {
            UserWeekoffs::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'off_type' => 'day_based',
                'day_name' => $day,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 1,
                'created_by' => $createdBy,
            ]);

            return 'created';
        }

        $endNow = $existing->getAttribute('end_date') ? substr((string) $existing->getAttribute('end_date'), 0, 10) : null;
        if ($endNow === $endDate && (int) $existing->getAttribute('status') === 1) {
            return 'unchanged';
        }

        $existing->update(['end_date' => $endDate, 'status' => 1]);

        return 'updated';
    }
}
