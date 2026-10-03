@extends('client.layout.master')

@section('style')
<style>
    .a-card { background:#fff; border:1px solid #edf2f7; border-radius:14px; margin-bottom:20px; }
    .a-head { padding:14px 18px; border-bottom:1px solid #edf2f7; background:#fafbfc; font-weight:600; font-size:14px; }
    .a-body { padding:18px; }
    .kpi { display:flex; gap:16px; flex-wrap:wrap; }
    .kpi .box { flex:1; min-width:150px; background:#f8fafc; border-radius:10px; padding:14px; }
    .kpi .box .n { font-size:22px; font-weight:700; }
    .kpi .box .l { font-size:12px; color:#64748b; }
    table.tbl { width:100%; font-size:12px; }
    table.tbl th, table.tbl td { padding:6px 8px; border-bottom:1px solid #eef2f7; text-align:left; }
    .sev-high { color:#b91c1c; font-weight:600; }
    .sev-medium { color:#b45309; }
    .bar { height:8px; background:#e2e8f0; border-radius:4px; overflow:hidden; }
    .bar > span { display:block; height:100%; background:#6366f1; }
</style>
@endsection

@section('content-area')
<x-ui.page-header title="Attendance Analytics" />
<div class="page-content"><div class="container-fluid">
    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

    <div class="a-card">
        <div class="a-head">Right now</div>
        <div class="a-body">
            <div class="kpi">
                <div class="box"><div class="n">{{ $presentNow['present_now'] }}</div><div class="l">clocked in now</div></div>
                <div class="box"><div class="n">{{ $presentNow['active_headcount'] }}</div><div class="l">active headcount</div></div>
                <div class="box"><div class="n">{{ $otCost['overtime_hours'] }}</div><div class="l">OT hours ({{ $otCost['year_month'] }})</div></div>
                <div class="box"><div class="n">{{ number_format($otCost['estimated_cost']) }}</div><div class="l">est. OT cost (×{{ $otCost['multiplier'] }})</div></div>
            </div>
        </div>
    </div>

    <div class="a-card">
        <div class="a-head">Daily trend
            <form method="GET" class="d-inline float-end" style="font-size:12px">
                <input type="date" name="from" value="{{ $from }}"> –
                <input type="date" name="to" value="{{ $to }}">
                <button class="btn btn-sm btn-outline-secondary">Apply</button>
            </form>
        </div>
        <div class="a-body">
            <table class="tbl">
                <thead><tr><th>Date</th><th>Present</th><th>Half</th><th>Leave</th><th>Absent (est)</th><th>Late min</th><th>Worked h</th><th style="width:120px">Absence rate</th></tr></thead>
                <tbody>
                @forelse ($trends as $t)
                    <tr>
                        <td>{{ $t['date'] }}</td>
                        <td>{{ $t['present'] }}</td>
                        <td>{{ $t['half_day'] }}</td>
                        <td>{{ $t['on_leave'] }}</td>
                        <td>{{ $t['absent_est'] }}</td>
                        <td>{{ $t['late_minutes'] }}</td>
                        <td>{{ $t['worked_hours'] }}</td>
                        <td>
                            <div class="bar"><span style="width:{{ min(100, $t['absence_rate']*100) }}%"></span></div>
                            <span style="font-size:11px">{{ round($t['absence_rate']*100, 1) }}%</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">No data in range.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="a-card">
        <div class="a-head">Open anomalies ({{ $anomalies->count() }})</div>
        <div class="a-body">
            <table class="tbl">
                <thead><tr><th>Date</th><th>Type</th><th>Severity</th><th>User</th><th>Detail</th><th style="width:220px">Action</th></tr></thead>
                <tbody>
                @forelse ($anomalies as $a)
                    <tr>
                        <td>{{ optional($a->date)->format('d M Y') }}</td>
                        <td>{{ str_replace('_', ' ', $a->type) }}</td>
                        <td class="sev-{{ $a->severity }}">{{ $a->severity }}</td>
                        <td>{{ $a->user_id }}</td>
                        <td style="font-size:11px">{{ \Illuminate\Support\Str::limit(json_encode($a->detail), 90) }}</td>
                        <td>
                            <form method="POST" action="{{ route('analytics.attendance.anomaly.review', $a->id) }}" class="d-flex gap-1">
                                @csrf
                                <input class="form-control form-control-sm" name="note" placeholder="note" style="width:90px">
                                <button class="btn btn-sm btn-outline-secondary" name="status" value="ack">Ack</button>
                                <button class="btn btn-sm btn-outline-danger" name="status" value="dismissed">Dismiss</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">Nothing flagged.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div></div>
@endsection
