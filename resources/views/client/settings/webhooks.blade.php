@extends('client.layout.master')

@section('style')
<style>
    .s-card { background:#fff; border:1px solid #edf2f7; border-radius:14px; margin-bottom:20px; }
    .s-head { padding:16px 20px; border-bottom:1px solid #edf2f7; background:#fafbfc; }
    .s-head h5 { margin:0; font-size:15px; font-weight:600; }
    .s-body { padding:20px; }
    table.tbl { width:100%; font-size:12px; }
    table.tbl th, table.tbl td { padding:6px 8px; border-bottom:1px solid #eef2f7; text-align:left; }
</style>
@endsection

@section('content-area')
<div class="page-content"><div class="container-fluid">
    <h4 class="mb-3">Webhooks</h4>

    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="s-card">
        <div class="s-head"><h5>Endpoints</h5></div>
        <div class="s-body">
            <table class="tbl">
                <thead><tr><th>URL</th><th>Events</th><th>Secret</th><th>Failures</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($endpoints as $e)
                    <tr>
                        <td style="max-width:280px;word-break:break-all">{{ $e->url }}</td>
                        <td>{{ implode(', ', $e->events ?? []) }}</td>
                        <td><code style="font-size:11px">{{ $e->secret }}</code></td>
                        <td>{{ $e->failure_count }}</td>
                        <td>{!! $e->is_active ? '<span class="badge bg-success">active</span>' : '<span class="badge bg-secondary">disabled</span>' !!}</td>
                        <td>
                            <form method="POST" action="{{ route('settings.webhooks.test', $e->id) }}" class="d-inline">
                                @csrf <button class="btn btn-sm btn-outline-secondary">Send test</button>
                            </form>
                            <form method="POST" action="{{ route('settings.webhooks.destroy', $e->id) }}" class="d-inline"
                                  onsubmit="return confirm('Remove this endpoint?')">
                                @csrf @method('DELETE') <button class="btn btn-sm btn-outline-danger">×</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">No endpoints.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="s-card">
        <div class="s-head"><h5>Add endpoint</h5></div>
        <div class="s-body">
            <form method="POST" action="{{ route('settings.webhooks.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Payload URL</label>
                    <input class="form-control" name="url" required placeholder="https://example.com/hooks/hrm">
                </div>
                <label class="form-label">Events</label>
                <div class="mb-3">
                    <label class="me-3" style="font-size:12px"><input type="checkbox" name="events[]" value="*"> all events (*)</label>
                    @foreach ($eventNames as $n)
                        <label class="me-3" style="font-size:12px"><input type="checkbox" name="events[]" value="{{ $n }}"> {{ $n }}</label>
                    @endforeach
                </div>
                <button class="btn btn-primary">Add</button>
                <div class="text-muted mt-2" style="font-size:12px">
                    Deliveries are signed: <code>X-HRM-Signature: sha256=HMAC(secret, "{timestamp}.{body}")</code>.
                </div>
            </form>
        </div>
    </div>

    <div class="s-card">
        <div class="s-head"><h5>Recent deliveries</h5></div>
        <div class="s-body">
            <table class="tbl">
                <thead><tr><th>When</th><th>Event</th><th>Status</th><th>Attempt</th><th>HTTP</th><th>ms</th></tr></thead>
                <tbody>
                @forelse ($deliveries as $d)
                    <tr>
                        <td>{{ $d->created_at?->format('d M H:i:s') }}</td>
                        <td>{{ $d->event }}</td>
                        <td>{{ $d->status }}</td>
                        <td>{{ $d->attempt }}</td>
                        <td>{{ $d->response_status ?? '—' }}</td>
                        <td>{{ $d->response_ms ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">No deliveries.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div></div>
@endsection
