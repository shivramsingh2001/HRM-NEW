<?php

namespace App\Services\Attendance;

use App\Models\User;

/**
 * @deprecated Use {@see AttendanceEntryService::markStatus()} directly.
 *
 * Kept as a thin shim so existing callers / DI type-hints keep working while the
 * attendance write funnel is `AttendanceEntryService`.
 */
class ManualAttendanceService
{
    public function __construct(private AttendanceEntryService $entry)
    {
    }

    /**
     * @param array{
     *   user_id:int, tenant_id:int, date:string, status:AttendanceStatus,
     *   clock_in?:?string, clock_out?:?string, remarks?:?string, leave_type_id?:?int,
     *   end_date?:?string
     * } $input
     * @return array{attendance:\App\Models\Attendance, leave:?\App\Models\Leave, is_update:bool, shift:?object, marked:int}
     */
    public function mark(array $input, User $actor): array
    {
        return $this->entry->markStatus($input, $actor);
    }
}
