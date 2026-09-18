@extends('client.layout.master')

@section('style')
<style>
    .s-card { background:#fff; border:1px solid #edf2f7; border-radius:14px; margin-bottom:20px; }
    .s-head { padding:16px 20px; border-bottom:1px solid #edf2f7; background:#fafbfc; }
    .s-head h5 { margin:0; font-size:15px; font-weight:600; }
    .s-body { padding:20px; }
    table.tbl { width:100%; font-size:12px; }
    table.tbl th, table.tbl td { padding:6px 8px; border-bottom:1px solid #eef2f7; text-align:left; vertical-align:top; }
    code.key { background:#f1f5f9; padding:2px 6px; border-radius:4px; word-break:break-all; }
</style>
@endsection

@section('content-area')
<div class="page-content"><div class="container-fluid">
    <h4 class="mb-3">Biometric Terminals</h4>

    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="alert alert-warning">{{ session('error') }}</div> @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    @if (session('new_api_secret'))
        <div class="alert alert-warning">
            <strong>Bridge key — copy now, shown only once:</strong><br>
            <code class="key">{{ session('new_api_secret') }}</code>
            <div class="mt-1" style="font-size:12px">Put this in the bridge's <code>appsettings.json</code> as <code>Hrm.ApiKey</code>.</div>
        </div>
    @endif

    <div class="s-card">
        <div class="s-head"><h5>Devices</h5></div>
        <div class="s-body">
            <p class="text-muted" style="font-size:12px">
                Each terminal is polled by a Windows "bridge" service that runs the vendor SDK and
                posts punches to <code>/api/v1/biometric/punches</code>. Register the device here,
                generate its bridge key, then map its enroll numbers to employees.
            </p>
            <table class="tbl">
                <thead><tr>
                    <th>Name</th><th>Serial</th><th>Mode</th><th>Direction</th>
                    <th>Mapped / Unmapped</th><th>Last seen</th><th>Last punch</th><th>Status</th><th></th>
                </tr></thead>
                <tbody>
                @forelse ($devices as $d)
                    <tr>
                        <td>{{ $d->name }}</td>
                        <td><code>{{ $d->serial_number }}</code></td>
                        <td>{{ $d->p2p_uid ? 'P2P' : 'LAN' }}{{ $d->ip_address ? ' · '.$d->ip_address : '' }}</td>
                        <td>{{ $d->direction_mode }}</td>
                        <td>{{ $d->mapped_count }} / {{ $d->unmapped_count }}</td>
                        <td>{{ $d->last_seen_at?->diffForHumans() ?? '—' }}</td>
                        <td>{{ $d->last_punch_at?->diffForHumans() ?? '—' }}</td>
                        <td>{!! $d->is_active ? '<span class="badge bg-success">active</span>' : '<span class="badge bg-secondary">disabled</span>' !!}</td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('settings.biometric.enrollments', $d) }}">Map users</a>
                            <form method="POST" action="{{ route('settings.biometric.devices.bridge-key', $d) }}" class="d-inline">
                                @csrf <button class="btn btn-sm btn-outline-secondary">Bridge key</button>
                            </form>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('settings.biometric.devices.config', $d) }}">Config</a>
                            <form method="POST" action="{{ route('settings.biometric.devices.destroy', $d) }}" class="d-inline"
                                  onsubmit="return confirm('Remove this device and its mappings?')">
                                @csrf @method('DELETE') <button class="btn btn-sm btn-outline-danger">×</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-muted">No devices yet.</td></tr>
                @endforelse
                </tbody>
            </table>
            <a class="btn btn-sm btn-link mt-2" href="{{ route('settings.biometric.punches') }}">View recent punches →</a>
        </div>
    </div>

    <div class="s-card">
        <div class="s-head"><h5>Add device</h5></div>
        <div class="s-body">
            <form method="POST" action="{{ route('settings.biometric.devices.store') }}" class="row g-3">
                @csrf
                <div class="col-md-3">
                    <label class="form-label">Serial number</label>
                    <input class="form-control" name="serial_number" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Name</label>
                    <input class="form-control" name="name" required placeholder="Front gate">
                </div>
                <div class="col-md-2">
                    <label class="form-label">LAN IP <span class="text-muted">(or blank)</span></label>
                    <input class="form-control" name="ip_address" placeholder="192.168.1.50">
                </div>
                <div class="col-md-2">
                    <label class="form-label">P2P UID <span class="text-muted">(or blank)</span></label>
                    <input class="form-control" name="p2p_uid" placeholder="15FADA4F825D1010">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Site timezone</label>
                    <select class="form-control" name="site_timezone">
                        <option value="">—</option>
                        @foreach ($timezones as $tz) <option value="{{ $tz }}">{{ $tz }}</option> @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Attendance Location</label>
                    <select class="form-control" name="branch_id">
                        <option value="">—</option>
                        @foreach ($branches as $b) <option value="{{ $b->id }}">{{ $b->name }}</option> @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Direction mode</label>
                    <select class="form-control" name="direction_mode">
                        <option value="auto">auto (first punch = in)</option>
                        <option value="by_verify_mode">from device verify-mode</option>
                        <option value="in">this reader is IN only</option>
                        <option value="out">this reader is OUT only</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Auto-provision employees</label>
                    <select class="form-control" name="auto_provision">
                        <option value="1">on — push HRM employees to the device</option>
                        <option value="0">off — map enroll numbers by hand</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Provision scope</label>
                    <select class="form-control" name="provision_scope">
                        <option value="tenant">all active employees</option>
                        <option value="branch">only this device's attendance location</option>
                    </select>
                </div>
                <div class="col-12"><button class="btn btn-primary">Add device</button></div>
            </form>
        </div>
    </div>
</div></div>
@endsection
