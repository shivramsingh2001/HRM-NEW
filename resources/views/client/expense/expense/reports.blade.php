@extends('client.layout.master')

@section('style')
    <style>
        /* Same look as the other Reports pages (project / task / asset): KPI tiles, one-row filter bar, compact table. */
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
        .filter-note { font-size: 10.5px; color: #6b7385; }

        #reportTable { font-size: 11px; }
        #reportTable th { font-size: 9.5px; font-weight: 700; text-transform: uppercase; color: #6b7385; padding: 6px 10px; white-space: nowrap; background: #f7faff; }
        #reportTable td { padding: 6px 10px; vertical-align: middle; }
        #reportTable tr.totals-row td { font-weight: 700; background: #f7faff; color: #1a2236; }

        .report-note { background: #eff6ff; border: 1px solid #dbeafe; border-radius: 10px; padding: 8px 12px; font-size: 10.5px; color: #1e40af; margin-bottom: 12px; }
    </style>
@endsection

@section('content-area')
    @php
        $csvUrl = route('expense.reports.show', array_merge(['report' => $report], request()->query(), ['export' => 'csv']));
        $isAgeing = $report === 'ageing';
        $tileIcons = ['feather-list', 'feather-credit-card', 'feather-check-circle', 'feather-clock'];
        $modes = ['cash', 'bank_transfer', 'cheque', 'upi', 'payroll'];
    @endphp

    <div class="content-area-header sticky-top">
        <div class="page-header-left d-flex align-items-center gap-2">
            <div class="page-header-title">
                <h5 class="m-b-10">{{ $title }}</h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.5rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('report.attendance.index') }}#expenseReportTab">Reports</a></li>
                <li class="breadcrumb-item">{{ $reports[$report] ?? 'Expense' }}</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <a href="{{ $csvUrl }}" class="btn btn-sm btn-primary">
                <i class="feather-download me-1"></i> Export CSV
            </a>
        </div>
    </div>

    <div class="content-area-body" style="padding: 20px !important;">
        {{-- Summary tiles --}}
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

        {{-- Filters (one row, no captions — same as the other reports) --}}
        <div class="filter-section">
            <form action="{{ route('expense.reports.show', $report) }}" method="GET" id="filterForm">
                <div class="filter-row">
                    @unless ($isAgeing)
                        <div class="filter-item">
                            <input type="date" name="from_date" value="{{ $from }}" class="form-control-sm-custom"
                                title="From date" aria-label="From date">
                        </div>
                        <div class="filter-item">
                            <input type="date" name="to_date" value="{{ $to }}" class="form-control-sm-custom"
                                title="To date" aria-label="To date">
                        </div>
                    @endunless

                    @if ($report === 'register')
                        <div class="filter-item">
                            <select name="user_id" class="form-control-sm-custom auto-submit">
                                <option value="">-- All Employees --</option>
                                @foreach ($employees as $e)
                                    <option value="{{ $e->id }}" @selected(($filters['user_id'] ?? '') == $e->id)>{{ $e->name }}
                                        ({{ $e->employee_id }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item">
                            <select name="payment_mode" class="form-control-sm-custom auto-submit">
                                <option value="">-- All Modes --</option>
                                @foreach ($modes as $m)
                                    <option value="{{ $m }}" @selected(($filters['payment_mode'] ?? '') === $m)>
                                        {{ ucwords(str_replace('_', ' ', $m)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item">
                            <select name="status" class="form-control-sm-custom auto-submit">
                                <option value="">-- All Status --</option>
                                <option value="posted" @selected(($filters['status'] ?? '') === 'posted')>Posted</option>
                                <option value="voided" @selected(($filters['status'] ?? '') === 'voided')>Voided</option>
                            </select>
                        </div>
                    @endif

                    @if ($report === 'summary')
                        <div class="filter-item">
                            <select name="group" class="form-control-sm-custom auto-submit" title="Group by">
                                @foreach (['employee' => 'Group by Employee', 'category' => 'Group by Category', 'project' => 'Group by Project'] as $k => $l)
                                    <option value="{{ $k }}" @selected($group === $k)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item">
                            <select name="requirement_type" class="form-control-sm-custom auto-submit" title="Claim type">
                                @foreach (['spend' => 'Spend (settlements + reimbursements)', 'advance' => 'Advances', 'settlement' => 'Settlements', 'reimbursement' => 'Reimbursements', 'all' => 'Everything'] as $k => $l)
                                    <option value="{{ $k }}" @selected($requirement_type === $k)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @isset($branches)
                        <div class="filter-item">
                            <select name="branch_id" class="form-control-sm-custom auto-submit">
                                <option value="">-- All Branches --</option>
                                @foreach ($branches as $b)
                                    <option value="{{ $b->id }}" @selected(($filters['branch_id'] ?? '') == $b->id)>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endisset

                    <div class="filter-item">
                        <input type="text" name="search" onchange="this.form.submit()" onkeydown="if (event.key === 'Enter') { event.preventDefault(); this.form.submit(); }" class="form-control-sm-custom" placeholder="Search employee, ID or reference…"
                            value="{{ $filters['search'] ?? '' }}">
                    </div>

                    @if ($isAgeing)
                        <span class="filter-note"><i class="feather-calendar me-1"></i>As of {{ $as_of }} — a live snapshot; branch and search still narrow it.</span>
                    @endif
                    <div class="filter-item"><a href="{{ route('expense.reports.show', $report) }}" class="btn-sm-custom-outline" title="Reset filters" aria-label="Reset filters"><i class="feather-refresh-cw"></i></a></div>
                </div>
            </form>
        </div>

        @isset($note)
            <div class="report-note"><i class="feather-info me-1"></i>{{ $note }}</div>
        @endisset

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
