@extends('client.layout.master')

@section('style')
<style>
    .s-card { background:#fff; border:1px solid #edf2f7; border-radius:14px; }
    .s-head { padding:16px 20px; border-bottom:1px solid #edf2f7; background:#fafbfc; }
    .s-head h5 { margin:0; font-size:15px; font-weight:600; }
    .s-body { padding:20px; }
    table.tbl { width:100%; font-size:12px; }
    table.tbl th, table.tbl td { padding:6px 8px; border-bottom:1px solid #eef2f7; text-align:left; }
    .st-processed { color:#15803d; } .st-error { color:#b91c1c; } .st-skipped { color:#b45309; } .st-pending { color:#64748b; }
</style>
@endsection

@section('content-area')
<div class="page-content"><div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Biometric punches</h4>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('settings.biometric.index') }}">← Devices</a>
    </div>
    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

    <div class="s-card"><div class="s-body">
        <form method="GET" class="mb-3" style="font-size:12px">
            Status:
            <select name="status" class="form-control form-control-sm d-inline-block" style="width:150px" onchange="this.form.submit()">
                <option value="">all</option>
                @foreach (['pending','processed','skipped','error'] as $s)
                    <option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>
                @endforeach
            </select>
        </form>
        <table class="tbl">
            <thead><tr>
                <th>When</th><th>Device</th><th>Enroll</th><th>Employee</th><th>Dir</th>
                <th>Method</th><th>Status</th><th>Error</th><th></th>
            </tr></thead>
            <tbody>
            @forelse ($punches as $p)
                <tr>
                    <td>{{ $p->punched_at?->format('d M H:i:s') }}</td>
                    <td>{{ $p->device?->name ?? $p->serial_number }}</td>
                    <td><code>{{ $p->enroll_no }}</code></td>
                    <td>{{ $p->user?->name ?? '—' }}</td>
                    <td>{{ $p->direction }}</td>
                    <td>{{ $p->method ?? '—' }}</td>
                    <td class="st-{{ $p->status }}">{{ $p->status }}</td>
                    <td style="font-size:11px">{{ $p->error }}</td>
                    <td>
                        @if (in_array($p->status, ['pending','error','skipped']))
                            <form method="POST" action="{{ route('settings.biometric.punches.reprocess', $p) }}">
                                @csrf <button class="btn btn-sm btn-outline-secondary">Reprocess</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-muted">No punches.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-2">{{ $punches->links() }}</div>
    </div></div>
</div></div>
@endsection
