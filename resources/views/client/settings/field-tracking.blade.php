@extends('client.layout.master')

@section('style')
    <style>
        .policy-container { max-width: 760px; margin: 0 auto; }
        .policy-card { background:#fff; border:1px solid #edf2f7; border-radius:16px; overflow:hidden; margin-bottom:24px; }
        .policy-header { padding:20px 24px; border-bottom:1px solid #edf2f7; background:#fafbfc; }
        .policy-header h5 { margin:0; font-size:16px; font-weight:600; color:#1e293b; }
        .policy-header p { margin:4px 0 0; font-size:12px; color:#64748b; }
        .policy-body { padding:24px; }
        .policy-body .form-text { font-size:12px; color:#64748b; }
        .seat-line { display:flex; gap:24px; flex-wrap:wrap; }
        .seat-line .box { background:#f8fafc; border-radius:10px; padding:14px 18px; min-width:150px; }
        .seat-line .box .n { font-size:22px; font-weight:700; }
        .seat-line .box .l { font-size:12px; color:#64748b; }
        .seat-over { color:#b91c1c; }
        .user-chips span { display:inline-block; background:#eef2ff; color:#3730a3; font-size:12px;
            padding:3px 10px; border-radius:999px; margin:0 6px 6px 0; }
    </style>
@endsection

@section('content-area')
<div class="page-content"><div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Field Tracking</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Field Tracking</li>
            </ol>
        </nav>
    </div>

    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="alert alert-warning">{{ session('error') }}</div> @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">
            @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
        </ul></div>
    @endif

    <div class="policy-container">
        <div class="policy-card">
            <div class="policy-header">
                <h5>Subscription</h5>
                <p>Continuous GPS tracking is a paid add-on, billed per tracked employee. These
                   figures are managed by your subscription &mdash; contact us to change them.</p>
            </div>
            <div class="policy-body">
                <div class="seat-line mb-3">
                    <div class="box">
                        <div class="n">{{ $tenant->field_tracking_enabled ? 'On' : 'Off' }}</div>
                        <div class="l">add-on status</div>
                    </div>
                    <div class="box">
                        <div class="n">{{ $tenant->field_tracking_seats }}</div>
                        <div class="l">seats purchased</div>
                    </div>
                    <div class="box">
                        <div class="n {{ $seatsUsed > $tenant->field_tracking_seats ? 'seat-over' : '' }}">{{ $seatsUsed }}</div>
                        <div class="l">seats in use</div>
                    </div>
                </div>

                @if ($enabledUsers->isNotEmpty())
                    <div class="user-chips">
                        <div class="form-text mb-1">Currently tracked:</div>
                        @foreach ($enabledUsers as $u)
                            <span>{{ $u->name }} <small class="text-muted">{{ $u->employee_id }}</small></span>
                        @endforeach
                    </div>
                @else
                    <div class="form-text">No employees are being tracked. Assign them from the
                        <a href="{{ route('employee.index') }}">Employees</a> list.</div>
                @endif
            </div>
        </div>

        <div class="policy-card">
            <div class="policy-header">
                <h5>Tracking behaviour</h5>
                <p>How often tracked devices report their location, and how long the movement
                   history is kept.</p>
            </div>
            <div class="policy-body">
                <form action="{{ route('settings.field-tracking.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="ping" class="form-label">Ping interval (seconds)</label>
                        <input type="number" class="form-control" style="max-width:180px"
                               id="ping" name="field_tracking_ping_seconds"
                               min="{{ config('location.min_ping_seconds', 30) }}"
                               max="{{ config('location.max_ping_seconds', 900) }}"
                               value="{{ old('field_tracking_ping_seconds', $tenant->field_tracking_ping_seconds) }}">
                        <div class="form-text">
                            Minimum {{ config('location.min_ping_seconds', 30) }}s. A shorter interval
                            gives smoother tracks but more battery and server load.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="retention" class="form-label">Retention (days)</label>
                        <input type="number" class="form-control" style="max-width:180px"
                               id="retention" name="field_tracking_retention_days"
                               min="{{ config('location.retention_floor_days', 30) }}" max="730"
                               value="{{ old('field_tracking_retention_days', $tenant->field_tracking_retention_days) }}">
                        <div class="form-text">
                            Movement breadcrumbs older than this are removed nightly (a backup is
                            taken first). Clock-in / clock-out locations are never removed.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </form>
            </div>
        </div>
    </div>
</div></div>
@endsection
