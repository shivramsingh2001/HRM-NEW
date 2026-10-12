<?php

namespace App\Http\Requests\Team;

use App\Enums\AttendanceStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Team Members → select several employees → mark the same status for all of them
 * (team.mark-attendance-bulk). Same statuses and rules as the one-employee
 * MarkAttendanceRequest, except that the "clock in only" / "clock out only"
 * shortcuts are not offered (they depend on each employee's own open punch).
 * Present can take each employee's own shift times instead of fixed times.
 */
class BulkMarkAttendanceRequest extends FormRequest
{
    public const MAX_EMPLOYEES = 200;

    public const MAX_DAYS = 31;

    public function authorize(): bool
    {
        $actor = $this->user();
        if (! $actor || ! in_array($actor->role, ['admin', 'hr', 'manager'], true)) {
            return false;
        }

        // A manager may only mark their own reportees — every selected one.
        if ($actor->role === 'manager') {
            $ids = array_unique(array_map('intval', (array) $this->input('user_ids', [])));

            return $ids !== [] && User::managedBy($actor->id)
                ->where('tenant_id', $actor->tenant_id)
                ->whereIn('id', $ids)
                ->count() === count($ids);
        }

        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;
        $needsTimes = fn () => in_array($this->input('status'), ['present', 'half_day'], true) && ! $this->usesShiftTimes();

        return [
            'user_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_EMPLOYEES],
            'user_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'end_date' => ['nullable', 'date', 'after_or_equal:date', 'before_or_equal:today'],
            'status' => ['required', Rule::in(AttendanceStatus::markable())],
            // Present only: each employee's own shift start / end for that day.
            'use_shift_times' => ['nullable', 'boolean'],
            'clock_in' => ['nullable', 'date_format:H:i', Rule::requiredIf($needsTimes)],
            'clock_out' => ['nullable', 'date_format:H:i', Rule::requiredIf($needsTimes)],
            'clock_out_next_day' => ['nullable', 'boolean'],
            'leave_type_id' => [
                'nullable',
                'required_if:status,on_leave,first_half_leave,second_half_leave',
                Rule::exists('leave_types', 'id')->where('tenant_id', $tenantId),
            ],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_ids.required' => 'Select at least one employee.',
            'user_ids.max' => 'You can mark at most '.self::MAX_EMPLOYEES.' employees at a time.',
            'clock_in.required' => 'Clock in time is required (or tick "Use each employee\'s shift timings").',
            'clock_out.required' => 'Clock out time is required (or tick "Use each employee\'s shift timings").',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $start = Carbon::parse($this->input('date'));
            $end = $this->filled('end_date') ? Carbon::parse($this->input('end_date')) : $start;
            if ($start->diffInDays($end) + 1 > self::MAX_DAYS) {
                $validator->errors()->add('end_date', 'Mark at most '.self::MAX_DAYS.' days at a time.');
            }

            if ($this->usesShiftTimes() && $this->input('status') !== AttendanceStatus::Present->value) {
                $validator->errors()->add('use_shift_times', 'Shift timings can only be used for Present.');
            }

            if (! $this->usesShiftTimes() && $this->input('status') === AttendanceStatus::Present->value
                && $this->filled('clock_out') && $this->boolean('clock_out_next_day')
                && Carbon::parse($end->copy()->addDay()->toDateString().' '.$this->input('clock_out'))->gt(now())) {
                $validator->errors()->add('clock_out', 'The next-day clock-out time cannot be in the future.');
            }
        });
    }

    public function usesShiftTimes(): bool
    {
        return $this->boolean('use_shift_times');
    }

    /** @return list<int> */
    public function userIds(): array
    {
        return array_values(array_unique(array_map('intval', (array) $this->input('user_ids'))));
    }

    public function attendanceStatus(): AttendanceStatus
    {
        return AttendanceStatus::from($this->input('status'));
    }
}
