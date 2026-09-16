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
        .weekday-grid { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 8px; }
        .weekday-grid .form-check { margin: 0; }
    </style>
@endsection

@section('content-area')
    <div class="page-content">
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h4 class="mb-0">Shift Settings</h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Shift Settings</li>
                    </ol>
                </nav>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
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
                <div class="policy-card">
                    <div class="policy-header">
                        <h5>Shift Mode</h5>
                        <p>Choose whether the whole company runs on one fixed shift, or each
                           employee is assigned shifts per day.</p>
                    </div>
                    <div class="policy-body">
                        <form action="{{ route('shift-settings.update') }}" method="POST" id="shiftSettingsForm">
                            @csrf
                            @method('PUT')

                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="custom_shifts_enabled" name="custom_shifts_enabled" value="1"
                                       {{ old('custom_shifts_enabled', $tenant->custom_shifts_enabled) ? 'checked' : '' }}>
                                <label class="form-check-label" for="custom_shifts_enabled">
                                    Use custom shifts (per-employee / per-date assignment)
                                </label>
                                <div class="form-text">
                                    When off, the fixed company shift below applies to everyone and the
                                    Shifts &amp; Assignment screen is hidden.
                                </div>
                            </div>

                            <div id="fixedShiftFields">
                                <h6 class="mb-3" style="font-weight:600;color:#1e293b;">Fixed company shift</h6>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label for="start_time" class="form-label">Start time</label>
                                        <input type="time" class="form-control" id="start_time" name="start_time"
                                               value="{{ old('start_time', $defaultShift?->start_time ? \Carbon\Carbon::parse($defaultShift->start_time)->format('H:i') : '09:00') }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="end_time" class="form-label">End time</label>
                                        <input type="time" class="form-control" id="end_time" name="end_time"
                                               value="{{ old('end_time', $defaultShift?->end_time ? \Carbon\Carbon::parse($defaultShift->end_time)->format('H:i') : '18:00') }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="grace_minutes" class="form-label">Grace period (minutes)</label>
                                        <input type="number" min="0" max="120" class="form-control"
                                               id="grace_minutes" name="grace_minutes"
                                               value="{{ old('grace_minutes', $defaultShift?->grace_minutes ?? 0) }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="break_time" class="form-label">Break (minutes)</label>
                                        <input type="number" min="0" max="180" class="form-control"
                                               id="break_time" name="break_time"
                                               value="{{ old('break_time', $defaultShift?->break_time ?? 0) }}">
                                    </div>
                                </div>

                                <label class="form-label">Weekly offs</label>
                                <div class="form-text">Applied to every active employee.</div>
                                <div class="weekday-grid">
                                    @php
                                        $selectedDays = old('weekoff_days', $tenant->default_weekoff_days ?? []);
                                    @endphp
                                    @foreach ($weekdays as $day)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                   name="weekoff_days[]" value="{{ $day }}"
                                                   id="weekoff_{{ $day }}"
                                                   {{ in_array($day, (array) $selectedDays, true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="weekoff_{{ $day }}">{{ $day }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary mt-4">Save Settings</button>
                        </form>
                    </div>
                </div>

                <p class="text-muted" style="font-size: 12px">
                    Switching to custom shifts keeps the fixed shift as a normal, editable shift.
                </p>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        (function () {
            var toggle = document.getElementById('custom_shifts_enabled');
            var fields = document.getElementById('fixedShiftFields');
            function sync() { fields.style.display = toggle.checked ? 'none' : ''; }
            if (toggle && fields) {
                toggle.addEventListener('change', sync);
                sync();
            }
        })();
    </script>
@endsection
