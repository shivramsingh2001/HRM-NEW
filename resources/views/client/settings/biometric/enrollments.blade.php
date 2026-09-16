@extends('client.layout.master')

@section('style')
<style>
    .s-card { background:#fff; border:1px solid #edf2f7; border-radius:14px; margin-bottom:20px; }
    .s-head { padding:16px 20px; border-bottom:1px solid #edf2f7; background:#fafbfc; }
    .s-head h5 { margin:0; font-size:15px; font-weight:600; }
    .s-body { padding:20px; }
    table.tbl { width:100%; font-size:12px; }
    table.tbl th, table.tbl td { padding:6px 8px; border-bottom:1px solid #eef2f7; text-align:left; }
    .pill { display:inline-block; padding:2px 8px; border-radius:20px; font-size:11px; font-weight:600; }
    .pill-synced { background:#e7f6ec; color:#1a7f37; }
    .pill-pending { background:#fff4e0; color:#a15c00; }
    .pill-removing { background:#fde8e8; color:#b42318; }
    .pill-failed { background:#fde8e8; color:#b42318; }
    .muted { color:#8a94a6; }
</style>
@endsection

@section('content-area')
<div class="page-content"><div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Employees on {{ $device->name }}</h4>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('settings.biometric.index') }}">← Devices</a>
    </div>

    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="alert alert-warning">{{ session('error') }}</div> @endif

    <div class="s-card">
        <div class="s-head d-flex justify-content-between align-items-center">
            <h5>
                Roster
                <span class="muted" style="font-weight:400">
                    &nbsp;{{ $counts['synced'] }} on device · {{ $counts['pending'] }} to push ·
                    {{ $counts['removing'] }} to remove @if($counts['failed']) · <span style="color:#b42318">{{ $counts['failed'] }} failed</span> @endif
                </span>
            </h5>
            <form method="POST" action="{{ route('settings.biometric.roster.sync', $device) }}">
                @csrf
                <button class="btn btn-sm btn-primary">Sync now</button>
            </form>
        </div>
        <div class="s-body">
            @if ($device->auto_provision)
                <p class="muted" style="font-size:12px">
                    HRM is the source of truth. Every active employee@if($device->provision_scope === 'branch') in this device's branch @else&nbsp;@endif is
                    created on the terminal automatically with their <strong>HRM user ID</strong> as the
                    device user ID — no mapping needed. Each person then just enrols their fingerprint on
                    the device against that ID. Removals happen when an employee is deactivated.
                </p>
            @else
                <div class="alert alert-warning" style="font-size:12px">
                    Auto-provision is <strong>off</strong> for this device — edit the device to enable it,
                    or use the manual mapping below.
                </div>
            @endif

            <table class="tbl">
                <thead><tr>
                    <th>Employee</th><th>Device user ID</th><th>State</th>
                    <th>Card no.</th><th>Name on device</th><th>Last synced</th><th></th>
                </tr></thead>
                <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td>
                            @if ($r->user)
                                {{ $r->user->name }} <span class="muted">({{ $r->user->employee_id }})</span>
                            @else
                                <span class="muted">enroll {{ $r->enroll_no }} — unmapped</span>
                            @endif
                            @if ($r->source === 'manual') <span class="pill" style="background:#eef1f5;color:#5b6472">manual</span> @endif
                        </td>
                        <td><code>{{ $r->device_user_id ?? $r->enroll_no }}</code></td>
                        <td>
                            <span class="pill pill-{{ $r->sync_state }}">{{ $r->sync_state }}</span>
                            @if ($r->last_error) <div class="muted" style="max-width:220px">{{ $r->last_error }}</div> @endif
                        </td>
                        <td>
                            @if ($r->user)
                                <form method="POST" action="{{ route('settings.biometric.enrollments.card', $r) }}" class="d-flex gap-1">
                                    @csrf
                                    <input class="form-control form-control-sm" name="card_number" value="{{ $r->user->card_number }}" placeholder="card #" style="width:110px">
                                    <button class="btn btn-sm btn-outline-secondary">Save</button>
                                </form>
                                @if ($r->user->card_number && $r->card_pushed === $r->user->card_number)
                                    <span class="muted" style="font-size:11px">✓ on device</span>
                                @elseif ($r->user->card_number)
                                    <span class="muted" style="font-size:11px">pending</span>
                                @endif
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td>{{ $r->name_on_device ?? $r->name_pushed ?? '—' }}</td>
                        <td class="muted">{{ optional($r->synced_at)->diffForHumans() ?? '—' }}</td>
                        <td class="text-nowrap">
                            <form method="POST" action="{{ route('settings.biometric.enrollments.repush', $r) }}" class="d-inline">
                                @csrf <button class="btn btn-sm btn-outline-primary">Re-push</button>
                            </form>
                            <form method="POST" action="{{ route('settings.biometric.enrollments.remove', $r) }}" class="d-inline">
                                @csrf <button class="btn btn-sm btn-outline-danger">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">
                        Nothing yet. Click <em>Sync now</em> to build the roster from your employee list.
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="s-card">
        <div class="s-head">
            <h5><a data-bs-toggle="collapse" href="#manualMap" role="button" class="text-decoration-none">Manual mapping (advanced) ▾</a></h5>
        </div>
        <div class="s-body collapse" id="manualMap">
            <p class="muted" style="font-size:12px">
                For odd enroll numbers that aren't an HRM user ID (e.g. a device that was already
                populated by hand). Mapped rows here are kept as <code>manual</code> and are never
                auto-removed. You can also <em>Auto-map by Employee ID</em>.
            </p>
            <form method="POST" action="{{ route('settings.biometric.enrollments.auto', $device) }}" class="mb-3">
                @csrf
                <button class="btn btn-sm btn-outline-primary">Auto-map by Employee ID</button>
            </form>
            <form method="POST" action="{{ route('settings.biometric.enrollments.map', $device) }}" class="row g-2">
                @csrf
                <div class="col-md-3"><input class="form-control form-control-sm" name="enroll_no" placeholder="enroll no." required></div>
                <div class="col-md-4">
                    <select class="form-control form-control-sm" name="user_id" required>
                        <option value="">— employee —</option>
                        @foreach ($employees as $e) <option value="{{ $e->id }}">{{ $e->name }} ({{ $e->employee_id }})</option> @endforeach
                    </select>
                </div>
                <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Add mapping</button></div>
            </form>
        </div>
    </div>
</div></div>
@endsection
