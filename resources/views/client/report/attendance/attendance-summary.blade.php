@extends('client.layout.master')

@section('title', 'Monthly Attendance Summary')

@section('style')
<style>
    /* ==================== ALL-BLUE THEME (same as the other Reports pages) ==================== */
    /* All filters on one line, no captions. */
    .filter-row { display: flex; flex-wrap: nowrap; gap: 6px; align-items: center; overflow-x: auto; }
    .form-control-sm-custom { border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 10px; font-size: 11px; height: 32px; background-color: #fff; }
    .form-control-sm-custom:focus { border-color: #0D6EFD; box-shadow: 0 0 0 .15rem rgba(13, 110, 253, .12); outline: none; }
    .btn-sm-custom-outline { background: #f4f6fb; color: #475569; border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 600; text-decoration: none; height: 32px; display: inline-flex; align-items: center; }
    .btn-sm-custom-outline:hover { background: #EFF6FF; color: #0D6EFD; }

    #summaryTable { font-size: 11px; }
    #summaryTable th { font-size: 9.5px; font-weight: 700; text-transform: uppercase; color: #6b7385; padding: 7px 10px; white-space: nowrap; background: #f7faff; border-bottom: 1px solid #EFF6FF; }
    #summaryTable td { padding: 7px 10px; vertical-align: middle; }
    #summaryTable tbody tr:hover td { background: #fafcff; }
    #summaryTable a.emp-link { text-decoration: none; color: inherit; }
    #summaryTable .employee-avatar { flex: none; }

    #summaryTable .cnt { display: inline-block; min-width: 28px; padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 700; }
    #summaryTable .cnt-present { background: #dcfce7; color: #15803d; }
    #summaryTable .cnt-absent { background: #fee2e2; color: #b91c1c; }
    #summaryTable .cnt-leave { background: #fef3c7; color: #b45309; }
    #summaryTable .cnt-holiday { background: #ede9fe; color: #5b21b6; }
    #summaryTable .cnt-weekoff { background: #f1f5f9; color: #475569; }
</style>
@endsection

@section('content-area')
    <x-ui.page-header class="content-area-header sticky-top" title="Monthly Attendance Summary" current="Monthly Summary" :crumbs="[['label' => 'Reports', 'url' => route('report.attendance.index')]]">
        <x-slot:actions>
            <a href="{{ route('report.attendance.summary.export', array_merge(request()->except('page'), ['month' => $selectedMonth])) }}" class="btn btn-sm btn-primary">
                <i class="feather-download me-1"></i> Export CSV
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="content-area-body" style="padding: 20px !important;">
        @include('client.report.partials.report-subnav', ['group' => 'attendance'])
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <x-ui.filter-card title="Filter Report">
            <form action="{{ route('report.attendance.summary.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item">
                        <input type="month" name="month" value="{{ $selectedMonth }}" class="form-control-sm-custom" onchange="this.form.submit()" title="Month" aria-label="Month">
                    </div>
                    <div class="filter-item">
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control-sm-custom"
                            placeholder="Search name / ID / email" aria-label="Search employee" style="min-width:200px" autocomplete="off">
                    </div>
                    @include('client.report.partials.employee-filters', ['selectClass' => 'form-control-sm-custom'])
                    <div class="filter-item"><a href="{{ route('report.attendance.summary.index') }}" class="btn-sm-custom-outline" title="Reset filters" aria-label="Reset filters"><i class="feather-refresh-cw"></i></a></div>
                </div>
            </form>
        </x-ui.filter-card>

        <div class="card stretch stretch-full">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0" id="summaryTable">
                        <thead>
                            <tr>
                                <th>Sr. No.</th>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Designation</th>
                                @if ($branchesEnabled ?? false)<th>Branch</th>@endif
                                <th>Attendance Location</th>
                                <th class="text-center">Present</th>
                                <th class="text-center">Absent</th>
                                <th class="text-center">Leaves</th>
                                <th class="text-center">Holidays</th>
                                <th class="text-center">Week Offs</th>
                                <th class="text-center">Working Days</th>
                                <th class="text-center">Month Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($summaryData as $index => $row)
                                <tr>
                                    <td>{{ $summaryPage->firstItem() + $index }}</td>
                                    <td>
                                        {{-- Same employee cell as the Overall Attendance report --}}
                                        <a class="emp-link" href="{{ route('team.member-detail', ['id' => encrypt($row['user_id'])]) }}">
                                            <div class="employee-info">
                                                <div class="employee-avatar">{{ strtoupper(substr($row['name'] ?? 'N/A', 0, 2)) }}</div>
                                                <div class="employee-details">
                                                    <div class="employee-name-text">{{ $row['name'] ?? 'N/A' }}
                                                        <small class="employee-id-text">( {{ $row['employee_id'] ?? 'N/A' }} )</small>
                                                    </div>
                                                    <div class="employee-email-text">{{ $row['email'] ?? 'N/A' }}</div>
                                                </div>
                                            </div>
                                        </a>
                                    </td>
                                    <td>{{ $row['department'] ?: '—' }}</td>
                                    <td>{{ $row['designation'] ?: '—' }}</td>
                                    @if ($branchesEnabled ?? false)<td>{{ $row['branch'] ?? '—' }}</td>@endif
                                    <td>{{ $row['attendance_location'] ?? '—' }}</td>
                                    <td class="text-center"><span class="cnt cnt-present">{{ $row['present'] }}</span></td>
                                    <td class="text-center"><span class="cnt cnt-absent">{{ $row['absent'] }}</span></td>
                                    <td class="text-center"><span class="cnt cnt-leave">{{ $row['leaves'] }}</span></td>
                                    <td class="text-center"><span class="cnt cnt-holiday">{{ $row['holidays'] }}</span></td>
                                    <td class="text-center"><span class="cnt cnt-weekoff">{{ $row['weekoffs'] }}</span></td>
                                    <td class="text-center">{{ $row['working_days'] }}</td>
                                    <td class="text-center">{{ $row['total_month_days'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="text-center py-5 text-muted" style="font-size:11px;">
                                        No attendance records available for the selected month.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <x-ui.pagination-footer :paginator="$summaryPage" label="employees" />
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        $(function() {
            const form = $('#filterForm');

            // search: submit shortly after typing stops, then put the cursor back after the reload
            const key = 'attendanceSummarySearchFocus';
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
