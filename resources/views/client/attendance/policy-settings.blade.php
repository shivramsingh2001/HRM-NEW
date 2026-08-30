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
    <div class="page-content">
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h4 class="mb-0">Attendance Policy</h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Attendance Policy</li>
                    </ol>
                </nav>
            </div>

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
                <div class="policy-card">
                    <div class="policy-header">
                        <h5>Monthly Late Allowance</h5>
                        <p>Automatically record a day as <strong>half day</strong> once an employee's late
                           arrivals in a calendar month go over the allowance.</p>
                    </div>
                    <div class="policy-body">
                        <form action="{{ route('attendance-policy.update') }}" method="POST">
                            @csrf
                            @method('PUT')

                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="late_halfday_enabled" name="late_halfday_enabled" value="1"
                                       {{ old('late_halfday_enabled', $tenant->late_halfday_enabled) ? 'checked' : '' }}>
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
                                       value="{{ old('monthly_late_allowance', $tenant->monthly_late_allowance) }}"
                                       placeholder="Unlimited">
                                <div class="form-text">
                                    Leave blank for <strong>unlimited</strong> (never auto-convert).
                                </div>
                                <ul class="rule-list">
                                    <li><strong>Blank</strong> &mdash; late days are always kept as "late".</li>
                                    <li><strong>0</strong> &mdash; every late day becomes a half day.</li>
                                    <li><strong>N</strong> &mdash; the first N late days in a month are fine;
                                        the (N+1)th and every later late day become a half day.</li>
                                </ul>
                            </div>

                            <button type="submit" class="btn btn-primary">Save Policy</button>
                        </form>
                    </div>
                </div>

                <p class="text-muted" style="font-size: 12px">
                    Changes take effect the next time attendance summaries are recalculated
                    (nightly, or immediately when an affected employee clocks in/out).
                </p>
            </div>
        </div>
    </div>
@endsection
