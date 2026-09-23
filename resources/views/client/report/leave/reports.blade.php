@extends('client.layout.master')

@section('style')
    <style>
        /* Same look as the other Reports pages: KPI tiles, one-row filter bar, compact table. */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: .6rem; margin-bottom: .8rem; }
        .stats-card {
            background: white; border: 1px solid #eaeef5; border-radius: 10px; padding: 10px 12px;
            display: flex; align-items: center; box-shadow: 0 1px 2px rgba(20, 30, 60, .04);
        }
        .stats-icon { width: 30px; height: 30px; background: #e3edfe; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 8px; flex: none; }
        .stats-icon i { font-size: 13px; color: #1e3a8a; }
        .stats-info h3 { font-size: 15px; font-weight: 700; margin: 0 0 1px 0; color: #1a2236; line-height: 1.2; }
        .stats-info p { font-size: 9.5px; color: #6b7385; margin: 0; }

        .filter-section { background: #fff; border: 1px solid #eaeef5; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; }
        .filter-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .form-control-sm-custom { border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 10px; font-size: 11px; height: 32px; background-color: #fff; }
        .form-control-sm-custom:focus { border-color: #1e3a8a; box-shadow: 0 0 0 .15rem rgba(30, 58, 138, .12); outline: none; }
        .btn-sm-custom-outline { background: #f4f6fb; color: #475569; border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 600; text-decoration: none; }
        .btn-sm-custom-outline:hover { background: #e3edfe; color: #1e3a8a; }

        #reportTable { font-size: 11px; }
        #reportTable th { font-size: 9.5px; font-weight: 700; text-transform: uppercase; color: #6b7385; padding: 6px 10px; white-space: nowrap; background: #f7faff; }
        #reportTable td { padding: 6px 10px; vertical-align: middle; }

        /* sub-tabs to switch between the 5 leave reports without leaving the page */
        .rpt-subnav { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
        .rpt-subnav a {
            font-size: 11px; font-weight: 600; padding: 6px 14px; border-radius: 20px; text-decoration: none;
            color: #475569; background: #f4f6fb; border: 1px solid #dfe5f0;
        }
        .rpt-subnav a.active { background: #1e3a8a; color: #fff; border-color: #1e3a8a; }
        .rpt-subnav a:hover:not(.active) { background: #e3edfe; color: #1e3a8a; }
    </style>
@endsection

@section('content-area')
    @php
        $csvUrl = route('report.leave.show', array_merge(['report' => $report], request()->query(), ['export' => 'csv']));
        $needsPeriod = in_array($report, ['register', 'summary', 'regularization', 'wfh-travel']);
        $tileIcons = ['feather-users', 'feather-check-circle', 'feather-clock', 'feather-x-circle'];
    @endphp

    <div class="content-area-header sticky-top">
        <div class="page-header-left d-flex align-items-center gap-2">
            <div class="page-header-title">
                <h5 class="m-b-10">{{ $title }}</h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.5rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('report.attendance.index') }}#leaveReportTab">Reports</a></li>
                <li class="breadcrumb-item">{{ $reports[$report] }}</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <a href="{{ $csvUrl }}" class="btn btn-sm btn-primary">
                <i class="feather-download me-1"></i> Export CSV
            </a>
        </div>
    </div>

    <div class="content-area-body" style="padding: 20px !important;">
        <div class="rpt-subnav">
            @foreach ($reports as $key => $label)
                <a href="{{ route('report.leave.show', $key) }}" class="{{ $key === $report ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>

        <div class="stats-grid">
            @foreach ($summary as $label => $value)
                <div class="stats-card">
                    <div class="stats-icon"><i class="{{ $tileIcons[$loop->index % count($tileIcons)] }}"></i></div>
                    <div class="stats-info">
                        <h3>{{ $value }}</h3>
                        <p>{{ $label }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Filters (one row, no captions) --}}
        <div class="filter-section">
            <form action="{{ route('report.leave.show', $report) }}" method="GET" id="filterForm">
                <div class="filter-row">
                    @if ($needsPeriod)
                        <div class="filter-item">
                            <input type="date" name="from_date" value="{{ $from }}" class="form-control-sm-custom" title="From date" aria-label="From date">
                        </div>
                        <div class="filter-item">
                            <input type="date" name="to_date" value="{{ $to }}" class="form-control-sm-custom" title="To date" aria-label="To date">
                        </div>
                    @endif

                    <div class="filter-item">
                        <select name="department_id" class="form-control-sm-custom auto-submit">
                            <option value="">-- All Departments --</option>
                            @foreach ($departments as $d)
                                <option value="{{ $d->id }}" @selected(($filters['department_id'] ?? '') == $d->id)>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if (in_array($report, ['balance', 'register']))
                        <div class="filter-item">
                            <select name="leave_type_id" class="form-control-sm-custom auto-submit">
                                <option value="">-- All Leave Types --</option>
                                @foreach ($leaveTypes as $t)
                                    <option value="{{ $t->id }}" @selected(($filters['leave_type_id'] ?? '') == $t->id)>{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if (in_array($report, ['register', 'regularization']))
                        <div class="filter-item">
                            <select name="status" class="form-control-sm-custom auto-submit">
                                <option value="">-- All Status --</option>
                                @if ($report === 'register')
                                    @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'cancelled' => 'Cancelled'] as $k => $l)
                                        <option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $l }}</option>
                                    @endforeach
                                @else
                                    @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $l)
                                        <option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $l }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                    @endif

                    @if ($report === 'wfh-travel')
                        <div class="filter-item">
                            <select name="request_type_id" class="form-control-sm-custom auto-submit">
                                <option value="">-- All Types --</option>
                                @foreach ($requestTypes as $t)
                                    <option value="{{ $t->id }}" @selected(($filters['request_type_id'] ?? '') == $t->id)>{{ $t->type_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item">
                            <select name="status" class="form-control-sm-custom auto-submit">
                                <option value="">-- All Status --</option>
                                @foreach (['PENDING' => 'Pending', 'APPROVED' => 'Approved', 'REJECTED' => 'Rejected', 'CANCELLED' => 'Cancelled'] as $k => $l)
                                    <option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($needsPeriod)
                        <div class="filter-item"><button type="submit" class="btn-sm-custom-outline"><i class="feather-eye"></i> View</button></div>
                    @endif
                    <div class="filter-item"><a href="{{ route('report.leave.show', $report) }}" class="btn-sm-custom-outline"><i class="feather-refresh-cw"></i> Reset</a></div>
                </div>
            </form>
        </div>

        <div class="card stretch stretch-full">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="reportTable">
                        <thead>
                            <tr>
                                @foreach ($headers as $h)
                                    <th>{{ $h }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    @foreach ($row as $cell)
                                        <td>{{ $cell }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($headers) }}" class="text-center py-4 text-muted">Nothing to show for
                                        this selection.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        $(function() {
            const form = $('#filterForm');
            form.find('select.auto-submit').on('change', () => form.submit());
            form.find('input[type="date"]').on('change', function() {
                const from = form.find('input[name="from_date"]').val();
                const to = form.find('input[name="to_date"]').val();
                if (from && to && from > to) {
                    if (window.toastr) toastr.error('From date cannot be greater than To date');
                    $(this).val('');
                    return;
                }
                form.submit();
            });
        });
    </script>
@endsection
