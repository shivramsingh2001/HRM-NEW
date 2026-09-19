@extends('client.layout.master')

@section('style')
    <style>
        /* ============================================
           SHIFT SETTINGS — COMPACT REFINED DESIGN
           ============================================ */

        /* ============ STAT CARDS ============ */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 10px;
            margin-bottom: 14px;
        }

        .stat-tile {
            background: #fff;
            border: 1px solid #edf2f7;
            border-radius: 10px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all .15s ease;
        }

        .stat-tile:hover {
            border-color: var(--primary-light, #e3edfe);
            box-shadow: 0 2px 8px rgba(30, 58, 138, 0.05);
        }

        .stat-tile__icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-light, #e3edfe);
            color: var(--primary, #1e3a8a);
            font-size: 14px;
            flex-shrink: 0;
        }

        .stat-tile__body {
            display: flex;
            flex-direction: column;
            min-width: 0;
            flex: 1;
        }

        .stat-tile__label {
            font-size: 10px;
            font-weight: 500;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            line-height: 1.2;
            margin-bottom: 2px;
        }

        .stat-tile__value {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ============ MAIN CARD ============ */
        .shift-card {
            background: #fff;
            border: 1px solid #edf2f7;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 14px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
        }

        .shift-card__head {
            padding: 12px 18px;
            border-bottom: 1px solid #f1f5f9;
            background: #fafbfc;
        }

        .shift-card__title {
            margin: 0;
            font-size: 13.5px;
            font-weight: 600;
            color: #1e293b;
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .shift-card__title i {
            font-size: 14px;
            color: var(--primary, #1e3a8a);
        }

        .shift-card__desc {
            margin: 3px 0 0;
            font-size: 11px;
            color: #64748b;
            line-height: 1.45;
        }

        .shift-card__body {
            padding: 18px;
        }

        /* ============ MODE SELECTOR ============ */
        .mode-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .mode-tile {
            position: relative;
            display: block;
            padding: 14px 16px;
            border-radius: 10px;
            border: 1.5px solid #e2e8f0;
            background: #fff;
            cursor: pointer;
            transition: all .15s ease;
        }

        .mode-tile:hover {
            border-color: var(--border-focus, #93c5fd);
            background: #fafcff;
        }

        .mode-tile input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .mode-tile.is-active {
            border-color: var(--primary, #1e3a8a);
            background: #f5f9ff;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.06);
        }

        .mode-tile__icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-light, #e3edfe);
            color: var(--primary, #1e3a8a);
            font-size: 13px;
            margin-bottom: 8px;
            transition: all .15s ease;
        }

        .mode-tile.is-active .mode-tile__icon {
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            color: #fff;
        }

        .mode-tile__name {
            font-weight: 600;
            font-size: 12.5px;
            margin: 0 0 3px;
            color: #1e293b;
            line-height: 1.3;
        }

        .mode-tile__text {
            font-size: 10.5px;
            color: #64748b;
            margin: 0;
            line-height: 1.4;
        }

        .mode-tile__check {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            border: 1.5px solid #cbd5e1;
            background: #fff;
            transition: all .15s ease;
        }

        .mode-tile.is-active .mode-tile__check {
            border-color: var(--primary, #1e3a8a);
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
        }

        .mode-tile.is-active .mode-tile__check::after {
            content: '';
            position: absolute;
            left: 4px;
            top: 1.5px;
            width: 4px;
            height: 7.5px;
            border: solid #fff;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        /* ============ HINT BAR ============ */
        .hint-bar {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 9px 12px;
            border-radius: 8px;
            background: #f8fafc;
            border-left: 3px solid var(--primary, #1e3a8a);
            margin: 12px 0 16px;
        }

        .hint-bar i {
            font-size: 12px;
            color: var(--primary, #1e3a8a);
            margin-top: 2px;
            flex-shrink: 0;
        }

        .hint-bar span {
            font-size: 11px;
            color: #475569;
            line-height: 1.5;
        }

        /* ============ SECTION LABEL ============ */
        .section-label {
            font-size: 11.5px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 10px;
            padding-bottom: 7px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .section-label i {
            font-size: 12px;
            color: var(--primary, #1e3a8a);
        }

        /* ============ FORM FIELDS ============ */
        .field-label {
            font-size: 11.5px;
            font-weight: 500;
            color: #334155;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .field-label i {
            font-size: 11px;
            color: #94a3b8;
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

        .field-input:hover {
            border-color: #cbd5e1;
        }

        .field-input:focus {
            border-color: var(--primary, #1e3a8a);
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.08);
        }

        .field-hint {
            font-size: 10.5px;
            color: #94a3b8;
            margin-top: 2px;
        }

        /* ============ WEEKDAY PILLS ============ */
        .day-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 6px;
        }

        .day-pill {
            position: relative;
        }

        .day-pill input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .day-pill span {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            border: 1px solid #e2e8f0;
            font-size: 10.5px;
            font-weight: 500;
            color: #475569;
            cursor: pointer;
            transition: all .15s ease;
            line-height: 1.4;
            user-select: none;
        }

        .day-pill span:hover {
            border-color: var(--border-focus, #93c5fd);
            background: #f5f9ff;
        }

        .day-pill input:checked + span {
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            border-color: var(--primary, #1e3a8a);
            color: #fff;
        }

        .day-pill input:focus-visible + span {
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.15);
        }

        /* ============ INFO NOTE ============ */
        .info-bar {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            padding: 11px 14px;
            background: #f8fafc;
            border: 1px solid #edf2f7;
            border-radius: 10px;
            font-size: 11px;
            color: #64748b;
            line-height: 1.55;
        }

        .info-bar i {
            color: var(--primary, #1e3a8a);
            font-size: 12px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .info-bar strong {
            color: #334155;
            font-weight: 600;
        }

        /* ============ BUTTONS ============ */
        .btn-save {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
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
            box-shadow: 0 2px 8px rgba(30, 58, 138, 0.3);
        }

        .btn-save:active {
            transform: translateY(1px);
        }

        .btn-ghost {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            color: #fff;
            border: 1px solid var(--primary, #1e3a8a);
            font-size: 11.5px;
            font-weight: 500;
            padding: 5px 12px;
            border-radius: 7px;
            text-decoration: none;
            cursor: pointer;
            transition: all .15s ease;
        }

        .btn-ghost:hover {
            filter: brightness(0.9);
            color: #fff;
            box-shadow: 0 2px 8px rgba(30, 58, 138, 0.3);
        }

        /* ============ ALERTS ============ */
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

        .alert-box i {
            font-size: 13px;
            margin-top: 1px;
            flex-shrink: 0;
        }

        .alert-box--success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #dcfce7;
        }

        .alert-box--success i {
            color: #16a34a;
        }

        .alert-box--danger {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fee2e2;
        }

        .alert-box--danger i {
            color: #dc2626;
        }

        .alert-box ul {
            margin: 4px 0 0;
            padding-left: 16px;
        }

        .alert-box ul li {
            margin-bottom: 2px;
        }

        /* ============ SUBMIT ROW ============ */
        .submit-row {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
        }

        .submit-row__note {
            font-size: 10.5px;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .submit-row__note i {
            font-size: 11px;
        }

        /* ============ RESPONSIVE ============ */
        @media (max-width: 768px) {
            .shift-card__body { padding: 14px; }
            .shift-card__head { padding: 11px 14px; }
            .mode-tile { padding: 12px 13px; }
            .stat-grid { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 640px) {
            .mode-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 480px) {
            .stat-grid { grid-template-columns: 1fr; }
            .submit-row { flex-direction: column; align-items: flex-start; gap: 10px; }
        }

        /* ============ UTILITIES ============ */
        .fade-toggle {
            transition: opacity .2s ease;
        }
    </style>
@endsection

@section('content-area')
    @php
        $customEnabled = (bool) old('custom_shifts_enabled', $tenant->custom_shifts_enabled);
    @endphp

    <x-ui.page-header title="Shift Settings">
        <x-slot:actions>
            @if ($customEnabled)
                <a href="{{ route('shift.roster') }}" class="btn-ghost">
                    <i class="feather-calendar"></i> Shift Roster
                </a>
                <a href="{{ route('shift.index') }}" class="btn-ghost">
                    <i class="feather-list"></i> Manage Shifts
                </a>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 20px !important;">

            {{-- ========== ALERTS ========== --}}
            @if (session('success'))
                <div class="alert-box alert-box--success">
                    <i class="feather-check-circle"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="alert-box alert-box--danger">
                    <i class="feather-alert-circle"></i>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert-box alert-box--danger">
                    <i class="feather-alert-triangle"></i>
                    <div>
                        <strong>Please fix the following:</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            {{-- ========== STAT TILES ========== --}}
                <div class="stat-grid">
                    <div class="stat-tile">
                        <div class="stat-tile__icon">
                            <i class="feather-{{ $customEnabled ? 'sliders' : 'users' }}"></i>
                        </div>
                        <div class="stat-tile__body">
                            <span class="stat-tile__label">Current Mode</span>
                            <span class="stat-tile__value">{{ $customEnabled ? 'Custom' : 'Fixed' }}</span>
                        </div>
                    </div>

                    @if ($customEnabled)
                        <div class="stat-tile">
                            <div class="stat-tile__icon">
                                <i class="feather-clock"></i>
                            </div>
                            <div class="stat-tile__body">
                                <span class="stat-tile__label">Active Shift Types</span>
                                <span class="stat-tile__value">{{ $activeShiftsCount }}</span>
                            </div>
                        </div>
                        <div class="stat-tile">
                            <div class="stat-tile__icon">
                                <i class="feather-calendar"></i>
                            </div>
                            <div class="stat-tile__body">
                                <span class="stat-tile__label">Assigned This Month</span>
                                <span class="stat-tile__value">{{ $assignedThisMonth }}</span>
                            </div>
                        </div>
                    @else
                        <div class="stat-tile">
                            <div class="stat-tile__icon">
                                <i class="feather-clock"></i>
                            </div>
                            <div class="stat-tile__body">
                                <span class="stat-tile__label">Company Hours</span>
                                <span class="stat-tile__value">
                                    {{ $defaultShift
                                        ? \Carbon\Carbon::parse($defaultShift->start_time)->format('h:i A') . ' – ' . \Carbon\Carbon::parse($defaultShift->end_time)->format('h:i A')
                                        : 'Not set' }}
                                </span>
                            </div>
                        </div>
                        <div class="stat-tile">
                            <div class="stat-tile__icon">
                                <i class="feather-users"></i>
                            </div>
                            <div class="stat-tile__body">
                                <span class="stat-tile__label">Employees Covered</span>
                                <span class="stat-tile__value">{{ $employeeCount }}</span>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- ========== MAIN CARD ========== --}}
                <div class="shift-card">
                    <div class="shift-card__head">
                        <h5 class="shift-card__title">
                            <i class="feather-sliders"></i>
                            Shift Mode
                        </h5>
                        <p class="shift-card__desc">
                            Choose whether the whole company runs on one fixed shift, or each
                            employee is assigned shifts individually — with Permanent or Flexible
                            assignments and full shift history.
                        </p>
                    </div>

                    <div class="shift-card__body">
                        <form action="{{ route('shift-settings.update') }}" method="POST" id="shiftSettingsForm">
                            @csrf
                            @method('PUT')

                            {{-- Mode selector --}}
                            <div class="mode-grid">
                                <label class="mode-tile {{ $customEnabled ? '' : 'is-active' }}" data-mode="0">
                                    <input type="radio" name="custom_shifts_enabled" value="0"
                                        {{ $customEnabled ? '' : 'checked' }}>
                                    <span class="mode-tile__check"></span>
                                    <span class="mode-tile__icon"><i class="feather-users"></i></span>
                                    <h6 class="mode-tile__name">Fixed Company Shift</h6>
                                    <p class="mode-tile__text">Every employee works the same hours. Simple to set up — nothing to assign per person.</p>
                                </label>

                                <label class="mode-tile {{ $customEnabled ? 'is-active' : '' }}" data-mode="1">
                                    <input type="radio" name="custom_shifts_enabled" value="1"
                                        {{ $customEnabled ? 'checked' : '' }}>
                                    <span class="mode-tile__check"></span>
                                    <span class="mode-tile__icon"><i class="feather-sliders"></i></span>
                                    <h6 class="mode-tile__name">Custom Per-Employee Shifts</h6>
                                    <p class="mode-tile__text">Assign Permanent (standing) or Flexible (day-specific) shifts per employee.</p>
                                </label>
                            </div>

                            {{-- Hint --}}
                            <div class="hint-bar">
                                <i class="feather-info"></i>
                                <span id="modeHintText"></span>
                            </div>

                            {{-- Fixed shift fields --}}
                            <div id="fixedShiftFields" class="fade-toggle">
                                <div class="section-label">
                                    <i class="feather-sun"></i>
                                    Fixed company shift
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label for="start_time" class="field-label">
                                            <i class="feather-log-in"></i> Start time
                                        </label>
                                        <input type="time" class="field-input" id="start_time" name="start_time"
                                            value="{{ old('start_time', $defaultShift?->start_time ? \Carbon\Carbon::parse($defaultShift->start_time)->format('H:i') : '09:00') }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="end_time" class="field-label">
                                            <i class="feather-log-out"></i> End time
                                        </label>
                                        <input type="time" class="field-input" id="end_time" name="end_time"
                                            value="{{ old('end_time', $defaultShift?->end_time ? \Carbon\Carbon::parse($defaultShift->end_time)->format('H:i') : '18:00') }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="grace_minutes" class="field-label">
                                            <i class="feather-clock"></i> Grace period
                                        </label>
                                        <input type="number" min="0" max="120" class="field-input"
                                            id="grace_minutes" name="grace_minutes"
                                            value="{{ old('grace_minutes', $defaultShift?->grace_minutes ?? 0) }}">
                                        <div class="field-hint">Minutes allowed after start time</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="break_time" class="field-label">
                                            <i class="feather-coffee"></i> Break duration
                                        </label>
                                        <input type="number" min="0" max="180" class="field-input"
                                            id="break_time" name="break_time"
                                            value="{{ old('break_time', $defaultShift?->break_time ?? 0) }}">
                                        <div class="field-hint">Total break minutes per day</div>
                                    </div>
                                </div>

                                <div class="mb-1">
                                    <label class="field-label">
                                        <i class="feather-calendar"></i> Weekly offs
                                    </label>
                                    <div class="field-hint mb-1">Applied to every active employee.</div>
                                    <div class="day-pills">
                                        @php
                                            $selectedDays = old('weekoff_days', $tenant->default_weekoff_days ?? []);
                                        @endphp
                                        @foreach ($weekdays as $day)
                                            <label class="day-pill">
                                                <input type="checkbox"
                                                    name="weekoff_days[]" value="{{ $day }}"
                                                    id="weekoff_{{ $day }}"
                                                    {{ in_array($day, (array) $selectedDays, true) ? 'checked' : '' }}>
                                                <span>{{ $day }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            {{-- Submit --}}
                            <div class="submit-row">
                                <button type="submit" class="btn-save">
                                    <i class="feather-check"></i> Save Settings
                                </button>
                                <span class="submit-row__note">
                                    <i class="feather-shield"></i>
                                    Changes apply immediately
                                </span>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- ========== INFO NOTE ========== --}}
                <div class="info-bar">
                    <i class="feather-info"></i>
                    <span>
                        <strong>Good to know:</strong>
                        Switching to Custom keeps the fixed shift as a normal, editable shift
                        definition — nothing is lost. Switching back to Fixed doesn't delete any
                        employee's existing shift assignments; they're simply ignored while Fixed
                        mode is on.
                    </span>
                </div>

    </div>
@endsection

@section('script-area')
    <script>
        (function () {
            'use strict';

            var radios     = document.querySelectorAll('input[name="custom_shifts_enabled"]');
            var tiles      = document.querySelectorAll('.mode-tile');
            var fields     = document.getElementById('fixedShiftFields');
            var hintText   = document.getElementById('modeHintText');

            var HINT_CUSTOM = 'The fixed shift below stays as a normal, editable shift definition. Assign shifts per employee from the Shift Roster page after saving.';
            var HINT_FIXED  = 'Applies to every active employee. The Shift Roster and Manage Shifts screens stay hidden until you switch to Custom.';

            function sync() {
                var checked  = document.querySelector('input[name="custom_shifts_enabled"]:checked');
                var isCustom = !!checked && checked.value === '1';

                // Update tile active state
                tiles.forEach(function (tile) {
                    tile.classList.toggle('is-active', tile.dataset.mode === (isCustom ? '1' : '0'));
                });

                // Toggle fixed fields visibility with a subtle fade
                if (isCustom) {
                    fields.style.opacity = '0';
                    window.setTimeout(function () {
                        fields.style.display = 'none';
                        fields.style.opacity = '';
                    }, 150);
                } else {
                    fields.style.display = '';
                    fields.style.opacity = '0';
                    window.requestAnimationFrame(function () {
                        fields.style.opacity = '1';
                    });
                }

                // Update hint copy
                hintText.textContent = isCustom ? HINT_CUSTOM : HINT_FIXED;
            }

            radios.forEach(function (radio) {
                radio.addEventListener('change', sync);
            });

            // Initial state
            sync();
        })();
    </script>
@endsection