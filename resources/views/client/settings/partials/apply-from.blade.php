{{--
    "Apply from" date for the Company Policies attendance cards (Day
    Classification, Late Arrival, Early Leaving) — saved through
    App\Services\Attendance\AttendancePolicyWriter. Empty = from today.
--}}
<div class="mt-3">
    <label class="field-label">Apply from <span class="text-muted">(optional)</span></label>
    <input type="date" class="field-input" name="apply_from" value="{{ old('apply_from') }}"
           max="{{ now()->toDateString() }}" min="{{ now()->subYear()->toDateString() }}">
    <div class="field-hint">
        Empty = from today. The change applies from this day on — days before it keep the earlier rule —
        and saved attendance is re-graded straight away. Locked months and processed / paid payslips are never changed.
        Employees with their own value on Employee 360 → Policies keep it.
    </div>
    @error('apply_from')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
</div>
