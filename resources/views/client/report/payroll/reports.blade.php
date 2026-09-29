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
        .btn-sm-custom { background: #1e3a8a; color: #fff; border: none; border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 600; }
        .btn-sm-custom:hover { background: #2563eb; color: #fff; }
        .btn-sm-custom-outline { background: #f4f6fb; color: #475569; border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 600; text-decoration: none; }
        .btn-sm-custom-outline:hover { background: #e3edfe; color: #1e3a8a; }

        #reportTable { font-size: 11px; }
        #reportTable th { font-size: 9.5px; font-weight: 700; text-transform: uppercase; color: #6b7385; padding: 6px 10px; white-space: nowrap; background: #f7faff; }
        #reportTable td { padding: 6px 10px; vertical-align: middle; }
        #reportTable tr.totals-row td { font-weight: 700; background: #f7faff; color: #1a2236; }

        /* sub-tabs to switch between the 4 payroll reports without leaving the page */
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
        $csvUrl = route('report.payroll.show', array_merge(['report' => $report], request()->query(), ['export' => 'csv']));
        $needsMonth = in_array($report, ['monthly', 'summary']);
        $tileIcons = ['feather-users', 'feather-trending-up', 'feather-trending-down', 'feather-check-circle'];
    @endphp

    <div class="content-area-header sticky-top">
        <div class="page-header-left d-flex align-items-center gap-2">
            <div class="page-header-title">
                <h5 class="m-b-10">{{ $title }}</h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.5rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('report.attendance.index') }}#payrollReportTab">Reports</a></li>
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
                <a href="{{ route('report.payroll.show', $key) }}" class="{{ $key === $report ? 'active' : '' }}">{{ $label }}</a>
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
            <form action="{{ route('report.payroll.show', $report) }}" method="GET" id="filterForm">
                <div class="filter-row">
                    @if ($needsMonth)
                        <div class="filter-item">
                            <input type="month" name="month" value="{{ $month }}" class="form-control-sm-custom auto-submit" title="Month" aria-label="Month">
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

                    @if ($report === 'monthly')
                        <div class="filter-item">
                            <select name="payment_status" class="form-control-sm-custom auto-submit">
                                <option value="">-- All Status --</option>
                                @foreach (['pending' => 'Pending', 'processing' => 'Processing', 'paid' => 'Paid'] as $k => $l)
                                    <option value="{{ $k }}" @selected(($filters['payment_status'] ?? '') === $k)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($report === 'structure')
                        <div class="filter-item">
                            <select name="payroll_structure_id" class="form-control-sm-custom auto-submit">
                                <option value="">-- All Structures --</option>
                                @foreach ($structures as $s)
                                    <option value="{{ $s->id }}" @selected(($filters['payroll_structure_id'] ?? '') == $s->id)>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($report === 'loan')
                        <div class="filter-item">
                            <select name="status" class="form-control-sm-custom auto-submit">
                                <option value="">-- All Status --</option>
                                @foreach ($loanStatuses as $k => $l)
                                    <option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="filter-item"><a href="{{ route('report.payroll.show', $report) }}" class="btn-sm-custom-outline" title="Reset filters" aria-label="Reset filters"><i class="feather-refresh-cw"></i></a></div>
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
                            @isset($totals_row)
                                @if (count($rows))
                                    <tr class="totals-row">
                                        @foreach ($totals_row as $cell)
                                            <td>{{ $cell }}</td>
                                        @endforeach
                                    </tr>
                                @endif
                            @endisset
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
            $('#filterForm select.auto-submit, #filterForm input.auto-submit').on('change', function() {
                $('#filterForm').submit();
            });
        });
    </script>
@endsection
