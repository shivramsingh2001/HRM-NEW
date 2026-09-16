@extends('client.layout.master')

@section('style')
<style>
    /* ==================== ALL-BLUE THEME ==================== */
    .form-section { background: #fff; border: 1px solid #e3edfe; border-radius: 12px; padding: 18px; margin-bottom: 16px; box-shadow: 0 1px 2px rgba(20, 30, 60, .04); }
    .form-section h6 { font-size: 12px; font-weight: 700; color: #1a2236; text-transform: uppercase; letter-spacing: .03em; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
    .form-section h6 .section-icon {
        width: 26px; height: 26px; border-radius: 8px; background: #e3edfe; color: #1e3a8a;
        display: inline-flex; align-items: center; justify-content: center; font-size: 13px;
    }
    .form-section label { font-size: 11.5px; font-weight: 600; margin-bottom: 4px; color: #475569; }
    .form-control:focus { border-color: #1e3a8a; box-shadow: 0 0 0 .18rem rgba(30, 58, 138, .12); }

    .current-rate-pill {
        font-size: 10px; font-weight: 600; color: #1e3a8a; background: #e3edfe;
        padding: 4px 10px; border-radius: 999px; display: inline-block; margin-bottom: 10px;
    }
    .current-rate-pill.not-configured { background: #f4f6fb; color: #6b7385; }

    .btn-primary { background: #1e3a8a; border-color: #1e3a8a; }
    .btn-primary:hover { background: #16295e; border-color: #16295e; }

    .alert-info-blue {
        background: #eef3fd; border: 1px solid #bfd3f7; color: #1e3a8a; border-radius: 10px; font-size: 11.5px; padding: 10px 14px;
    }
    .alert-info-blue a { color: #1e3a8a; font-weight: 700; text-decoration: underline; }

    /* summary stat tiles */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: .75rem; margin-bottom: 1rem; }
    .stats-card {
        background: white; border: 1px solid #eaeef5; border-radius: 10px; padding: 12px 14px;
        display: flex; align-items: center; transition: all 0.2s; box-shadow: 0 1px 2px rgba(20, 30, 60, .04);
    }
    .stats-card:hover { box-shadow: 0 4px 12px -4px rgba(30, 50, 110, .12); border-color: #dfe5f0; transform: translateY(-1px); }
    .stats-icon { width: 34px; height: 34px; background: #e3edfe; border-radius: 9px; display: flex; align-items: center; justify-content: center; margin-right: 10px; flex: none; }
    .stats-icon i { font-size: 15px; color: #1e3a8a; }
    .stats-info h3 { font-size: 17px; font-weight: 700; margin: 0 0 2px 0; color: #1a2236; line-height: 1.2; }
    .stats-info p { font-size: 11px; color: #6b7385; margin: 0; }

    /* PT slabs table */
    #ptSlabsTable { font-size: 11.5px; }
    #ptSlabsTable th { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .02em; color: #6b7385; background: #f7faff; padding: 8px 10px; border-bottom: 1px solid #e3edfe; }
    #ptSlabsTable td { padding: 8px 10px; vertical-align: middle; }
    #ptSlabsTable tr:hover td { background: #fafcff; }
    #ptSlabsTable .state-badge { font-size: 10px; font-weight: 700; color: #1e3a8a; background: #e3edfe; padding: 3px 9px; border-radius: 999px; }

    .btn-icon-delete {
        width: 30px; height: 30px; padding: 0; display: inline-flex; align-items: center; justify-content: center;
        border-radius: 8px; border: 1px solid #bfd3f7; background: #eef3fd; color: #1e3a8a; transition: all .15s;
    }
    .btn-icon-delete:hover { background: #dbe6fb; border-color: #1e3a8a; }
    .btn-icon-delete i { font-size: 13px; }

    .add-slab-row { background: #fbfcfe; border: 1px dashed #bfd3f7; border-radius: 10px; padding: 14px; }
</style>
@endsection

@section('content-area')
    <div class="content-area-header sticky-top">
        <div class="page-header-left d-flex align-items-center gap-2">
            <div class="page-header-title">
                <h5 class="m-b-10">Statutory Compliance Settings</h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.57rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Compliance Settings</li>
            </ul>
        </div>
    </div>

    <div class="content-area-body">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="alert-info-blue mb-3">
            <i class="feather-info me-1"></i> These rates drive any payroll component marked "statutory" in the
            <a href="{{ route('payroll-components.index') }}">Payroll Components</a> catalog. Saving here creates a new
            versioned rate (effective from the date you choose) — past payroll runs always keep using the rate that was
            actually in force at the time.
        </div>

        <div class="stats-grid">
            @foreach (['pf' => ['Provident Fund', 'feather-shield'], 'esi' => ['ESI', 'feather-heart']] as $type => $meta)
                @php $current = $rateConfigs->get($type); @endphp
                <div class="stats-card">
                    <div class="stats-icon"><i class="{{ $meta[1] }}"></i></div>
                    <div class="stats-info">
                        <h3>{{ $current ? $current->config['employee_rate'] . '% / ' . $current->config['employer_rate'] . '%' : 'Not set' }}</h3>
                        <p>{{ $meta[0] }} (Employee / Employer)</p>
                    </div>
                </div>
            @endforeach
            <div class="stats-card">
                <div class="stats-icon"><i class="feather-map-pin"></i></div>
                <div class="stats-info">
                    <h3>{{ $ptSlabs->flatten()->count() }}</h3>
                    <p>PT slabs across {{ $ptSlabs->count() }} state{{ $ptSlabs->count() == 1 ? '' : 's' }}</p>
                </div>
            </div>
        </div>

        <div class="row">
            @foreach (['pf' => 'Provident Fund (PF)', 'esi' => 'ESI'] as $type => $label)
                @php $current = $rateConfigs->get($type); @endphp
                <div class="col-md-6">
                    <div class="form-section">
                        <h6><span class="section-icon"><i class="{{ $type === 'pf' ? 'feather-shield' : 'feather-heart' }}"></i></span>{{ $label }}</h6>
                        @if ($current)
                            <div class="current-rate-pill">
                                Currently: Employee {{ $current->config['employee_rate'] }}% · Employer {{ $current->config['employer_rate'] }}%
                                · Ceiling ₹{{ number_format($current->config['wage_ceiling'] ?? 0, 0) }}
                                · effective {{ \Carbon\Carbon::parse($current->effective_from)->format('d M Y') }}
                            </div>
                        @else
                            <div class="current-rate-pill not-configured">Not configured yet — component's own values will be used.</div>
                        @endif
                        <form action="{{ route('payroll-compliance.update-rate', $type) }}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-6 mb-2">
                                    <label>Employee Rate (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control" name="employee_rate"
                                        value="{{ old('employee_rate', optional($current)->config['employee_rate'] ?? '') }}" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <label>Employer Rate (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control" name="employer_rate"
                                        value="{{ old('employer_rate', optional($current)->config['employer_rate'] ?? '') }}" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <label>Wage Ceiling (₹)</label>
                                    <input type="number" step="0.01" min="0" class="form-control" name="wage_ceiling"
                                        value="{{ old('wage_ceiling', optional($current)->config['wage_ceiling'] ?? '') }}">
                                </div>
                                <div class="col-6 mb-2">
                                    <label>Effective From</label>
                                    <input type="date" class="form-control" name="effective_from" value="{{ old('effective_from', now()->toDateString()) }}" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">Save {{ strtoupper($type) }} Rates</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="form-section">
            <h6><span class="section-icon"><i class="feather-map-pin"></i></span>Professional Tax Slabs</h6>
            <p class="text-muted" style="font-size:11px;">
                PT varies by state and is a fixed monthly amount per gross-salary band (not a percentage). Add a slab per
                state/band; leave "Max" blank for the top open-ended band.
            </p>

            @if ($ptSlabs->isEmpty())
                <div class="alert alert-warning d-flex align-items-start gap-2" style="font-size:12px;" role="alert">
                    <i class="feather-alert-triangle mt-1"></i>
                    <div>
                        <strong>Professional Tax is not auto-calculated.</strong> No PT slabs are configured for any state
                        yet, so the dynamic payroll engine cannot compute PT automatically — it falls back to whatever
                        manual PT amount is entered on the component or employee level (not ₹0 by default). Add real slabs
                        for your state(s) below once you have the official rate table, or continue entering PT manually
                        until then.
                    </div>
                </div>
            @endif

            <div class="table-responsive mb-3">
                <table class="table mb-0" id="ptSlabsTable">
                    <thead>
                        <tr>
                            <th>State</th>
                            <th>Gross Salary Range</th>
                            <th>PT Amount</th>
                            <th>Effective From</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ptSlabs as $state => $slabs)
                            @foreach ($slabs as $slab)
                                <tr>
                                    <td>
                                        @if ($loop->first)
                                            <span class="state-badge">{{ $state }}</span>
                                        @endif
                                    </td>
                                    <td>₹{{ number_format($slab->gross_salary_min, 0) }} – {{ $slab->gross_salary_max ? '₹' . number_format($slab->gross_salary_max, 0) : 'and above' }}</td>
                                    <td>₹{{ number_format($slab->pt_amount, 2) }}</td>
                                    <td>{{ \Carbon\Carbon::parse($slab->effective_from)->format('d M Y') }}</td>
                                    <td class="text-end">
                                        <form action="{{ route('payroll-compliance.pt-slabs.destroy', $slab->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Remove this PT slab?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon-delete"><i class="feather-trash-2"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">No PT slabs configured yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <form action="{{ route('payroll-compliance.pt-slabs.store') }}" method="POST" class="row g-2 align-items-end add-slab-row">
                @csrf
                <div class="col-md-2">
                    <label>State</label>
                    <input type="text" class="form-control" name="state_code" placeholder="e.g. Karnataka" required>
                </div>
                <div class="col-md-2">
                    <label>Min Gross (₹)</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="gross_salary_min" value="0" required>
                </div>
                <div class="col-md-2">
                    <label>Max Gross (₹, blank = open)</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="gross_salary_max">
                </div>
                <div class="col-md-2">
                    <label>PT Amount (₹)</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="pt_amount" required>
                </div>
                <div class="col-md-2">
                    <label>Effective From</label>
                    <input type="date" class="form-control" name="effective_from" value="{{ now()->toDateString() }}" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="feather-plus me-1"></i>Add Slab</button>
                </div>
            </form>
        </div>
    </div>
@endsection
