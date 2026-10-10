<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use App\Models\User;
use App\Services\Attendance\AttendanceEntryService;
use App\Services\Attendance\TenantShiftResolver;
use App\Support\ShiftWindow;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        if (!$actor || !in_array($actor->role, ['admin', 'hr', 'manager'], true)) {
            return false;
        }

        // A manager may only mark their own reportees (any reporting head).
        if ($actor->role === 'manager') {
            return User::managedBy($actor->id)
                ->where('id', $this->input('user_id'))
                ->where('tenant_id', $actor->tenant_id)
                ->exists();
        }

        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'end_date' => ['nullable', 'date', 'after_or_equal:date', 'before_or_equal:today'],
            'status' => ['required', Rule::in(AttendanceStatus::markable())],
            // Clock-out only keeps the employee's own clock-in, so none is sent.
            'clock_in' => ['nullable', 'date_format:H:i', Rule::requiredIf(
                fn () => in_array($this->input('status'), ['present', 'half_day'], true) && ! $this->clockOutOnly()
            )],
            // Present may leave clock-out blank (checked in, still working) — see withValidator().
            'clock_out' => ['nullable', 'date_format:H:i', 'required_if:status,half_day', Rule::requiredIf(fn () => $this->clockOutOnly())],
            'clock_out_only' => ['nullable', 'boolean'],
            // The clock-out is on the day after the date (06:30 in → 09:00 out next morning).
            'clock_out_next_day' => ['nullable', 'boolean'],
            'leave_type_id' => [
                'nullable',
                'required_if:status,on_leave,first_half_leave,second_half_leave',
                Rule::exists('leave_types', 'id')->where('tenant_id', $tenantId),
            ],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Clock-in only (Present with no clock-out) leaves the day open for the
     * employee to clock out, so it is limited to a single current day.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty() || $this->input('status') !== AttendanceStatus::Present->value) {
                return;
            }

            $date = Carbon::parse($this->input('date'))->startOfDay();

            if ($this->clockOutOnly()) {
                $this->validateClockOutOnly($validator, $date);

                return;
            }

            if ($this->filled('clock_out')) {
                if ($this->boolean('clock_out_next_day')
                    && Carbon::parse($date->copy()->addDay()->toDateString() . ' ' . $this->input('clock_out'))->gt(now())) {
                    $validator->errors()->add('clock_out', 'The next-day clock-out time cannot be in the future.');
                }

                return;
            }

            if ($this->filled('end_date') && ! Carbon::parse($this->input('end_date'))->isSameDay($date)) {
                $validator->errors()->add('clock_out', 'Clock-out time is required when marking more than one day.');
            } elseif ($date->lt(Carbon::yesterday())) {
                $validator->errors()->add('clock_out', 'Clock-out time is required for a past date. Only today can be marked as clock-in only.');
            } elseif (Carbon::parse($date->toDateString() . ' ' . $this->input('clock_in'))->gt(now())) {
                $validator->errors()->add('clock_in', 'Clock-in time cannot be in the future when clock-out is left blank.');
            }
        });
    }

    /** Clock-out only: Present, with just the clock-out time — for an employee who forgot to clock out. */
    public function clockOutOnly(): bool
    {
        return $this->boolean('clock_out_only') && $this->input('status') === AttendanceStatus::Present->value;
    }

    /**
     * Clock-out only closes the employee's own open clock-in, so there must
     * be one, and the clock-out must fall after it and not in the future.
     */
    private function validateClockOutOnly($validator, Carbon $date): void
    {
        if ($this->filled('end_date') && ! Carbon::parse($this->input('end_date'))->isSameDay($date)) {
            $validator->errors()->add('clock_out', 'Clock-out only can be marked for one day at a time.');

            return;
        }

        $tenantId = (int) $this->user()->tenant_id;
        $userId = (int) $this->input('user_id');
        $open = app(AttendanceEntryService::class)->openClockIn($userId, $tenantId, $date->toDateString());

        if (! $open) {
            $validator->errors()->add('clock_out', 'Clock-out only is for an employee who has clocked in and not clocked out. Enter both times instead.');

            return;
        }

        $clockIn = Carbon::parse($open->punched_at);
        $clockOut = Carbon::parse($date->toDateString() . ' ' . $this->input('clock_out'));
        $shift = app(TenantShiftResolver::class)->forUserDate($userId, $tenantId, $date->toDateString());
        $overnight = $shift && ShiftWindow::isOvernight($shift);

        if ($this->boolean('clock_out_next_day')) {
            $clockOut->addDay();
        } elseif ($clockOut->lte($clockIn)) {
            if (! $overnight) {
                $validator->errors()->add('clock_out', 'Clock-out time must be after the clock-in time (' . $clockIn->format('h:i A') . ').');

                return;
            }
            $clockOut->addDay();
        }

        if ($clockOut->gt(now())) {
            $validator->errors()->add('clock_out', 'Clock-out time cannot be in the future.');
        }
    }

    public function messages(): array
    {
        return [
            'user_id.exists' => 'Employee not found in your company.',
            'date.before_or_equal' => 'You cannot mark attendance for a future date.',
            'clock_in.required' => 'Clock-in time is required for present / half day.',
            'clock_out.required' => 'Clock-out time is required.',
            'clock_out.required_if' => 'Clock-out time is required for half day.',
            'leave_type_id.required_if' => 'A leave type is required when marking a leave.',
            'leave_type_id.exists' => 'Selected leave type is not valid for your company.',
        ];
    }

    public function attendanceStatus(): AttendanceStatus
    {
        return AttendanceStatus::from($this->input('status'));
    }
}
