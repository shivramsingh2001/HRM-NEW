@extends('client.layout.master')

@section('style')
<style>
    /* ==================== ALL-BLUE THEME ==================== */
    /* All filters on one line, no captions — same as the other Reports pages. */
    .filter-row { display: flex; flex-wrap: nowrap; gap: 6px; align-items: center; overflow-x: auto; }
    .form-control-sm-custom { border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 10px; font-size: 11px; height: 32px; background-color: #fff; }
    .form-control-sm-custom:focus { border-color: #0D6EFD; box-shadow: 0 0 0 .15rem rgba(13, 110, 253, .12); outline: none; }
    .btn-sm-custom-outline { background: #f4f6fb; color: #475569; border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 600; text-decoration: none; height: 32px; display: inline-flex; align-items: center; }
    .btn-sm-custom-outline:hover { background: #EFF6FF; color: #0D6EFD; }

    #overtimeTable { font-size: 10.5px; }
    #overtimeTable th { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .02em; color: #6b7385; background: #f7faff; padding: 6px 8px; border-bottom: 1px solid #EFF6FF; }
    #overtimeTable td { padding: 6px 8px; vertical-align: middle; }
    #overtimeTable tr:hover td { background: #fafcff; }
    #overtimeTable .hours-pill { font-size: 9px; font-weight: 700; padding: 2px 8px; border-radius: 999px; }
    #overtimeTable .hours-approved { background: #0D6EFD; color: #fff; }
    #overtimeTable .hours-pending { background: #93c5fd; color: #0D6EFD; }
    #overtimeTable .hours-rejected { background: #e2e8f0; color: #475569; }
    #overtimeTable .cost-value { font-weight: 700; color: #0D6EFD; }
</style>
@endsection

@section('content-area')
    <x-ui.page-header class="content-area-header sticky-top" title="Overtime Report (Monthly)" current="Overtime Monthly" :crumbs="[['label' => 'Reports', 'url' => route('report.attendance.index')]]" />

    <div class="content-area-body" style="padding: 20px !important;">
        @include('client.report.partials.report-subnav', ['group' => 'attendance'])
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <x-ui.filter-card title="Filter Report">
            <form action="{{ route('report.overtime.monthly.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item">
                        <input type="month" name="month" value="{{ $selectedMonth }}" class="form-control-sm-custom auto-submit" title="Month" aria-label="Month">
                    </div>
                    <div class="filter-item">
                        <input type="text" name="search" value="{{ $search }}" class="form-control-sm-custom"
                            placeholder="Search name or employee ID" aria-label="Search employee" style="min-width:200px" autocomplete="off">
                    </div>
                    <div class="filter-item">
                        <select name="status" class="form-control-sm-custom auto-submit" aria-label="Status">
                            <option value="">-- Any Status --</option>
                            <option value="approved" {{ $statusFilter === 'approved' ? 'selected' : '' }}>Has Approved</option>
                            <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Has Pending</option>
                            <option value="rejected" {{ $statusFilter === 'rejected' ? 'selected' : '' }}>Has Rejected</option>
                        </select>
                    </div>
                    @include('client.report.partials.employee-filters', ['selectClass' => 'form-control-sm-custom'])
                    <div class="filter-item"><a href="{{ route('report.overtime.monthly.index') }}" class="btn-sm-custom-outline" title="Reset filters" aria-label="Reset filters"><i class="feather-refresh-cw"></i></a></div>
                </div>
            </form>
        </x-ui.filter-card>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0" id="overtimeTable">
                        <thead>
                            <tr>
                                <th>Sr. No.</th>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Designation</th>
                                @feature('branches')<th>Branch</th>@endfeature
                                <th>Attendance Location</th>
                                <th class="text-center">Requests</th>
                                <th class="text-center">Approved</th>
                                <th class="text-center" title="Hours worked in additional (2nd+) shifts — paid as overtime automatically">Extra Shift</th>
                                <th class="text-center">Pending</th>
                                <th class="text-center">Rejected</th>
                                <th class="text-end">Rate (₹/hr)</th>
                                <th class="text-end">Est. Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reportData as $row)
                                <tr>
                                    <td>{{ $reportData->firstItem() + $loop->index }}</td>
                                    <td>
                                        <div class="employee-info">
                                            <div class="employee-avatar"
                                                style="background:#0D6EFD;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;">
                                                {{ strtoupper(substr($row['name'], 0, 2)) }}</div>
                                            <div class="employee-details">
                                                <div class="employee-name">
                                                    {{ $row['name'] }}
                                                    <small class="text-muted">({{ $row['employee_id'] ?? 'N/A' }})</small>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $row['department'] ?? '—' }}</td>
                                    <td>{{ $row['designation'] ?? '—' }}</td>
                                    @feature('branches')<td>{{ $row['branch'] ?? '—' }}</td>@endfeature
                                    <td>{{ $row['attendance_location'] ?? '—' }}</td>
                                    <td class="text-center">{{ $row['request_count'] }}</td>
                                    <td class="text-center">
                                        <span class="hours-pill hours-approved">{{ number_format($row['approved_hours'], 1) }}h ({{ $row['approved_count'] }})</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="hours-pill hours-approved">{{ number_format($row['extra_shift_hours'] ?? 0, 1) }}h</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="hours-pill hours-pending">{{ number_format($row['pending_hours'], 1) }}h ({{ $row['pending_count'] }})</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="hours-pill hours-rejected">{{ number_format($row['rejected_hours'], 1) }}h ({{ $row['rejected_count'] }})</span>
                                    </td>
                                    <td class="text-end">₹{{ number_format($row['hourly_rate'], 2) }}</td>
                                    <td class="text-end cost-value">₹{{ number_format($row['estimated_cost'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="text-center text-muted py-4">No overtime requests found for this month.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <x-ui.pagination-footer :paginator="$reportData" label="employees" />
        </div>

        <div class="alert-info-blue mt-3" style="background:#eef3fd;border:1px solid #bfd3f7;color:#0D6EFD;border-radius:10px;font-size:10.5px;padding:8px 12px;">
            <i class="feather-info me-1"></i> Estimated cost = approved hours &times; hourly rate &times; the tenant's overtime multiplier ({{ rtrim(rtrim($stats['multiplier'], '0'), '.') }}x). Hourly rate is derived from
            each employee's current payroll assignment — this is an estimate for planning, not a payroll-authoritative figure.
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        $(function() {
            const form = $('#filterForm');
            form.find('.auto-submit').on('change', () => form.submit());

            // search: submit shortly after typing stops, then put the cursor back after the reload
            const key = 'overtimeReportSearchFocus';
            const box = form.find('input[name="search"]');
            let last = box.val(), timer;
            box.on('input', function() {
                clearTimeout(timer);
                timer = setTimeout(function() {
                    if (box.val() === last) return;
                    try { sessionStorage.setItem(key, '1'); } catch (e) {}
                    form.submit();
                }, 600);
            });
            try {
                if (sessionStorage.getItem(key)) {
                    sessionStorage.removeItem(key);
                    const el = box.get(0);
                    if (el) { el.focus(); el.setSelectionRange(el.value.length, el.value.length); }
                }
            } catch (e) {}
        });
    </script>
@endsection
