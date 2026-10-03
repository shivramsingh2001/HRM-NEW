@extends('client.layout.master')

@section('style')
<style>
    .s-card { background:#fff; border:1px solid #edf2f7; border-radius:14px; margin-bottom:20px; }
    .s-head { padding:16px 20px; border-bottom:1px solid #edf2f7; background:#fafbfc; }
    .s-head h5 { margin:0; font-size:15px; font-weight:600; }
    .s-body { padding:20px; }
    table.tbl { width:100%; font-size:12px; }
    table.tbl th, table.tbl td { padding:6px 8px; border-bottom:1px solid #eef2f7; text-align:left; }
    code.key { background:#f1f5f9; padding:2px 6px; border-radius:4px; }
</style>
@endsection

@section('content-area')
<x-ui.page-header title="API Clients" back />
<div class="page-content"><div class="container-fluid">

    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    @if (session('new_api_secret'))
        <div class="alert alert-warning">
            <strong>Copy this secret now — it will not be shown again:</strong><br>
            <code class="key">{{ session('new_api_secret') }}</code>
            <div class="mt-1" style="font-size:12px">Use it as <code>Authorization: Bearer &lt;this value&gt;</code> against <code>/api/v1/*</code>.</div>
        </div>
    @endif

    <div class="s-card">
        <div class="s-head"><h5>Active clients</h5></div>
        <div class="s-body">
            <table class="tbl">
                <thead><tr><th>Name</th><th>Key ID</th><th>Scopes</th><th>Rate/min</th><th>Last used</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($clients as $c)
                    <tr>
                        <td>{{ $c->name }}</td>
                        <td><code class="key">{{ $c->key_id }}</code></td>
                        <td>{{ implode(', ', $c->scopes ?? []) }}</td>
                        <td>{{ $c->rate_limit_per_min }}</td>
                        <td>{{ $c->last_used_at?->diffForHumans() ?? '—' }}</td>
                        <td>{!! $c->is_active ? '<span class="badge bg-success">active</span>' : '<span class="badge bg-secondary">revoked</span>' !!}</td>
                        <td>
                            @if ($c->is_active)
                            <form method="POST" action="{{ route('settings.api.rotate', $c->id) }}" class="d-inline">
                                @csrf <button class="btn btn-sm btn-outline-secondary">Rotate</button>
                            </form>
                            <form method="POST" action="{{ route('settings.api.destroy', $c->id) }}" class="d-inline"
                                  onsubmit="return confirm('Revoke this key?')">
                                @csrf @method('DELETE') <button class="btn btn-sm btn-outline-danger">Revoke</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">No API clients yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="s-card">
        <div class="s-head"><h5>Create client</h5></div>
        <div class="s-body">
            <form method="POST" action="{{ route('settings.api.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Name</label>
                        <input class="form-control" name="name" required placeholder="e.g. Payroll integration">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Rate limit / min</label>
                        <input type="number" class="form-control" name="rate_limit_per_min" value="120" min="1">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Scopes</label>
                        <div>
                            @foreach ($allScopes as $s)
                                <label class="me-3" style="font-size:12px">
                                    <input type="checkbox" name="scopes[]" value="{{ $s }}"> {{ $s }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <button class="btn btn-primary mt-3">Create</button>
            </form>
        </div>
    </div>

    <div class="s-card">
        <div class="s-head"><h5>Recent API calls</h5></div>
        <div class="s-body">
            <table class="tbl">
                <thead><tr><th>When</th><th>Method</th><th>Path</th><th>Status</th><th>ms</th><th>Idem key</th></tr></thead>
                <tbody>
                @forelse ($recent as $r)
                    <tr>
                        <td>{{ $r->created_at?->format('d M H:i:s') }}</td>
                        <td>{{ $r->method }}</td>
                        <td>{{ $r->path }}</td>
                        <td>{{ $r->status }}</td>
                        <td>{{ $r->duration_ms }}</td>
                        <td>{{ $r->idempotency_key ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">No calls logged.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div></div>
@endsection
