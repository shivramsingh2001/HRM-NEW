@extends('client.layout.master')

@section('style')
    <style>
        .wf-card {
            background: #fff;
            border: 1px solid #edf2f7;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 14px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
        }

        .wf-card__head {
            padding: 12px 18px;
            border-bottom: 1px solid #f1f5f9;
            background: #fafbfc;
        }

        .wf-card__title {
            margin: 0;
            font-size: 13.5px;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .wf-card__title i {
            font-size: 14px;
            color: var(--icon-color, #0D6EFD);
        }

        .wf-card__desc {
            margin: 3px 0 0;
            font-size: 11px;
            color: #64748b;
            line-height: 1.45;
        }

        .wf-card__body {
            padding: 18px;
        }

        /* Cards in the same row share one height; the Update button sits at the bottom. */
        .wf-row > [class*="col-"] {
            display: flex;
            flex-direction: column;
        }

        .wf-row .wf-card {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
        }

        .wf-row .wf-card__body {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
        }

        .wf-row .wf-card__body > .btn-update {
            align-self: flex-start;
            margin-top: auto;
        }

        .field-label {
            font-size: 11.5px;
            font-weight: 500;
            color: #334155;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .field-input {
            width: 100%;
            font-size: 12.5px;
            color: #1e293b;
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 7px;
            background: #fff;
            transition: all .15s ease;
            outline: none;
        }

        .field-input:focus {
            border-color: var(--primary, #0D6EFD);
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.08);
        }

        #employee_id_prefix {
            max-width: 120px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }

        .field-hint {
            font-size: 10.5px;
            color: #94a3b8;
            margin-top: 4px;
        }

        .eid-row {
            display: flex;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 12px;
        }

        .eid-preview {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            height: 33px;
            background: #f4f6fb;
            border-radius: 8px;
        }

        .eid-preview__label {
            font-size: 10.5px;
            color: #6b7385;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .eid-preview__value {
            font-size: 13px;
            font-weight: 700;
            color: #0D6EFD;
            letter-spacing: .5px;
        }

        .btn-save {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #0D6EFD, #0D6EFD);
            color: #fff;
            border: none;
            font-size: 12.5px;
            font-weight: 500;
            padding: 7px 18px;
            border-radius: 8px;
            cursor: pointer;
            transition: all .15s ease;
        }

        .btn-save:hover {
            filter: brightness(0.9);
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.3);
        }

        .btn-update {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #0D6EFD, #0D6EFD);
            color: #fff;
            border: none;
            font-size: 12.5px;
            font-weight: 500;
            padding: 7px 18px;
            border-radius: 8px;
            cursor: pointer;
            transition: all .15s ease;
        }

        .btn-update:hover {
            filter: brightness(0.9);
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.3);
        }

        .info-list {
            display: flex;
            flex-direction: column;
            gap: 0;
            margin-bottom: 14px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 10px;
            font-size: 12.5px;
            padding: 8px 0;
            border-bottom: 1px dashed #eef2f7;
        }

        .info-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .info-row:first-child {
            padding-top: 0;
        }

        .info-row__label {
            color: #64748b;
        }

        .info-row__value {
            color: #1e293b;
            font-weight: 600;
            text-align: right;
        }

        .alert-box {
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 12px;
            margin-bottom: 12px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            line-height: 1.5;
        }

        .alert-box--success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #dcfce7;
        }

        .alert-box--danger {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fee2e2;
        }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Company Policies" />

    <div class="main-content" style="padding: 20px !important;">

        @if (session('success'))
            <div class="alert-box alert-box--success">
                <i class="feather-check-circle"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert-box alert-box--danger">
                <i class="feather-alert-triangle"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <div class="row wf-row">
            <div class="col-md-4">
                <div class="wf-card">
                    <div class="wf-card__head">
                        <h5 class="wf-card__title">
                            <i class="feather-hash"></i>
                            Employee ID Prefix
                        </h5>
                        <p class="wf-card__desc">
                            The 2-letter code added in front of every new employee's auto-generated ID
                            (e.g. <strong>SH</strong>000123).
                        </p>
                    </div>
                    <div class="wf-card__body">
                        <div class="info-list">
                            <div class="info-row">
                                <span class="info-row__label">Current prefix</span>
                                <span class="info-row__value">{{ $tenant->employee_id_prefix ?? $defaultPrefix }}</span>
                            </div>
                            {{-- <div class="info-row">
                                <span class="info-row__label">Preview</span>
                                <span class="info-row__value">{{ $exampleId }}</span>
                            </div> --}}
                        </div>

                        <button type="button" class="btn-update" data-bs-toggle="modal" data-bs-target="#employeeIdPrefixModal">
                            <i class="feather-edit-2"></i> Update
                        </button>
                    </div>
                </div>

            </div>

            <div class="col-md-4">
                <div class="wf-card">
                    <div class="wf-card__head">
                        <h5 class="wf-card__title">
                            <i class="feather-calendar"></i>
                            Company-wide Notice Period
                        </h5>
                        <p class="wf-card__desc">
                            Default days an employee must serve between resignation
                            and their last working day.
                        </p>
                    </div>
                    <div class="wf-card__body">
                        <div class="info-list">
                            <div class="info-row">
                                <span class="info-row__label">Notice period</span>
                                <span class="info-row__value">{{ $tenant->notice_period ?? $defaultDays }} days</span>
                            </div>
                        </div>

                        <button type="button" class="btn-update" data-bs-toggle="modal" data-bs-target="#noticePeriodModal">
                            <i class="feather-edit-2"></i> Update
                        </button>
                    </div>
                </div>

            </div>

            <div class="col-md-4">
                <div class="wf-card">
                    <div class="wf-card__head">
                        <h5 class="wf-card__title">
                            <i class="feather-repeat"></i>
                            Multiple Punches
                        </h5>
                        <p class="wf-card__desc">
                            Controls how many Clock In/Out events an employee may record per day.
                        </p>
                    </div>
                    <div class="wf-card__body">
                        <div class="info-list">
                            <div class="info-row">
                                <span class="info-row__label">Multiple punches</span>
                                <span class="info-row__value">{{ $tenant->allow_multiple_punches ? 'Enabled' : 'Disabled' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-row__label">Auto clock-out after</span>
                                <span class="info-row__value">{{ (int) ($tenant->auto_clockout_hours ?? 15) }} h</span>
                            </div>
                        </div>

                        <button type="button" class="btn-update" data-bs-toggle="modal" data-bs-target="#multiplePunchesModal">
                            <i class="feather-edit-2"></i> Update
                        </button>
                    </div>
                </div>

            </div>

            <div class="col-md-4">
                <div class="wf-card">
                    <div class="wf-card__head">
                        <h5 class="wf-card__title">
                            <i class="feather-sliders"></i>
                            Day Classification
                        </h5>
                        <p class="wf-card__desc">
                            A day is scored by comparing hours worked against the hours the
                            employee was scheduled to work.
                        </p>
                    </div>
                    <div class="wf-card__body">
                        <div class="info-list">
                            <div class="info-row">
                                <span class="info-row__label">Half-day calculation</span>
                                <span class="info-row__value">{{ $policy->dayClassificationEnabled ? 'Enabled' : 'Disabled' }}</span>
                            </div>
                        </div>
                        <div class="info-list" @unless ($policy->dayClassificationEnabled) style="opacity:.5" @endunless>
                            <div class="info-row">
                                <span class="info-row__label">Present ratio</span>
                                <span class="info-row__value">{{ $policy->presentRatio }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-row__label">Half-day ratio</span>
                                <span class="info-row__value">{{ $policy->halfDayRatio }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-row__label">Fallback present hours</span>
                                <span class="info-row__value">{{ $policy->fallbackPresentHours }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-row__label">Fallback half-day hours</span>
                                <span class="info-row__value">{{ $policy->fallbackHalfHours }}</span>
                            </div>
                        </div>

                        <button type="button" class="btn-update" data-bs-toggle="modal" data-bs-target="#dayClassificationModal">
                            <i class="feather-edit-2"></i> Update
                        </button>
                    </div>
                </div>

            </div>

            <div class="col-md-4">
                <div class="wf-card">
                    <div class="wf-card__head">
                        <h5 class="wf-card__title">
                            <i class="feather-clock"></i>
                            Late Arrival
                        </h5>
                        <p class="wf-card__desc">
                            Monthly allowance, what happens to attendance once it's exceeded, and an
                            optional payroll deduction.
                        </p>
                    </div>
                    <div class="wf-card__body">
                        <div class="info-list">
                            <div class="info-row">
                                <span class="info-row__label">Grace period source</span>
                                <span class="info-row__value">
                                    @if ($policy->graceMode === 'fixed')
                                        Fixed ({{ $policy->fixedGraceMinutes }}m)
                                    @else
                                        As per shift
                                    @endif
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-row__label">Monthly allowance</span>
                                <span class="info-row__value">{{ $policy->monthlyLateAllowance }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-row__label">Attendance action</span>
                                <span class="info-row__value">
                                    @if ($policy->lateAttendanceAction === 'half_day')
                                        Mark half day
                                    @elseif ($policy->lateAttendanceAction === 'absent')
                                        Mark absent
                                    @else
                                        Keep Original
                                    @endif
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-row__label">Payroll deduction</span>
                                <span class="info-row__value">
                                    @if (! $policy->lateDeductionEnabled)
                                        Off
                                    @elseif ($policy->lateDeductionMode === 'fixed_amount')
                                        &#8377;{{ $policy->lateDeductionAmount }} / day
                                    @elseif ($policy->lateDeductionMode === 'half_day')
                                        Half Day (0.5x)
                                    @elseif ($policy->lateDeductionMode === 'full_day')
                                        Full Day (1x)
                                    @else
                                        {{ $policy->lateDeductionMultiplier }}x
                                    @endif
                                </span>
                            </div>
                        </div>

                        <button type="button" class="btn-update" data-bs-toggle="modal" data-bs-target="#lateArrivalModal">
                            <i class="feather-edit-2"></i> Update
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="wf-card">
                    <div class="wf-card__head">
                        <h5 class="wf-card__title">
                            <i class="feather-log-out"></i>
                            Early Leaving
                        </h5>
                        <p class="wf-card__desc">
                            Independent from Late Arrival — its own allowance, attendance action, and
                            payroll deduction.
                        </p>
                    </div>
                    <div class="wf-card__body">
                        <div class="info-list">
                            <div class="info-row">
                                <span class="info-row__label">Monthly allowance</span>
                                <span class="info-row__value">{{ $policy->monthlyEarlyAllowance }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-row__label">Attendance action</span>
                                <span class="info-row__value">
                                    @if ($policy->earlyAttendanceAction === 'half_day')
                                        Mark half day
                                    @elseif ($policy->earlyAttendanceAction === 'absent')
                                        Mark absent
                                    @else
                                        Keep Original
                                    @endif
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-row__label">Payroll deduction</span>
                                <span class="info-row__value">
                                    @if (! $policy->earlyDeductionEnabled)
                                        Off
                                    @elseif ($policy->earlyDeductionMode === 'fixed_amount')
                                        &#8377;{{ $policy->earlyDeductionAmount }} / day
                                    @elseif ($policy->earlyDeductionMode === 'half_day')
                                        Half Day (0.5x)
                                    @elseif ($policy->earlyDeductionMode === 'full_day')
                                        Full Day (1x)
                                    @else
                                        {{ $policy->earlyDeductionMultiplier }}x
                                    @endif
                                </span>
                            </div>
                        </div>

                        <button type="button" class="btn-update" data-bs-toggle="modal" data-bs-target="#earlyLeavingModal">
                            <i class="feather-edit-2"></i> Update
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="wf-card">
                    <div class="wf-card__head">
                        <h5 class="wf-card__title">
                            <i class="feather-activity"></i>
                            Working-time thresholds
                        </h5>
                        <p class="wf-card__desc">
                            Rest and daily-hours limits for shift swaps &amp; changes, and the sandwich-leave rule for payroll.
                        </p>
                    </div>
                    <div class="wf-card__body">
                        <div class="info-list">
                            <div class="info-row">
                                <span class="info-row__label">Min rest hours</span>
                                <span class="info-row__value">{{ $policy->minRestHours ? $policy->minRestHours . ' h' : 'No minimum' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-row__label">Max daily hours</span>
                                <span class="info-row__value">{{ $policy->maxDailyHours ? $policy->maxDailyHours . ' h' : 'No limit' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-row__label">Sandwich-leave rule</span>
                                <span class="info-row__value">{{ $policy->sandwichLeave ? 'Enabled' : 'Disabled' }}</span>
                            </div>
                        </div>

                        <button type="button" class="btn-update" data-bs-toggle="modal" data-bs-target="#workingTimeThresholdsModal">
                            <i class="feather-edit-2"></i> Update
                        </button>
                    </div>
                </div>
            </div>

            @php $limit = fn ($v, string $unit) => (int) $v > 0 ? $v . ' ' . $unit : 'No limit'; @endphp
            <div class="col-md-4">
                <div class="wf-card">
                    <div class="wf-card__head">
                        <h5 class="wf-card__title">
                            <i class="feather-sliders"></i>
                            Request limits
                        </h5>
                        <p class="wf-card__desc">
                            How much work from home and regularization an employee may request.
                        </p>
                    </div>
                    <div class="wf-card__body">
                        <div class="info-list">
                            <div class="info-row">
                                <span class="info-row__label">WFH days / month</span>
                                <span class="info-row__value">{{ $limit($tenant->wfh_max_days_per_month, 'days') }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-row__label">WFH notice</span>
                                <span class="info-row__value">{{ $limit($tenant->wfh_min_notice_days, 'days') }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-row__label">Regularizations / month</span>
                                <span class="info-row__value">{{ $limit($tenant->regularization_max_per_month, 'requests') }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-row__label">Regularize up to</span>
                                <span class="info-row__value">{{ (int) $tenant->regularization_max_days_back > 0 ? $tenant->regularization_max_days_back . ' days back' : 'No limit' }}</span>
                            </div>
                        </div>

                        <button type="button" class="btn-update" data-bs-toggle="modal" data-bs-target="#requestLimitsModal">
                            <i class="feather-edit-2"></i> Update
                        </button>
                    </div>
                </div>
            </div>

            @feature('leave_management')
            @php
                $lyMonth = (int) ($tenant->leave_year_start_month ?: 4);
                $lyDay = (int) ($tenant->leave_year_start_day ?: 1);
                $lyStart = \Carbon\Carbon::create(2000, $lyMonth, $lyDay);
                $lyEnd = $lyStart->copy()->subDay();
            @endphp
            <div class="col-md-4">
                <div class="wf-card">
                    <div class="wf-card__head">
                        <h5 class="wf-card__title">
                            <i class="feather-corner-down-right"></i>
                            Leave carry forward
                        </h5>
                        <p class="wf-card__desc">
                            What happens to unused leave when a new leave year starts.
                        </p>
                    </div>
                    <div class="wf-card__body">
                        <div class="info-list">
                            <div class="info-row">
                                <span class="info-row__label">Carry forward rules</span>
                                <span class="info-row__value">{{ $tenant->leave_carry_forward_enabled ? 'Active' : 'Inactive' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-row__label">Leave year</span>
                                <span class="info-row__value">{{ $lyStart->format('j M') }} – {{ $lyEnd->format('j M') }}</span>
                            </div>
                            @if ($tenant->leave_carry_forward_enabled && $tenant->leave_carry_forward_enabled_at)
                                <div class="info-row">
                                    <span class="info-row__label">Active since</span>
                                    <span class="info-row__value">{{ $tenant->leave_carry_forward_enabled_at->format('d M Y') }}</span>
                                </div>
                            @endif
                        </div>

                        <button type="button" class="btn-update" data-bs-toggle="modal" data-bs-target="#leaveCarryForwardModal">
                            <i class="feather-edit-2"></i> Update
                        </button>
                    </div>
                </div>

            </div>
            @endfeature

            @feature('overtime')
            @if ($canManageOvertime)
            @php
                $otNum = fn ($v) => $v !== null && (float) $v > 0 ? rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') : null;
                $otAuto = $overtime->mode === 'auto';
            @endphp
            <div class="col-md-4" id="overtime">
                <div class="wf-card">
                    <div class="wf-card__head">
                        <h5 class="wf-card__title">
                            <i class="feather-clock"></i>
                            Overtime
                        </h5>
                        <p class="wf-card__desc">
                            Whether overtime is counted, and whether it is calculated from attendance or raised by employees.
                        </p>
                    </div>
                    <div class="wf-card__body">
                        <div class="info-list">
                            <div class="info-row">
                                <span class="info-row__label">Overtime</span>
                                <span class="info-row__value">{{ $overtime->enabled ? 'Active' : 'Inactive' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-row__label">Mode</span>
                                <span class="info-row__value">{{ $otAuto ? 'Automatic calculation' : 'Request & approval' }}</span>
                            </div>
                            @if ($otAuto)
                                <div class="info-row">
                                    <span class="info-row__label">Counting starts</span>
                                    <span class="info-row__value">
                                        {{ $overtime->auto_start_basis === 'fixed'
                                            ? ((int) $overtime->auto_start_after_minutes) . ' min after shift end'
                                            : 'After the shift\'s grace time' }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        <button type="button" class="btn-update" data-bs-toggle="modal" data-bs-target="#overtimeModeModal">
                            <i class="feather-edit-2"></i> Update
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="wf-card">
                    <div class="wf-card__head">
                        <h5 class="wf-card__title">
                            <i class="feather-dollar-sign"></i>
                            Overtime limits &amp; rate
                        </h5>
                        <p class="wf-card__desc">
                            Daily and monthly limits and how overtime is paid — used in both modes.
                        </p>
                    </div>
                    <div class="wf-card__body">
                        <div class="info-list">
                            <div class="info-row">
                                <span class="info-row__label">Minimum per day</span>
                                <span class="info-row__value">{{ $otNum($overtime->min_hours) ? $otNum($overtime->min_hours) . ' h' : 'None' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-row__label">Maximum per day</span>
                                <span class="info-row__value">{{ $otNum($overtime->max_hours_per_day) ? $otNum($overtime->max_hours_per_day) . ' h' : 'No limit' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-row__label">Maximum per month</span>
                                <span class="info-row__value">{{ $otNum($overtime->max_hours_per_month) ? $otNum($overtime->max_hours_per_month) . ' h' : 'No limit' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-row__label">Rate</span>
                                <span class="info-row__value">
                                    {{ $overtime->rate_type === 'fixed'
                                        ? '₹' . number_format((float) $overtime->fixed_rate_per_hour, 2) . ' per hour'
                                        : '× ' . ($otNum($overtime->rate_multiplier) ?? '1.5') . ' hourly rate' }}
                                </span>
                            </div>
                            @unless ($otAuto)
                                <div class="info-row">
                                    <span class="info-row__label">Approval</span>
                                    <span class="info-row__value">
                                        {{ $overtime->require_approval
                                            ? ($otNum($overtime->auto_approve_limit) ? 'Needed above ' . $otNum($overtime->auto_approve_limit) . ' h' : 'Always needed')
                                            : 'Not needed' }}
                                    </span>
                                </div>
                            @endunless
                        </div>

                        <button type="button" class="btn-update" data-bs-toggle="modal" data-bs-target="#overtimeLimitsModal">
                            <i class="feather-edit-2"></i> Update
                        </button>
                    </div>
                </div>
            </div>
            @endif
            @endfeature

            @if ($showShiftRequests)
            <div class="col-md-4" id="shift-requests">
                <div class="wf-card">
                    <div class="wf-card__head">
                        <h5 class="wf-card__title">
                            <i class="feather-repeat"></i>
                            Shift Requests
                        </h5>
                        <p class="wf-card__desc">
                            Employee shift swaps and shift change requests — who may ask, how early, and who approves.
                        </p>
                    </div>
                    <div class="wf-card__body">
                        <div class="info-list">
                            <div class="info-row">
                                <span class="info-row__label">Swaps / changes</span>
                                <span class="info-row__value">{{ $shiftRequests->swap_enabled ? 'On' : 'Off' }} / {{ $shiftRequests->change_enabled ? 'On' : 'Off' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-row__label">Approval</span>
                                <span class="info-row__value">{{ $shiftRequests->requires_approval ? 'Manager / HR' : 'Not needed' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-row__label">Notice · rest</span>
                                <span class="info-row__value" title="Rest comes from Working-time thresholds">{{ $shiftRequests->min_notice_hours }} h · {{ $policy->minRestHours ? rtrim(rtrim(number_format((float) $policy->minRestHours, 2), '0'), '.') . ' h' : 'no minimum' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-row__label">Limit per month</span>
                                <span class="info-row__value">{{ $shiftRequests->max_requests_per_month ?: 'No limit' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-row__label">Swap with</span>
                                <span class="info-row__value">{{ $shiftRequests->same_department_only ? 'Same department' : 'Anyone' }}{{ $shiftRequests->same_branch_only ? ', same branch' : '' }}</span>
                            </div>
                        </div>

                        <button type="button" class="btn-update" data-bs-toggle="modal" data-bs-target="#shiftRequestsModal">
                            <i class="feather-edit-2"></i> Update
                        </button>
                    </div>
                </div>
            </div>
            @endif
        </div>

    </div>
@endsection

{{--
    Modals live in the create-modal yield (rendered at the body level by
    master.blade.php, see the department module's addDepartments/editDepartment
    modals for the same pattern) rather than inline inside content-area — the
    content-area wraps its content in a scrollbar container that breaks
    Bootstrap's position:fixed modal positioning, leaving the modal rendered
    but invisible/clipped behind the page.
--}}
@section('create-modal')

        {{-- Employee ID Prefix --}}
        <x-ui.modal id="employeeIdPrefixModal" title="Employee ID Prefix">
            <form action="{{ route('employee-id-settings.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="eid-row">
                    <div>
                        <label for="employee_id_prefix" class="field-label">
                            <i class="feather-type"></i> Prefix (2 letters)
                        </label>
                        <input type="text" class="field-input" id="employee_id_prefix" name="employee_id_prefix"
                            maxlength="2" pattern="[A-Za-z]{2}" required
                            value="{{ old('employee_id_prefix', $tenant->employee_id_prefix ?? $defaultPrefix) }}">
                    </div>

                    <div class="eid-preview">
                        <span class="eid-preview__label">Preview</span>
                        <span class="eid-preview__value" id="eidPreviewValue">{{ $exampleId }}</span>
                    </div>
                </div>
                <div class="field-hint mb-3">
                    If left unset, the system falls back to "{{ $defaultPrefix }}".
                </div>

                <button type="submit" class="btn-save">
                    <i class="feather-check"></i> Save
                </button>
            </form>
        </x-ui.modal>

        {{-- Company-wide Notice Period --}}
        <x-ui.modal id="noticePeriodModal" title="Company-wide Notice Period">
            <form action="{{ route('notice-period-settings.update') }}" method="POST">
                @csrf
                @method('PUT')

                <label for="notice_period" class="field-label">
                    <i class="feather-clock"></i> Notice period (days)
                </label>
                <input type="number" min="0" max="365" class="field-input" id="notice_period"
                    name="notice_period" value="{{ old('notice_period', $tenant->notice_period ?? $defaultDays) }}">
                <div class="field-hint">
                    If left unset, the system falls back to {{ $defaultDays }} days.
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn-save">
                        <i class="feather-check"></i> Save
                    </button>
                </div>
            </form>
        </x-ui.modal>

        {{-- Leave carry forward --}}
        @feature('leave_management')
        <x-ui.modal id="leaveCarryForwardModal" title="Leave carry forward" size="md">
            <form action="{{ route('leave-carry-forward-settings.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="leave_carry_forward_enabled" name="leave_carry_forward_enabled" value="1"
                           {{ old('leave_carry_forward_enabled', $tenant->leave_carry_forward_enabled) ? 'checked' : '' }}>
                    <label class="form-check-label field-label mb-0" for="leave_carry_forward_enabled">
                        Apply carry forward rules at the start of each leave year
                    </label>
                </div>
                <div class="field-hint">
                    Active — when a new leave year starts, unused leave above each leave type's carry-forward
                    limit lapses, and carried days not used before the type's expiry lapse too.
                    Inactive (default) — all unused leave carries over, nothing lapses.
                    Turning it on applies from the next leave year start, never to the year already running.
                </div>

                <div class="row g-2 mt-2">
                    <div class="col-7">
                        <label class="field-label">Leave year starts — month</label>
                        <select class="field-input" name="leave_year_start_month">
                            @foreach (range(1, 12) as $m)
                                <option value="{{ $m }}" @selected((int) old('leave_year_start_month', $tenant->leave_year_start_month ?: 4) === $m)>
                                    {{ \Carbon\Carbon::create(2000, $m, 1)->format('F') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-5">
                        <label class="field-label">Day</label>
                        <input type="number" min="1" max="28" step="1" class="field-input" name="leave_year_start_day"
                               value="{{ old('leave_year_start_day', (int) ($tenant->leave_year_start_day ?: 1)) }}">
                    </div>
                </div>
                @error('leave_year_start_day')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
                <div class="field-hint mt-2">
                    Also the day yearly leave credits are added (default 1 April).
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn-save">
                        <i class="feather-check"></i> Save
                    </button>
                </div>
            </form>
        </x-ui.modal>
        @endfeature

        {{-- Shift Requests policy --}}
        @if ($showShiftRequests)
        <x-ui.modal id="shiftRequestsModal" title="Shift Requests" size="md">
            <form action="{{ route('shift-request-settings.update') }}" method="POST">
                @csrf
                @method('PUT')
                @foreach ([
                    'swap_enabled' => ['Employees can swap shifts with a colleague', 'The colleague accepts first, then it goes for approval.'],
                    'change_enabled' => ['Employees can request a different shift', 'For specific dates, e.g. evening instead of morning.'],
                    'requires_approval' => ['Needs approval by the reporting manager / HR', 'Off — an accepted swap or a change request is applied at once. A workflow set up in Settings → Approvals ("shift_request") is used when present.'],
                    'same_department_only' => ['Swap only within the same department', null],
                    'same_branch_only' => ['Swap only within the same branch', null],
                    'notify_on_roster_change' => ['Notify employees when their roster changes', 'One message per change (assign, edit, swap) for today and later days.'],
                ] as $key => [$label, $hint])
                    <div class="form-check form-switch {{ $loop->first ? '' : 'mt-2' }}">
                        <input class="form-check-input" type="checkbox" role="switch" id="sr_{{ $key }}" name="{{ $key }}" value="1" @checked($shiftRequests->{$key})>
                        <label class="form-check-label field-label mb-0" for="sr_{{ $key }}">{{ $label }}</label>
                    </div>
                    @if ($hint)<div class="field-hint">{{ $hint }}</div>@endif
                @endforeach

                <div class="row g-2 mt-2">
                    <div class="col-6">
                        <label class="field-label">Minimum notice (hours)</label>
                        <input type="number" min="0" max="720" class="field-input" name="min_notice_hours" value="{{ old('min_notice_hours', $shiftRequests->min_notice_hours) }}" required>
                    </div>
                    <div class="col-6">
                        <label class="field-label">Requests per employee per month</label>
                        <input type="number" min="0" max="100" class="field-input" name="max_requests_per_month" value="{{ old('max_requests_per_month', $shiftRequests->max_requests_per_month) }}" required>
                        <div class="field-hint">0 = no limit.</div>
                    </div>
                    <div class="col-6">
                        <label class="field-label">Colleague must answer within (hours)</label>
                        <input type="number" min="1" max="168" class="field-input" name="peer_response_hours" value="{{ old('peer_response_hours', $shiftRequests->peer_response_hours) }}" required>
                    </div>
                </div>
                <div class="field-hint mt-2">
                    Minimum rest between shifts and maximum hours a day come from the <strong>Working-time thresholds</strong> card:
                    an employee request that breaks them is refused; for admin / manager direct changes it is only a warning.
                    A request not decided before its first shift starts expires automatically.
                </div>
                @foreach (['min_notice_hours', 'max_requests_per_month', 'peer_response_hours'] as $f)
                    @error($f)<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                @endforeach

                <div class="mt-3">
                    <button type="submit" class="btn-save"><i class="feather-check"></i> Save</button>
                </div>
            </form>
        </x-ui.modal>
        @endif

        {{-- Overtime: switch + mode --}}
        @feature('overtime')
        @if ($canManageOvertime)
        <x-ui.modal id="overtimeModeModal" title="Overtime" size="md">
            <form action="{{ route('overtime-policy-settings.update') }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="section" value="mode">

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="ot_enabled" name="enabled" value="1"
                           {{ old('enabled', $overtime->enabled) ? 'checked' : '' }}>
                    <label class="form-check-label field-label mb-0" for="ot_enabled">Overtime is active</label>
                </div>
                <div class="field-hint">
                    Inactive — no new overtime is recorded (no requests, no automatic calculation).
                    Overtime already approved is still paid.
                </div>

                <div id="ot_mode_fields">
                    <label class="field-label mt-3">How overtime is recorded</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="mode" id="ot_mode_request" value="request"
                               @checked(old('mode', $overtime->mode) !== 'auto')>
                        <label class="form-check-label" for="ot_mode_request">
                            <strong>Overtime request &amp; approval</strong> — employees raise a request and it follows the approval workflow.
                        </label>
                    </div>
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="radio" name="mode" id="ot_mode_auto" value="auto"
                               @checked(old('mode', $overtime->mode) === 'auto')>
                        <label class="form-check-label" for="ot_mode_auto">
                            <strong>Automatic overtime calculation</strong> — calculated from attendance and shift timing; no request needed.
                        </label>
                    </div>

                    <div id="ot_auto_fields" class="mt-3">
                        <label class="field-label">Overtime starts counting</label>
                        <select class="field-input" name="auto_start_basis" id="ot_start_basis">
                            <option value="grace" @selected(old('auto_start_basis', $overtime->auto_start_basis) !== 'fixed')>After the shift's grace time</option>
                            <option value="fixed" @selected(old('auto_start_basis', $overtime->auto_start_basis) === 'fixed')>A fixed number of minutes after shift end</option>
                        </select>
                        <div id="ot_fixed_minutes_wrap" class="mt-2">
                            <label class="field-label">Minutes after shift end</label>
                            <input type="number" min="0" max="600" step="1" class="field-input" name="auto_start_after_minutes"
                                   value="{{ old('auto_start_after_minutes', (int) $overtime->auto_start_after_minutes) }}">
                        </div>
                        <div class="field-hint mt-2">
                            Example: shift ends 6:00 PM, 30 minutes → overtime counts from 6:30 PM.
                            Counted after the last shift of the day; overnight shifts use their real end time.
                            No clock-out, an automatic (system) clock-out or leaving before the start time = no overtime.
                        </div>
                    </div>
                </div>
                @foreach (['mode', 'auto_start_basis', 'auto_start_after_minutes'] as $f)
                    @error($f)
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                @endforeach

                <div class="mt-3">
                    <button type="submit" class="btn-save">
                        <i class="feather-check"></i> Save
                    </button>
                </div>
            </form>
        </x-ui.modal>

        {{-- Overtime: limits + rate --}}
        <x-ui.modal id="overtimeLimitsModal" title="Overtime limits &amp; rate" size="md">
            <form action="{{ route('overtime-policy-settings.update') }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="section" value="limits">

                <div class="row g-2">
                    <div class="col-4">
                        <label class="field-label">Minimum hours / day</label>
                        <input type="number" min="0" max="24" step="0.25" class="field-input" name="min_hours"
                               value="{{ old('min_hours', $overtime->min_hours) }}" placeholder="None">
                    </div>
                    <div class="col-4">
                        <label class="field-label">Maximum hours / day</label>
                        <input type="number" min="0" max="24" step="0.25" class="field-input" name="max_hours_per_day"
                               value="{{ old('max_hours_per_day', $overtime->max_hours_per_day) }}" placeholder="No limit">
                    </div>
                    <div class="col-4">
                        <label class="field-label">Maximum hours / month</label>
                        <input type="number" min="0" max="744" step="0.5" class="field-input" name="max_hours_per_month"
                               value="{{ old('max_hours_per_month', $overtime->max_hours_per_month) }}" placeholder="No limit">
                    </div>
                </div>
                <div class="field-hint">Empty or 0 = no limit. Automatic overtime below the minimum is not counted; above the maximum it is capped.</div>

                <label class="field-label mt-3">Overtime pay</label>
                <select class="field-input" name="rate_type" id="ot_rate_type">
                    <option value="multiplier" @selected(old('rate_type', $overtime->rate_type) !== 'fixed')>Overtime rate multiplier (× hourly rate)</option>
                    <option value="fixed" @selected(old('rate_type', $overtime->rate_type) === 'fixed')>Fixed amount per hour</option>
                </select>
                <div class="row g-2 mt-1">
                    <div class="col-6" id="ot_multiplier_wrap">
                        <label class="field-label">Multiplier</label>
                        <input type="number" min="1" max="5" step="0.05" class="field-input" name="rate_multiplier"
                               value="{{ old('rate_multiplier', $overtime->rate_multiplier ?? 1.5) }}">
                    </div>
                    <div class="col-6" id="ot_fixed_rate_wrap">
                        <label class="field-label">Amount per hour (₹)</label>
                        <input type="number" min="1" max="100000" step="1" class="field-input" name="fixed_rate_per_hour"
                               value="{{ old('fixed_rate_per_hour', $overtime->fixed_rate_per_hour) }}">
                    </div>
                </div>
                <div class="field-hint">Hourly rate = basic salary ÷ days (payroll setting) ÷ working hours per day.</div>

                <div id="ot_approval_fields" class="{{ $overtime->mode === 'auto' ? 'd-none' : '' }}">
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="ot_require_approval" name="require_approval" value="1"
                               {{ old('require_approval', $overtime->require_approval) ? 'checked' : '' }}>
                        <label class="form-check-label field-label mb-0" for="ot_require_approval">Requests need approval</label>
                    </div>
                    <label class="field-label mt-2">Approve automatically up to (hours)</label>
                    <input type="number" min="0" max="24" step="0.25" class="field-input" name="auto_approve_limit"
                           value="{{ old('auto_approve_limit', $overtime->auto_approve_limit) }}" placeholder="Never">
                    <div class="field-hint">Request &amp; approval mode only.</div>
                </div>
                @foreach (['min_hours', 'max_hours_per_day', 'max_hours_per_month', 'rate_type', 'rate_multiplier', 'fixed_rate_per_hour', 'auto_approve_limit'] as $f)
                    @error($f)
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                @endforeach

                <div class="mt-3">
                    <button type="submit" class="btn-save">
                        <i class="feather-check"></i> Save
                    </button>
                </div>
            </form>
        </x-ui.modal>
        @endif
        @endfeature

        {{-- Multiple Punches --}}
        <x-ui.modal id="multiplePunchesModal" title="Multiple Punches">
            <form action="{{ route('multiple-punches-settings.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="allow_multiple_punches" name="allow_multiple_punches" value="1"
                           {{ old('allow_multiple_punches', $tenant->allow_multiple_punches) ? 'checked' : '' }}>
                    <label class="form-check-label field-label mb-0" for="allow_multiple_punches">
                        Allow employees to clock in/out multiple times per day
                    </label>
                </div>
                <div class="field-hint">
                    Off (default) — one Clock In and one Clock Out per day.
                    On — an employee can clock in again after clocking out; the idle
                    time between sessions is treated as a break.
                </div>

                <label class="field-label mt-3">Auto clock-out after (hours)</label>
                <input type="number" min="4" max="48" step="1" class="field-input" name="auto_clockout_hours"
                       value="{{ old('auto_clockout_hours', (int) ($tenant->auto_clockout_hours ?? 15)) }}">
                <div class="field-hint">
                    A clock-in left open this long is closed automatically (at the shift end when that is earlier).
                    Raise it when employees work long duties that end the next morning — e.g. 06:30 → 09:00 next day is 26.5 h, so use 28 or more.
                </div>
                @error('auto_clockout_hours')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

                <div class="mt-3">
                    <button type="submit" class="btn-save">
                        <i class="feather-check"></i> Save
                    </button>
                </div>
            </form>
        </x-ui.modal>

        {{-- Day Classification --}}
        <x-ui.modal id="dayClassificationModal" title="Day Classification" size="md">
            <form action="{{ route('day-classification-settings.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="day_classification_enabled" name="day_classification_enabled" value="1"
                           {{ old('day_classification_enabled', $policy->dayClassificationEnabled) ? 'checked' : '' }}>
                    <label class="form-check-label field-label mb-0" for="day_classification_enabled">
                        Calculate half day / absent from hours worked
                    </label>
                </div>
                <div class="field-hint mb-3">
                    On — days are scored present / half day / absent using the values below.
                    Off — no calculation: any day with work counts as Present.
                </div>

                <div id="day_classification_fields">
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="field-label">Present ratio</label>
                        <input type="number" step="0.01" min="0.1" max="1" class="field-input"
                               name="present_ratio"
                               value="{{ old('present_ratio', $policy->presentRatio) }}">
                        <div class="field-hint">worked/scheduled &ge; this &rarr; <strong>present</strong></div>
                    </div>
                    <div class="col-6">
                        <label class="field-label">Half-day ratio</label>
                        <input type="number" step="0.01" min="0" max="1" class="field-input"
                               name="half_day_ratio"
                               value="{{ old('half_day_ratio', $policy->halfDayRatio) }}">
                        <div class="field-hint">&ge; this &rarr; <strong>half day</strong></div>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <label class="field-label">Fallback present hours</label>
                        <input type="number" step="0.25" min="1" max="24" class="field-input"
                               name="fallback_present_hours"
                               value="{{ old('fallback_present_hours', $policy->fallbackPresentHours) }}">
                        <div class="field-hint">no scheduled shift</div>
                    </div>
                    <div class="col-6">
                        <label class="field-label">Fallback half-day hours</label>
                        <input type="number" step="0.25" min="0" max="24" class="field-input"
                               name="fallback_half_hours"
                               value="{{ old('fallback_half_hours', $policy->fallbackHalfHours) }}">
                    </div>
                </div>
                </div>

                @include('client.settings.partials.apply-from')

                <div class="mt-3">
                    <button type="submit" class="btn-save">
                        <i class="feather-check"></i> Save
                    </button>
                </div>
            </form>
        </x-ui.modal>

        {{-- Late Arrival --}}
        <x-ui.modal id="lateArrivalModal" title="Late Arrival" size="md">
            <form action="{{ route('late-policy-settings.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="field-label">Grace period source</label>
                        <select class="field-input" name="grace_mode" id="late_grace_mode">
                            <option value="fixed" @selected($policy->graceMode === 'fixed')>Fixed minutes</option>
                            <option value="shift" @selected($policy->graceMode === 'shift')>As per shift grace</option>
                        </select>
                        <div class="field-hint">Shared with Early Leaving, below.</div>
                    </div>
                    <div class="col-6" id="late_fixed_grace_wrap">
                        <label class="field-label">Fixed grace minutes</label>
                        <input type="number" min="0" max="240" class="field-input" name="fixed_grace_minutes"
                               value="{{ old('fixed_grace_minutes', $policy->fixedGraceMinutes) }}">
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <label class="field-label">Monthly allowance</label>
                        <input type="number" min="0" max="31" class="field-input" name="monthly_late_allowance"
                               value="{{ old('monthly_late_allowance', $policy->monthlyLateAllowance) }}">
                    </div>
                    <div class="col-6">
                        <label class="field-label">Attendance action</label>
                        <select class="field-input" name="late_attendance_action">
                            <option value="none" @selected($policy->lateAttendanceAction === 'none')>Keep Original Attendance</option>
                            <option value="half_day" @selected($policy->lateAttendanceAction === 'half_day')>Mark half day</option>
                            <option value="absent" @selected($policy->lateAttendanceAction === 'absent')>Mark absent</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="late_deduction_enabled" name="late_deduction_enabled" value="1"
                                   {{ old('late_deduction_enabled', $policy->lateDeductionEnabled) ? 'checked' : '' }}>
                            <label class="form-check-label field-label mb-0" for="late_deduction_enabled">
                                Deduct from payroll
                            </label>
                        </div>
                    </div>
                </div>

                <div class="row g-2 mt-1" id="late_deduction_fields">
                    <div class="col-6">
                        <label class="field-label">Deduction type</label>
                        <select class="field-input" name="late_deduction_mode" id="late_deduction_mode">
                            <option value="fixed_amount" @selected($policy->lateDeductionMode === 'fixed_amount')>Fixed Amount</option>
                            <option value="half_day" @selected($policy->lateDeductionMode === 'half_day')>Half Day</option>
                            <option value="full_day" @selected($policy->lateDeductionMode === 'full_day')>Full Day</option>
                            <option value="custom_multiplier" @selected($policy->lateDeductionMode === 'custom_multiplier')>Custom Multiplier</option>
                        </select>
                        <div class="field-hint">Applied per day beyond the monthly allowance.</div>
                    </div>
                    <div class="col-6" id="late_fixed_amount_wrap">
                        <label class="field-label">Fixed amount (&#8377;)</label>
                        <input type="number" step="0.01" min="0" class="field-input" name="late_deduction_amount"
                               value="{{ old('late_deduction_amount', $policy->lateDeductionAmount) }}">
                        <div class="field-hint">Deducted per excess day.</div>
                    </div>
                    <div class="col-6" id="late_multiplier_wrap">
                        <label class="field-label">Custom multiplier</label>
                        <input type="number" step="0.1" min="0" max="5" class="field-input" name="late_deduction_multiplier"
                               value="{{ old('late_deduction_multiplier', $policy->lateDeductionMultiplier) }}">
                        <div class="field-hint">0.3x = 30% of a day's salary, 1x = full day, 1.5x = 150%.</div>
                    </div>
                </div>

                @include('client.settings.partials.apply-from')

                <div class="mt-3">
                    <button type="submit" class="btn-save">
                        <i class="feather-check"></i> Save Late Arrival Rules
                    </button>
                </div>
            </form>
        </x-ui.modal>

        {{-- Early Leaving --}}
        <x-ui.modal id="earlyLeavingModal" title="Early Leaving" size="md">
            <form action="{{ route('early-leaving-policy-settings.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row g-2">
                    <div class="col-6">
                        <label class="field-label">Monthly allowance</label>
                        <input type="number" min="0" max="31" class="field-input" name="monthly_early_allowance"
                               value="{{ old('monthly_early_allowance', $policy->monthlyEarlyAllowance) }}">
                    </div>
                    <div class="col-6">
                        <label class="field-label">Attendance action once exceeded</label>
                        <select class="field-input" name="early_attendance_action">
                            <option value="none" @selected($policy->earlyAttendanceAction === 'none')>Keep Original Attendance</option>
                            <option value="half_day" @selected($policy->earlyAttendanceAction === 'half_day')>Mark half day</option>
                            <option value="absent" @selected($policy->earlyAttendanceAction === 'absent')>Mark absent</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="early_deduction_enabled" name="early_deduction_enabled" value="1"
                                   {{ old('early_deduction_enabled', $policy->earlyDeductionEnabled) ? 'checked' : '' }}>
                            <label class="form-check-label field-label mb-0" for="early_deduction_enabled">
                                Deduct from payroll
                            </label>
                        </div>
                    </div>
                </div>

                <div class="row g-2 mt-1" id="early_deduction_fields">
                    <div class="col-6">
                        <label class="field-label">Deduction type</label>
                        <select class="field-input" name="early_deduction_mode" id="early_deduction_mode">
                            <option value="fixed_amount" @selected($policy->earlyDeductionMode === 'fixed_amount')>Fixed Amount</option>
                            <option value="half_day" @selected($policy->earlyDeductionMode === 'half_day')>Half Day</option>
                            <option value="full_day" @selected($policy->earlyDeductionMode === 'full_day')>Full Day</option>
                            <option value="custom_multiplier" @selected($policy->earlyDeductionMode === 'custom_multiplier')>Custom Multiplier</option>
                        </select>
                        <div class="field-hint">Applied per day beyond the monthly allowance.</div>
                    </div>
                    <div class="col-6" id="early_fixed_amount_wrap">
                        <label class="field-label">Fixed amount (&#8377;)</label>
                        <input type="number" step="0.01" min="0" class="field-input" name="early_deduction_amount"
                               value="{{ old('early_deduction_amount', $policy->earlyDeductionAmount) }}">
                        <div class="field-hint">Deducted per excess day.</div>
                    </div>
                    <div class="col-6" id="early_multiplier_wrap">
                        <label class="field-label">Custom multiplier</label>
                        <input type="number" step="0.1" min="0" max="5" class="field-input" name="early_deduction_multiplier"
                               value="{{ old('early_deduction_multiplier', $policy->earlyDeductionMultiplier) }}">
                        <div class="field-hint">0.3x = 30% of a day's salary, 1x = full day, 1.5x = 150%.</div>
                    </div>
                </div>

                @include('client.settings.partials.apply-from')

                <div class="mt-3">
                    <button type="submit" class="btn-save">
                        <i class="feather-check"></i> Save Early Leaving Rules
                    </button>
                </div>
            </form>
        </x-ui.modal>

        {{-- Working-time thresholds --}}
        <x-ui.modal id="workingTimeThresholdsModal" title="Working-time thresholds" size="md">
            <form action="{{ route('working-time-thresholds-settings.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row g-2">
                    <div class="col-6">
                        <label class="field-label">Min rest hours</label>
                        <input type="number" step="0.5" min="0" max="24" class="field-input"
                               name="min_rest_hours"
                               value="{{ old('min_rest_hours', $policy->minRestHours) }}">
                        <div class="field-hint">Between two shifts. Empty = no minimum.</div>
                    </div>
                    <div class="col-6">
                        <label class="field-label">Max daily hours</label>
                        <input type="number" step="0.5" min="0" max="24" class="field-input"
                               name="max_daily_hours"
                               value="{{ old('max_daily_hours', $policy->maxDailyHours) }}">
                        <div class="field-hint">Scheduled shift hours in a day. Empty = no limit.</div>
                    </div>
                    <div class="col-12">
                        <div class="field-hint">
                            Checked on every shift swap / change: an employee request that breaks them is refused;
                            an admin / manager direct swap or change shows a warning.
                        </div>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="sandwich_leave" name="sandwich_leave" value="1"
                                   {{ old('sandwich_leave', $policy->sandwichLeave) ? 'checked' : '' }}>
                            <label class="form-check-label field-label mb-0" for="sandwich_leave">
                                Sandwich-leave rule
                            </label>
                        </div>
                        <div class="field-hint">
                            On — a week-off / holiday (or a run of them) is paid only if the employee worked or was on paid
                            leave on the working day just before or just after it. Applied by both payroll engines.
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn-save">
                        <i class="feather-check"></i> Save
                    </button>
                </div>
            </form>
        </x-ui.modal>

        {{-- Request limits (RequestLimitsSettingsController) --}}
        <x-ui.modal id="requestLimitsModal" title="Request limits" size="md">
            <form action="{{ route('request-limits-settings.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row g-2">
                    <div class="col-6">
                        <label class="field-label">WFH days allowed per month</label>
                        <input type="number" min="0" max="31" step="1" class="field-input" name="wfh_max_days_per_month"
                               value="{{ old('wfh_max_days_per_month', (int) $tenant->wfh_max_days_per_month) }}">
                    </div>
                    <div class="col-6">
                        <label class="field-label">WFH notice needed (days)</label>
                        <input type="number" min="0" max="90" step="1" class="field-input" name="wfh_min_notice_days"
                               value="{{ old('wfh_min_notice_days', (int) $tenant->wfh_min_notice_days) }}">
                    </div>
                    <div class="col-6">
                        <label class="field-label">Regularization requests per month</label>
                        <input type="number" min="0" max="31" step="1" class="field-input" name="regularization_max_per_month"
                               value="{{ old('regularization_max_per_month', (int) $tenant->regularization_max_per_month) }}">
                    </div>
                    <div class="col-6">
                        <label class="field-label">Regularize at most (days back)</label>
                        <input type="number" min="0" max="365" step="1" class="field-input" name="regularization_max_days_back"
                               value="{{ old('regularization_max_days_back', (int) $tenant->regularization_max_days_back) }}">
                    </div>
                </div>
                <div class="field-hint mt-2">
                    0 = no limit. WFH days are counted per calendar month from pending + approved WFH requests (travel
                    requests are not limited); regularizations count pending + approved requests for the month of the
                    day being corrected. Applies on the web and in the mobile app. One employee can be given different
                    limits on their profile (Employee 360 → Policies).
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn-save">
                        <i class="feather-check"></i> Save
                    </button>
                </div>
            </form>
        </x-ui.modal>

@endsection

@section('script-area')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const input = document.getElementById('employee_id_prefix');
            const preview = document.getElementById('eidPreviewValue');
            const padded = '{{ str_pad('123', $paddingLength, '0', STR_PAD_LEFT) }}';
            const fallback = '{{ $defaultPrefix }}';

            input.addEventListener('input', function () {
                input.value = input.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 2);
                const prefix = input.value.length === 2 ? input.value : fallback;
                preview.textContent = prefix + padded;
            });

            function wireDeductionCard(prefix) {
                const graceMode = document.getElementById(prefix + '_grace_mode');
                const fixedGraceWrap = document.getElementById(prefix + '_fixed_grace_wrap');
                const enabledToggle = document.getElementById(prefix + '_deduction_enabled');
                const fieldsWrap = document.getElementById(prefix + '_deduction_fields');
                const modeSelect = document.getElementById(prefix + '_deduction_mode');
                const amountWrap = document.getElementById(prefix + '_fixed_amount_wrap');
                const multiplierWrap = document.getElementById(prefix + '_multiplier_wrap');

                function syncGrace() {
                    if (!graceMode || !fixedGraceWrap) return;
                    fixedGraceWrap.classList.toggle('d-none', graceMode.value !== 'fixed');
                }

                function syncMode() {
                    if (!modeSelect || !amountWrap || !multiplierWrap) return;
                    amountWrap.classList.toggle('d-none', modeSelect.value !== 'fixed_amount');
                    multiplierWrap.classList.toggle('d-none', modeSelect.value !== 'custom_multiplier');
                }

                function syncEnabled() {
                    if (!enabledToggle || !fieldsWrap) return;
                    fieldsWrap.classList.toggle('d-none', !enabledToggle.checked);
                }

                if (graceMode) {
                    graceMode.addEventListener('change', syncGrace);
                    syncGrace();
                }
                if (modeSelect) {
                    modeSelect.addEventListener('change', syncMode);
                    syncMode();
                }
                if (enabledToggle) {
                    enabledToggle.addEventListener('change', syncEnabled);
                    syncEnabled();
                }
            }

            wireDeductionCard('late');
            wireDeductionCard('early');

            // Overtime cards: show only the fields that apply.
            const otEnabled = document.getElementById('ot_enabled');
            if (otEnabled) {
                const otModeFields = document.getElementById('ot_mode_fields');
                const otAutoFields = document.getElementById('ot_auto_fields');
                const otBasis = document.getElementById('ot_start_basis');
                const otFixedWrap = document.getElementById('ot_fixed_minutes_wrap');
                const syncOt = () => {
                    const auto = document.getElementById('ot_mode_auto').checked;
                    otModeFields.classList.toggle('d-none', !otEnabled.checked);
                    otAutoFields.classList.toggle('d-none', !auto);
                    otFixedWrap.classList.toggle('d-none', otBasis.value !== 'fixed');
                };
                [otEnabled, otBasis, document.getElementById('ot_mode_auto'), document.getElementById('ot_mode_request')]
                    .forEach(el => el.addEventListener('change', syncOt));
                syncOt();

                const otRateType = document.getElementById('ot_rate_type');
                const syncRate = () => {
                    document.getElementById('ot_multiplier_wrap').classList.toggle('d-none', otRateType.value !== 'multiplier');
                    document.getElementById('ot_fixed_rate_wrap').classList.toggle('d-none', otRateType.value !== 'fixed');
                };
                otRateType.addEventListener('change', syncRate);
                syncRate();
            }

            // Day Classification: hide the thresholds while the switch is off.
            const dcToggle = document.getElementById('day_classification_enabled');
            const dcFields = document.getElementById('day_classification_fields');
            if (dcToggle && dcFields) {
                const syncDc = () => dcFields.classList.toggle('d-none', !dcToggle.checked);
                dcToggle.addEventListener('change', syncDc);
                syncDc();
            }
        });
    </script>
@endsection
