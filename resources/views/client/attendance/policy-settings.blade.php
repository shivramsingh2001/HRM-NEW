@extends('client.layout.master')

@section('style')
    <style>
        .policy-container { max-width: 760px; margin: 0 auto; }
        .policy-card {
            background: #fff; border: 1px solid #edf2f7; border-radius: 16px;
            overflow: hidden; margin-bottom: 24px;
        }
        .policy-header { padding: 20px 24px; border-bottom: 1px solid #edf2f7; background: #fafbfc; }
        .policy-header h5 { margin: 0; font-size: 16px; font-weight: 600; color: #1e293b; }
        .policy-header p { margin: 4px 0 0; font-size: 12px; color: #64748b; }
        .policy-body { padding: 24px; }
        .policy-body .form-text { font-size: 12px; color: #64748b; }
        .rule-list { font-size: 13px; color: #475569; margin: 8px 0 0; padding-left: 18px; }
        .rule-list li { margin-bottom: 4px; }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Attendance Policy" />

    <div class="page-content">
        <div class="container-fluid">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="policy-container">
                <form action="{{ route('attendance-policy.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="policy-card">
                        <div class="policy-header">
                            <h5>Effective date</h5>
                            <p>Saving creates a new policy version. It applies to every day on or
                               after this date; closed months keep the version they were graded under.</p>
                        </div>
                        <div class="policy-body">
                            <input type="date" class="form-control" name="effective_from" style="max-width: 220px"
                                   value="{{ old('effective_from', now()->format('Y-m-d')) }}">
                        </div>
                    </div>

                    <div class="policy-card">
                        <div class="policy-header">
                            <h5>Day classification</h5>
                            <p>A day is scored by comparing hours worked against the hours the
                               employee was scheduled to work.</p>
                        </div>
                        <div class="policy-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Present ratio</label>
                                    <input type="number" step="0.01" min="0.1" max="1" class="form-control"
                                           name="present_ratio"
                                           value="{{ old('present_ratio', $policy->presentRatio) }}">
                                    <div class="form-text">worked / scheduled &ge; this &rarr; <strong>present</strong> (e.g. 0.90)</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Half-day ratio</label>
                                    <input type="number" step="0.01" min="0" max="1" class="form-control"
                                           name="half_day_ratio"
                                           value="{{ old('half_day_ratio', $policy->halfDayRatio) }}">
                                    <div class="form-text">&ge; this &rarr; <strong>half day</strong>, else <strong>absent</strong></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Fallback present hours</label>
                                    <input type="number" step="0.25" min="1" max="24" class="form-control"
                                           name="fallback_present_hours"
                                           value="{{ old('fallback_present_hours', $policy->fallbackPresentHours) }}">
                                    <div class="form-text">used when the day has no scheduled shift</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Fallback half-day hours</label>
                                    <input type="number" step="0.25" min="0" max="24" class="form-control"
                                           name="fallback_half_hours"
                                           value="{{ old('fallback_half_hours', $policy->fallbackHalfHours) }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="policy-card">
                        <div class="policy-header">
                            <h5>Overtime &amp; late arrival</h5>
                        </div>
                        <div class="policy-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Overtime after (hours)</label>
                                    <input type="number" step="0.25" min="1" max="24" class="form-control"
                                           name="overtime_after_hours"
                                           value="{{ old('overtime_after_hours', $policy->overtimeAfterHours) }}">
                                    <div class="form-text">For attendance reports only — paid overtime follows Company Policies → Overtime</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Overtime multiplier</label>
                                    <input type="number" step="0.05" min="0" max="5" class="form-control"
                                           name="overtime_multiplier"
                                           value="{{ old('overtime_multiplier', $policy->overtimeMultiplier) }}">
                                    <div class="form-text">For overtime cost estimates in reports only — payroll uses Company Policies → Overtime</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Grace period (minutes)</label>
                                    <input type="number" min="0" max="240" class="form-control"
                                           name="grace_minutes"
                                           value="{{ old('grace_minutes', $policy->graceMinutes) }}">
                                    <div class="form-text">arrivals within this window are not "late"</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Round punches to (minutes)</label>
                                    <input type="number" min="0" max="60" class="form-control"
                                           name="rounding_minutes"
                                           value="{{ old('rounding_minutes', $policy->roundingMinutes) }}">
                                    <div class="form-text">0 = keep exact times</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="policy-card">
                        <div class="policy-header">
                            <h5>Multiple punches</h5>
                            <p>Controls how many Clock In/Out events an employee may record per day.</p>
                        </div>
                        <div class="policy-body">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="allow_multiple_punches" name="allow_multiple_punches" value="1"
                                       {{ old('allow_multiple_punches', $tenant->allow_multiple_punches) ? 'checked' : '' }}>
                                <label class="form-check-label" for="allow_multiple_punches">
                                    Allow employees to clock in/out multiple times per day
                                </label>
                            </div>
                            <div class="form-text mt-2">
                                Off (default) &mdash; one Clock In and one Clock Out per day, exactly as today.
                                On &mdash; an employee can clock in again after clocking out; the idle time between
                                sessions is treated as a break and is not counted as worked time.
                            </div>
                        </div>
                    </div>

                    <div class="policy-card">
                        <div class="policy-header">
                            <h5>Monthly late allowance</h5>
                            <p>Record a day as <strong>half day</strong> once an employee's late arrivals
                               in a calendar month go over the allowance.</p>
                        </div>
                        <div class="policy-body">
                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="late_halfday_enabled" name="late_halfday_enabled" value="1"
                                       {{ old('late_halfday_enabled', $policy->lateHalfdayEnabled) ? 'checked' : '' }}>
                                <label class="form-check-label" for="late_halfday_enabled">
                                    Enable automatic late &rarr; half-day conversion
                                </label>
                            </div>

                            <div class="mb-3">
                                <label for="monthly_late_allowance" class="form-label">
                                    Late arrivals allowed per month
                                </label>
                                <input type="number" min="0" max="31" class="form-control"
                                       id="monthly_late_allowance" name="monthly_late_allowance"
                                       style="max-width: 160px"
                                       value="{{ old('monthly_late_allowance', $policy->monthlyLateAllowance) }}"
                                       placeholder="30">
                                <ul class="rule-list">
                                    <li><strong>Blank</strong> &mdash; falls back to the default of 30.</li>
                                    <li><strong>0</strong> &mdash; every late day becomes a half day.</li>
                                    <li><strong>N</strong> &mdash; the first N late days in a month are fine;
                                        the (N+1)th and every later late day become a half day.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="policy-card">
                        <div class="policy-header">
                            <h5>Working-time thresholds <span class="text-muted" style="font-size:12px">(advisory)</span></h5>
                            <p>Not enforced yet &mdash; reserved for compliance alerts.</p>
                        </div>
                        <div class="policy-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Min rest hours</label>
                                    <input type="number" step="0.5" min="0" max="24" class="form-control"
                                           name="min_rest_hours"
                                           value="{{ old('min_rest_hours', $policy->minRestHours) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Max daily hours</label>
                                    <input type="number" step="0.5" min="0" max="24" class="form-control"
                                           name="max_daily_hours"
                                           value="{{ old('max_daily_hours', $policy->maxDailyHours) }}">
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               id="sandwich_leave" name="sandwich_leave" value="1"
                                               {{ old('sandwich_leave', $policy->sandwichLeave) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="sandwich_leave">Sandwich-leave rule</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Policy Version</button>
                </form>

                @if (($history ?? collect())->isNotEmpty())
                    <div class="policy-card mt-4">
                        <div class="policy-header"><h5>Previous versions</h5></div>
                        <div class="policy-body">
                            <table class="table table-sm mb-0" style="font-size: 12px;">
                                <thead>
                                    <tr>
                                        <th>Effective from</th><th>Present</th><th>Half</th>
                                        <th>OT after</th><th>Grace</th><th>Late allow.</th><th>Saved</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($history as $h)
                                        <tr>
                                            <td>{{ \Carbon\Carbon::parse($h->effective_from)->format('d M Y') }}</td>
                                            <td>{{ $h->present_ratio }}</td>
                                            <td>{{ $h->half_day_ratio }}</td>
                                            <td>{{ $h->overtime_after_hours }}h</td>
                                            <td>{{ $h->grace_minutes }}m</td>
                                            <td>{{ $h->late_halfday_enabled ? $h->monthly_late_allowance : '—' }}</td>
                                            <td>{{ $h->created_at?->format('d M Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <p class="text-muted" style="font-size: 12px">
                    Changes take effect the next time attendance summaries are recalculated
                    (nightly, or immediately when an affected employee clocks in/out).
                </p>
            </div>
        </div>
    </div>
@endsection
