@extends('client.layout.master')

@section('style')
<style>
    .form-section { background: #fff; border: 1px solid #eaeef5; border-radius: 10px; padding: 16px; margin-bottom: 14px; }
    .form-section h6 { font-size: 12px; font-weight: 700; color: #1a2236; text-transform: uppercase; letter-spacing: .03em; margin-bottom: 12px; }
    .readiness-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f4f6fb; font-size: 12px; }
    .readiness-row:last-child { border-bottom: none; }
    .engine-badge { font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 999px; }
</style>
@endsection

@section('content-area')
    <div class="content-area-header sticky-top">
        <div class="page-header-left d-flex align-items-center gap-2">
            <div class="page-header-title">
                <h5 class="m-b-10">Payroll Engine Settings</h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.57rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Payroll Engine Settings</li>
            </ul>
        </div>
    </div>

    <div class="content-area-body">
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

        <div class="form-section">
            <h6>Current Engine</h6>
            <p>
                This company is currently on the
                <span class="engine-badge {{ $tenant->payroll_dynamic_ui_enabled ? 'bg-success text-white' : 'bg-secondary text-white' }}">
                    {{ $tenant->payroll_dynamic_ui_enabled ? 'Dynamic Payroll Engine' : 'Legacy Payroll Engine' }}
                </span>
                for new "Process Payroll" runs. Historical payslips already generated are never affected by this
                setting — every payslip records which engine actually produced it.
            </p>
        </div>

        <div class="form-section">
            <h6>Cutover Readiness</h6>
            <div class="readiness-row">
                <span>Active employees</span>
                <strong>{{ $activeEmployees }}</strong>
            </div>
            <div class="readiness-row">
                <span>Employees with a dynamic payroll structure assigned</span>
                <strong class="{{ $withDynamicStructure < $activeEmployees ? 'text-warning' : 'text-success' }}">
                    {{ $withDynamicStructure }} / {{ $activeEmployees }}
                </strong>
            </div>
            <div class="readiness-row">
                <span>Employees with a legacy payroll assignment</span>
                <strong>{{ $withLegacyPayroll }} / {{ $activeEmployees }}</strong>
            </div>
            <div class="readiness-row">
                <span>Most recent legacy-engine payslip month</span>
                <strong>{{ $lastLegacyMonth ?? '—' }}</strong>
            </div>
            <div class="readiness-row">
                <span>Most recent dynamic-engine payslip month</span>
                <strong>{{ $lastDynamicMonth ?? '—' }}</strong>
            </div>

            @if ($withDynamicStructure < $activeEmployees)
                <div class="alert alert-warning mt-3" style="font-size:11px;">
                    <i class="feather-alert-triangle me-1"></i>
                    {{ $activeEmployees - $withDynamicStructure }} active employee(s) don't have a dynamic payroll structure
                    yet — assign one via <a href="{{ route('payroll-employee-structures.index') }}">Employee Payroll Structures</a>
                    before switching, or they won't be eligible for a dynamic payroll run.
                </div>
            @endif
        </div>

        <div class="form-section">
            <h6>Switch Engine</h6>
            <p style="font-size:11px;" class="text-muted">
                Before enabling the dynamic engine, it's strongly recommended to run
                <code>php artisan payroll:engine-diff</code> against a recent month and confirm there's no material drift
                against what was actually paid. This switch takes effect on the next "Process Payroll" run — it does not
                reprocess anything already generated.
            </p>
            <form action="{{ route('payroll-engine-settings.update') }}" method="POST" onsubmit="return confirm('Are you sure you want to switch the payroll engine for this company?');">
                @csrf
                <input type="hidden" name="enable_dynamic_engine" value="{{ $tenant->payroll_dynamic_ui_enabled ? 0 : 1 }}">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="confirm" name="confirm" value="1" required>
                    <label class="form-check-label" for="confirm" style="font-size:11.5px;">
                        I understand this changes which engine processes new payroll runs for this company.
                    </label>
                </div>
                <button type="submit" class="btn {{ $tenant->payroll_dynamic_ui_enabled ? 'btn-secondary' : 'btn-primary' }}">
                    {{ $tenant->payroll_dynamic_ui_enabled ? 'Switch back to Legacy Engine' : 'Switch to Dynamic Engine' }}
                </button>
            </form>
        </div>
    </div>
@endsection
