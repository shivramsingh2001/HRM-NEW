<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use App\Models\User;
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
            'clock_in' => ['nullable', 'date_format:H:i', 'required_if:status,present,half_day'],
            // Present may leave clock-out blank (checked in, still working) — see withValidator().
            'clock_out' => ['nullable', 'date_format:H:i', 'required_if:status,half_day'],
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
            if ($validator->errors()->isNotEmpty()
                || $this->input('status') !== AttendanceStatus::Present->value
                || $this->filled('clock_out')) {
                return;
            }

            $date = Carbon::parse($this->input('date'))->startOfDay();

            if ($this->filled('end_date') && ! Carbon::parse($this->input('end_date'))->isSameDay($date)) {
                $validator->errors()->add('clock_out', 'Clock-out time is required when marking more than one day.');
            } elseif ($date->lt(Carbon::yesterday())) {
                $validator->errors()->add('clock_out', 'Clock-out time is required for a past date. Only today can be marked as clock-in only.');
            } elseif (Carbon::parse($date->toDateString() . ' ' . $this->input('clock_in'))->gt(now())) {
                $validator->errors()->add('clock_in', 'Clock-in time cannot be in the future when clock-out is left blank.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'user_id.exists' => 'Employee not found in your company.',
            'date.before_or_equal' => 'You cannot mark attendance for a future date.',
            'clock_in.required_if' => 'Clock-in time is required for present / half day.',
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
