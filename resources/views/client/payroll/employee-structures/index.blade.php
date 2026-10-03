@extends('client.layout.master')

@section('style')
<style>
    /* ==================== ALL-BLUE THEME ==================== */
    #assignmentsTable .badge { font-size: 10px; padding: 3px 9px; font-weight: 700; }
    #assignmentsTable .badge.bg-success { background-color: #0D6EFD !important; }
    #assignmentsTable .badge.bg-warning { background-color: #93c5fd !important; color: #0D6EFD !important; }

    /* search bar */
    .structure-search { position: relative; max-width: 360px; }
    .structure-search input {
        padding-left: 34px !important; border-radius: 999px; border: 1px solid #dfe5f0; font-size: 12px;
        background: #fff; transition: all .15s;
    }
    .structure-search input:focus {
        border-color: #0D6EFD; box-shadow: 0 0 0 .18rem rgba(13, 110, 253, .12); outline: none;
    }
    .structure-search i {
        position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #93a1b8; font-size: 13px;
    }
    .structure-search .btn-search {
        border-radius: 999px; background: #0D6EFD; border-color: #0D6EFD; color: #fff; font-size: 11.5px; padding: 6px 16px;
    }
    .structure-search .btn-search:hover { background: #0B5ED7; border-color: #0B5ED7; }

    /* action buttons */
    .btn-icon-view, .btn-icon-revise {
        height: 30px; padding: 0 10px; font-size: 11px; font-weight: 600;
        display: inline-flex; align-items: center; justify-content: center; gap: 5px;
        border-radius: 8px; border: 1px solid; transition: all .15s;
    }
    .btn-icon-view { border-color: #bfd3f7; background: #eef3fd; color: var(--icon-color, #0D6EFD); }
    .btn-icon-view:hover { background: #dbe6fb; border-color: #0D6EFD; }
    .btn-icon-revise { border-color: #0D6EFD; background: #0D6EFD; color: #fff; margin-left: 6px; }
    .btn-icon-revise:hover { background: #0B5ED7; border-color: #0B5ED7; color: #fff; }
    .btn-icon-view i, .btn-icon-revise i { font-size: 12px; }

    /* ==================== VIEW DRAWER (read-only breakdown) ==================== */
    .view-drawer { width: 480px; max-width: 92vw; }
    .view-drawer .offcanvas-header { padding: 8px 14px; border-bottom: 1px solid #eaeef5; }
    .view-drawer .offcanvas-header h5 { font-size: 13px; font-weight: 700; margin: 0; color: #1a2236; }
    .view-drawer .offcanvas-body { padding: 10px 14px; }
    .view-drawer .breakdown-card { background: #fbfcfe; border: 1px solid #eaeef5; border-radius: 8px; padding: 10px; margin-bottom: 8px; }
    .view-drawer .breakdown-card h6 { font-size: 10px; font-weight: 700; color: #1a2236; text-transform: uppercase; letter-spacing: .03em; margin-bottom: 6px; }
    .view-drawer .breakdown-row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #f4f6fb; font-size: 11.5px; }
    .view-drawer .breakdown-row:last-child { border-bottom: none; }
    .view-drawer .breakdown-row .label { color: #1a2236; font-weight: 500; }
    .view-drawer .breakdown-row .value { font-weight: 700; color: #0D6EFD; }

    /* ==================== ASSIGN/REVISE DRAWER ==================== */
    .structure-drawer { width: 720px; max-width: 94vw; }
    .structure-drawer .offcanvas-header { padding: 8px 14px; border-bottom: 1px solid #eaeef5; }
    .structure-drawer .offcanvas-header h5 { font-size: 13px; font-weight: 700; margin: 0; color: #1a2236; }
    .structure-drawer .offcanvas-body { padding: 10px 14px; }
    .structure-drawer .form-section { background: #fbfcfe; border: 1px solid #eaeef5; border-radius: 8px; padding: 10px; margin-bottom: 8px; }
    .structure-drawer .form-section h6 { font-size: 10px; font-weight: 700; color: #1a2236; text-transform: uppercase; letter-spacing: .03em; margin-bottom: 6px; }
    .structure-drawer label { font-size: 10.5px; font-weight: 600; margin-bottom: 2px; }
    .structure-drawer .form-control { font-size: 10.5px; padding: 4px 8px; height: auto; }
    .structure-drawer .row > [class*="col-"] { margin-bottom: 4px !important; }
    .structure-drawer .component-row { display: flex; align-items: center; gap: 8px; padding: 5px 0; border-bottom: 1px solid #f4f6fb; }
    .structure-drawer .component-row:last-child { border-bottom: none; }
    .structure-drawer .component-row .comp-name { flex: 1; font-size: 11.5px; font-weight: 600; }
    .structure-drawer .component-row .method-field select { font-size: 10px; padding: 3px 6px; height: auto; width: 105px; }
    .structure-drawer .component-row .value-field input { font-size: 10.5px; padding: 3px 8px; height: auto; width: 115px; }
    .structure-drawer .component-row-locked { opacity: .45; }
    .structure-drawer .component-row-locked .comp-name::after { content: ' (not in this structure)'; font-weight: 400; font-size: 9.5px; color: #9aa1b1; }
    .structure-drawer .base-components-row {
        display: flex; flex-wrap: wrap; align-items: center; gap: 6px 10px;
        padding: 4px 0 8px 26px; margin-top: -4px; border-bottom: 1px solid #f4f6fb;
    }
    .structure-drawer .base-components-label { font-size: 9px; font-weight: 600; color: var(--gray-500); text-transform: uppercase; }
    .structure-drawer .base-comp-check {
        display: inline-flex; align-items: center; gap: 3px; font-size: 10px; font-weight: 400;
        background: #eef2ff; border: 1px solid #dbe2fb; border-radius: 999px; padding: 2px 8px; cursor: pointer;
    }
    .structure-drawer .base-comp-check input { margin: 0; }
    #componentsTotalNotice { color: var(--gray-600); }
    #componentsTotalNotice.components-over-budget { color: var(--danger); }
    .structure-drawer .btn { padding: 4px 12px; font-size: 11px; border-radius: 7px; }
    .structure-drawer .btn-primary { background: #0D6EFD; border-color: #0D6EFD; }
    .structure-drawer .btn-primary:hover { background: #0B5ED7; border-color: #0B5ED7; }
    .structure-drawer .btn-modal-cancel { background: #f4f6fb; border-color: #dfe5f0; color: #475569; }
    .structure-drawer .btn-modal-cancel:hover { background: #EFF6FF; border-color: #0D6EFD; color: #0D6EFD; }
</style>
@endsection

@php
    $user = Auth::user();
    $role = $user->role;
@endphp

@section('content-area')
    <x-ui.page-header class="content-area-header sticky-top" title="Employee Payroll Structures (Dynamic)" current="Employee Payroll Structures">
        <x-slot:actions>
            <div class="hstack gap-2">
                @if (in_array($role, ['admin', 'hr']))
                    <a href="#" class="btn btn-primary btn-sm" id="assignStructureBtn">
                        <i class="feather-plus me-1"></i>Assign Structure
                    </a>
                @endif
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="content-area-body">
        <x-ui.filter-card title="Search Employees">
<form method="GET">
            <div class="structure-search d-inline-flex align-items-center gap-2">
                <div class="position-relative flex-grow-1">
                    <i class="feather-search"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search by name or employee ID..." value="{{ request('search') }}">
                </div>
                <button type="submit" class="btn btn-search">Search</button>
            </div>
        </form>
</x-ui.filter-card>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0" id="assignmentsTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>CTC</th>
                                <th>Effective From</th>
                                <th>Revision Type</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($employees as $employee)
                                @php $structure = $currentStructures->get($employee->id); @endphp
                                <tr>
                                    <td>
                                        <div class="employee-info">
                                            <div class="employee-avatar"
                                                style="background:#0D6EFD;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;">
                                                {{ strtoupper(substr($employee->name, 0, 2)) }}</div>
                                            <div class="employee-details">
                                                <div class="employee-name">{{ $employee->name }}</div>
                                                <div class="employee-email">{{ $employee->employee_id }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $structure ? '₹' . number_format($structure->ctc, 2) : '—' }}</td>
                                    <td>{{ $structure ? $structure->effective_from->format('d M Y') : '—' }}</td>
                                    <td>{{ $structure ? ucfirst($structure->revision_type) : '—' }}</td>
                                    <td>
                                        @if ($structure)
                                            <span class="badge bg-success">Assigned</span>
                                        @else
                                            <span class="badge bg-warning">Not assigned</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if ($structure)
                                            <a href="#" class="btn-icon-view view-structure" data-id="{{ $structure->id }}">
                                                <i class="feather feather-eye"></i> View
                                            </a>
                                        @endif
                                        <a href="#" class="btn-icon-revise assign-structure" data-user-id="{{ $employee->id }}">
                                            <i class="feather feather-edit-3"></i> {{ $structure ? 'Revise' : 'Assign' }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No employees found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{ $employees->links() }}
    </div>
@endsection

@section('create-modal')
    <div class="offcanvas offcanvas-end view-drawer" tabindex="-1" id="viewDrawer" aria-labelledby="viewDrawerLabel">
        <div class="offcanvas-header">
            <h5 id="viewDrawerLabel">Payroll Structure</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="employee-info mb-3" id="viewEmployeeInfo"></div>

            <div id="viewBreakdownContainer"></div>

            <div class="alert alert-info" style="font-size:11px;">
                <i class="feather-info me-1"></i> These are the configured values from the dynamic catalog. Actual
                payslip amounts are prorated by attendance when payroll is processed through the dynamic engine.
            </div>

            <a href="#" class="btn btn-icon-revise" id="viewReviseBtn" style="margin-left:0;">
                <i class="feather feather-edit-3"></i> Revise
            </a>
        </div>
    </div>

    <div class="offcanvas offcanvas-end structure-drawer" tabindex="-1" id="assignDrawer" aria-labelledby="assignDrawerLabel">
        <div class="offcanvas-header">
            <h5 id="assignDrawerLabel">Assign Dynamic Payroll Structure</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div id="assignCurrentNotice" class="alert alert-info d-none" style="font-size:11px;"></div>
            <div id="assignFormError" class="alert alert-danger d-none"></div>

            <form id="assignForm">
                @csrf
                <input type="hidden" name="user_id" id="assign_user_id">

                <div class="form-section">
                    <h6>Assignment</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Employee</label>
                            <input type="text" class="form-control" id="assign_employee_display" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="payroll_structure_id">Start From Structure Template</label>
                            <select class="form-control" name="payroll_structure_id" id="payroll_structure_id">
                                <option value="">-- Ad hoc (pick components below) --</option>
                                @foreach ($structures as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="ctc">Annual CTC (₹) *</label>
                            <input type="number" step="0.01" class="form-control" name="ctc" id="ctc" required>
                            <small class="text-danger error-text ctc_error"></small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="monthly_ctc_display">Monthly CTC (₹)</label>
                            <input type="text" class="form-control" id="monthly_ctc_display" readonly tabindex="-1">
                            {{-- <small class="text-muted" style="font-size:9px;">Annual CTC ÷ 12 — matches what the "Monthly Components" amounts below should add up to.</small> --}}
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="effective_from">Effective From *</label>
                            <input type="date" class="form-control" name="effective_from" id="effective_from" required>
                            <small class="text-danger error-text effective_from_error"></small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="revision_type">Revision Type *</label>
                            <select class="form-control" name="revision_type" id="revision_type" required>
                                <option value="initial">Initial</option>
                                <option value="increment">Increment</option>
                                <option value="promotion">Promotion</option>
                                <option value="demotion">Demotion</option>
                                <option value="correction">Correction</option>
                                <option value="transfer">Transfer</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label for="revision_reason">Reason</label>
                            <input type="text" class="form-control" name="revision_reason" id="revision_reason" placeholder="e.g. Annual increment FY26-27">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h6>Monthly Components</h6>
                    <div id="componentsTotalNotice" class="mb-2" style="font-size:10.5px;font-weight:600;">
                        Total: <span id="componentsTotalDisplay">₹0.00</span> / Monthly CTC <span id="componentsTotalCtcDisplay">₹0.00</span>
                    </div>
                    <div id="componentsError" class="text-danger error-text components_error mb-2"></div>
                    @php $earningComponents = $components->where('component_type', 'earning'); @endphp
                    @foreach (['earning' => 'Earnings', 'deduction' => 'Deductions', 'employer_contribution' => 'Employer Contributions', 'reimbursement' => 'Reimbursements'] as $type => $label)
                        @php $typeComponents = $components->where('component_type', $type); @endphp
                        @if ($typeComponents->isNotEmpty())
                            <div class="mb-2">
                                <div class="text-muted fw-semibold" style="font-size:9.5px;text-transform:uppercase;">{{ $label }}</div>
                                @foreach ($typeComponents as $c)
                                    <div class="component-row" data-component-id="{{ $c->id }}">
                                        <div class="form-check mb-0">
                                            <input type="checkbox" class="form-check-input component-toggle"
                                                name="components[{{ $c->id }}][enabled]" value="1"
                                                id="assign_comp_{{ $c->id }}">
                                        </div>
                                        <div class="comp-name">
                                            <label for="assign_comp_{{ $c->id }}" class="mb-0">{{ $c->name }}</label>
                                        </div>
                                        <div class="method-field">
                                            <select class="form-control component-method" name="components[{{ $c->id }}][calculation_method]">
                                                <option value="fixed_amount">Fixed ₹</option>
                                                <option value="percentage">% Of</option>
                                            </select>
                                        </div>
                                        <div class="value-field amount-field">
                                            <input type="number" step="0.01" min="0" class="form-control"
                                                name="components[{{ $c->id }}][amount]"
                                                value="{{ $c->default_amount }}" placeholder="Amount">
                                        </div>
                                        <div class="value-field percentage-field" style="display:none;">
                                            <input type="number" step="0.001" min="0" max="100" class="form-control"
                                                name="components[{{ $c->id }}][percentage_value]"
                                                value="{{ $c->percentage_value }}" placeholder="%">
                                        </div>
                                    </div>
                                    @if ($earningComponents->isNotEmpty() && in_array($type, ['deduction', 'employer_contribution']))
                                        {{-- Deductions/Employer Contributions only — an Earning itself
                                             switched to "% Of" doesn't get this (it would almost always
                                             mean "% of CTC/Basic", a fixed_base case, not a sum of other
                                             Earnings). Which Earnings components this percentage is
                                             calculated on (e.g. "12% of Basic + HRA"). Sibling of
                                             .component-row, not nested inside it, so it can span the full
                                             row width without fighting that row's fixed-width flex
                                             layout; shown only when this row's method is switched to "%
                                             Of". Checking more boxes
                                             updates the selection live — submitted as
                                             components[id][base_component_ids][]. Pre-checked from the
                                             catalog's own configured default (baseComponents), or from
                                             this employee's existing assignment when revising (see the
                                             overrides prefill in the script below). --}}
                                        <div class="base-components-row" data-component-id="{{ $c->id }}" style="display:none;">
                                            <span class="base-components-label">% Of (Earnings):</span>
                                            @foreach ($earningComponents as $ec)
                                                @if ($ec->id !== $c->id)
                                                    <label class="base-comp-check" data-earning-id="{{ $ec->id }}">
                                                        <input type="checkbox"
                                                            name="components[{{ $c->id }}][base_component_ids][]"
                                                            value="{{ $ec->id }}"
                                                            {{ $c->baseComponents->contains('id', $ec->id) ? 'checked' : '' }}>
                                                        {{ $ec->name }}
                                                    </label>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                </div>

                <div class="row">
                    <div class="col-6">
                        <button type="submit" class="btn btn-primary" id="assignSubmitBtn">
                            <i class="feather-save me-2"></i>Save Assignment
                        </button>
                    </div>
                    <div class="col-6">
                        <a href="#" class="btn btn-modal-cancel float-end" data-bs-dismiss="offcanvas">
                            <i class="feather-x me-2"></i>Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('script-area')
<script>
    // Structure -> component defaults, embedded once, keyed by structure id.
    const structureComponents = {
        @foreach ($structures as $s)
            {{ $s->id }}: [
                @foreach ($s->components as $sc)
                    @php
                        // The template's own "% Of (Earnings)" override takes
                        // precedence; falls back to the catalog's configured
                        // default when this structure component has none —
                        // same precedence order as the per-employee override.
                        $scBaseIds = $sc->baseComponents->isNotEmpty()
                            ? $sc->baseComponents->pluck('id')
                            : $sc->component->baseComponents->pluck('id');
                    @endphp
                    {
                        id: {{ $sc->payroll_component_master_id }},
                        method: '{{ $sc->override_calculation_method ?? $sc->component->calculation_method }}',
                        amount: {{ $sc->override_amount ?? $sc->component->default_amount ?? 'null' }},
                        percentage: {{ $sc->override_percentage ?? $sc->component->percentage_value ?? 'null' }},
                        base_component_ids: [{{ $scBaseIds->implode(',') }}]
                    },
                @endforeach
            ],
        @endforeach
    };

    $(document).ready(function() {
        const drawerEl = document.getElementById('assignDrawer');
        const drawer = drawerEl ? bootstrap.Offcanvas.getOrCreateInstance(drawerEl) : null;

        const viewDrawerEl = document.getElementById('viewDrawer');
        const viewDrawer = viewDrawerEl ? bootstrap.Offcanvas.getOrCreateInstance(viewDrawerEl) : null;
        const typeLabels = {
            earning: 'Earnings',
            deduction: 'Deductions',
            employer_contribution: 'Employer Contributions',
            reimbursement: 'Reimbursements'
        };

        $(document).on('click', '.view-structure', function(e) {
            e.preventDefault();
            const id = $(this).data('id');

            $.ajax({
                url: '{{ url("payroll-employee-structures") }}/' + id,
                type: 'GET',
                success: function(response) {
                    if (!response.success) return;
                    const d = response.data;

                    $('#viewDrawerLabel').text('Payroll Structure — ' + d.employee.name);
                    $('#viewEmployeeInfo').html(
                        '<div class="employee-avatar" style="background:#0D6EFD;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;">' +
                        d.employee.name.substring(0, 2).toUpperCase() + '</div>' +
                        '<div class="employee-details">' +
                        '<div class="employee-name">' + d.employee.name + ' <small class="text-muted">(' + d.employee.employee_id + ')</small></div>' +
                        '<div class="employee-email">Effective ' + d.effective_from +
                        (d.is_current ? ' (current)' : ' – ' + d.effective_to) +
                        ' · CTC ₹' + Number(d.ctc).toLocaleString('en-IN', {minimumFractionDigits: 2}) +
                        ' · ' + d.revision_type + '</div>' +
                        '</div>'
                    );

                    const grouped = {};
                    (d.components || []).forEach(function(c) {
                        grouped[c.type] = grouped[c.type] || [];
                        grouped[c.type].push(c);
                    });

                    let html = '';
                    $.each(typeLabels, function(type, label) {
                        if (!grouped[type] || !grouped[type].length) return;

                        html += '<div class="breakdown-card"><h6>' + label + '</h6>';
                        grouped[type].forEach(function(c) {
                            const value = c.calculation_method === 'percentage'
                                ? (parseFloat(c.percentage_value).toString().replace(/\.?0+$/, '')) + '%'
                                : '₹' + Number(c.amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2});
                            html += '<div class="breakdown-row"><span class="label">' + c.name + '</span><span class="value">' + value + '</span></div>';
                        });
                        html += '</div>';
                    });
                    $('#viewBreakdownContainer').html(html);

                    $('#viewReviseBtn').data('user-id', d.user_id);

                    viewDrawer && viewDrawer.show();
                },
                error: function() {
                    toastr.error('Failed to load structure details.');
                }
            });
        });

        $('#viewReviseBtn').on('click', function(e) {
            e.preventDefault();
            const userId = $(this).data('user-id');
            viewDrawer && viewDrawer.hide();
            openAssignDrawerForUser(userId);
        });

        // Monthly CTC is purely a derived, read-only display — Annual CTC
        // is the one field actually submitted/stored.
        function updateMonthlyCtcDisplay() {
            const annual = parseFloat($('#ctc').val()) || 0;
            $('#monthly_ctc_display').val((annual / 12).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            refreshComponentsTotalDisplay();
        }
        $('#ctc').on('input change', updateMonthlyCtcDisplay);

        // Monthly Components can't add up to more than Monthly CTC — this
        // is a live, best-effort mirror of the server-side check in
        // PayrollEmployeeStructureController::estimateMonthlyComponentsTotal()
        // (same algorithm: fixed-amount rows summed directly, a percentage
        // row's base resolved only from OTHER checked fixed-amount rows in
        // this same form). The server re-validates for real on submit
        // regardless — this is purely so the mistake is visible immediately
        // instead of only after clicking Save.
        function computeMonthlyComponentsTotal() {
            const fixedAmounts = {};
            let total = 0;

            $('.component-row').each(function() {
                const row = $(this);
                if (!row.find('.component-toggle').is(':checked')) return;
                if (row.find('.component-method').val() !== 'fixed_amount') return;
                const amt = parseFloat(row.find('.amount-field input').val()) || 0;
                fixedAmounts[row.data('component-id')] = amt;
                total += amt;
            });

            $('.component-row').each(function() {
                const row = $(this);
                if (!row.find('.component-toggle').is(':checked')) return;
                if (row.find('.component-method').val() !== 'percentage') return;
                const pct = parseFloat(row.find('.percentage-field input').val()) || 0;
                let base = 0;
                $('.base-components-row[data-component-id="' + row.data('component-id') + '"] input[type="checkbox"]:checked')
                    .each(function() {
                        base += fixedAmounts[$(this).val()] || 0;
                    });
                total += base * pct / 100;
            });

            return total;
        }

        function refreshComponentsTotalDisplay() {
            const monthlyCtc = (parseFloat($('#ctc').val()) || 0) / 12;
            const total = computeMonthlyComponentsTotal();
            const overBudget = total > monthlyCtc + 0.01;

            $('#componentsTotalDisplay').text('₹' + total.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            $('#componentsTotalCtcDisplay').text('₹' + monthlyCtc.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            $('#componentsTotalNotice').toggleClass('components-over-budget', overBudget);

            return !overBudget;
        }

        $(document).on('input change', '.component-toggle, .component-method, .amount-field input, .percentage-field input, .base-comp-check input', refreshComponentsTotalDisplay);

        // The "% Of (Earnings)" list must only ever offer Earnings
        // components that are actually enabled/checked for THIS employee's
        // structure — not the tenant's whole Earnings catalog. Hides (and
        // un-checks, so a now-unavailable base can never be silently
        // submitted) any base-comp-check whose own main enable checkbox
        // isn't ticked. Re-run any time an earning's enable state changes.
        function refreshBaseComponentOptions() {
            $('.base-comp-check[data-earning-id]').each(function() {
                const earningId = $(this).data('earning-id');
                const isEnabled = $('#assign_comp_' + earningId).is(':checked');
                $(this).toggle(isEnabled);
                if (!isEnabled) {
                    $(this).find('input[type="checkbox"]').prop('checked', false);
                }
            });
        }

        $(document).on('change', '.component-method', function() {
            const row = $(this).closest('.component-row');
            const isPercentage = $(this).val() === 'percentage';
            row.find('.amount-field').toggle(!isPercentage);
            row.find('.percentage-field').toggle(isPercentage);

            const componentId = row.data('component-id');
            $('.base-components-row[data-component-id="' + componentId + '"]').toggle(isPercentage);
            if (isPercentage) refreshBaseComponentOptions();
        });

        $(document).on('change', '.component-toggle', refreshBaseComponentOptions);

        $('#payroll_structure_id').on('change', function() {
            const structureId = $(this).val();

            $('.component-row').each(function() {
                $(this).find('.component-toggle').prop('checked', false);
            });

            if (!structureId || !structureComponents[structureId]) {
                // Ad hoc (no template) — every component freely selectable again.
                $('.component-row').removeClass('component-row-locked')
                    .find('.component-toggle').prop('disabled', false);
                return;
            }

            // "Only the components selected in that structure should be
            // used" — lock down every component NOT in this structure so it
            // can't be manually checked, instead of just pre-checking the
            // matching ones and leaving the rest freely editable.
            const allowedIds = structureComponents[structureId].map(c => c.id);
            $('.component-row').each(function() {
                const row = $(this);
                const componentId = parseInt(row.data('component-id'), 10);
                const allowed = allowedIds.includes(componentId);
                row.toggleClass('component-row-locked', !allowed);
                row.find('.component-toggle').prop('disabled', !allowed);
            });

            structureComponents[structureId].forEach(function(comp) {
                const row = $('.component-row[data-component-id="' + comp.id + '"]');
                if (!row.length) return;

                row.find('.component-toggle').prop('checked', true);
                row.find('.component-method').val(comp.method).trigger('change');
                if (comp.method === 'percentage') {
                    row.find('.percentage-field input').val(comp.percentage);

                    // Pre-check from this template's own base-component
                    // selection (already resolved server-side to the
                    // template's override, or the catalog default if it
                    // has none — see structureComponents above).
                    const baseIds = (comp.base_component_ids || []).map(String);
                    $('.base-components-row[data-component-id="' + comp.id + '"] input[type="checkbox"]')
                        .each(function() {
                            $(this).prop('checked', baseIds.includes(String($(this).val())));
                        });
                } else {
                    row.find('.amount-field input').val(comp.amount);
                }
            });

            // Run once after every row's enable-state is finalized above —
            // the per-row .component-method 'change' trigger already calls
            // this too, but iteration order within structureComponents[...]
            // isn't guaranteed earnings-first, so a row-level call could run
            // before its sibling earnings are checked yet.
            refreshBaseComponentOptions();
            refreshComponentsTotalDisplay();
        });

        function resetAssignForm() {
            $('#assignForm')[0].reset();
            $('.component-row .amount-field').show();
            $('.component-row .percentage-field').hide();
            $('.component-row').removeClass('component-row-locked')
                .find('.component-toggle').prop('disabled', false);
            // Native reset() restores each checkbox's checked state (back to
            // the catalog default) but doesn't fire 'change', so the
            // visibility toggle from the .component-method handler has to
            // be re-applied manually here.
            $('.base-components-row').hide();
            refreshBaseComponentOptions();
            updateMonthlyCtcDisplay();
            $('.error-text').text('');
            $('#assignFormError').addClass('d-none').text('');
            $('#assignCurrentNotice').addClass('d-none').text('');
            $('#effective_from').val('{{ now()->toDateString() }}');
            $('#revision_type').val('initial');
        }

        function openAssignDrawerForUser(userId) {
            $.ajax({
                url: '{{ url("payroll-employee-structures/for-user") }}/' + userId,
                type: 'GET',
                success: function(response) {
                    if (!response.success) return;
                    const d = response.data;

                    resetAssignForm();
                    $('#assign_user_id').val(d.employee.id);
                    $('#assign_employee_display').val(d.employee.name + ' (' + d.employee.employee_id + ')');

                    if (d.current) {
                        $('#ctc').val(d.current.ctc);
                        updateMonthlyCtcDisplay();
                        $('#effective_from').val(d.current.effective_from);
                        $('#revision_type').val('increment');
                        $('#assignCurrentNotice').removeClass('d-none').html(
                            '<i class="feather-info me-1"></i> This employee already has a structure effective ' +
                            d.current.effective_from_display + ' (CTC ₹' + Number(d.current.ctc).toLocaleString('en-IN', {minimumFractionDigits: 2}) + '). ' +
                            'Saving below creates a new version and supersedes it.'
                        );

                        (d.selected_component_ids || []).forEach(function(componentId) {
                            $('#assign_comp_' + componentId).prop('checked', true);
                        });

                        $.each(d.overrides || {}, function(componentId, values) {
                            const row = $('.component-row[data-component-id="' + componentId + '"]');
                            if (!row.length) return;
                            row.find('.component-method').val(values.calculation_method).trigger('change');
                            if (values.calculation_method === 'percentage') {
                                row.find('.percentage-field input').val(values.percentage_value);

                                // This employee's existing assignment already has
                                // its own "% Of" base selection (possibly a
                                // per-assignment override, not just the catalog
                                // default) — replace whatever the page loaded
                                // with those checkbox states as the true source
                                // of truth for revising this employee.
                                const baseIds = (values.base_component_ids || []).map(String);
                                $('.base-components-row[data-component-id="' + componentId + '"] input[type="checkbox"]')
                                    .each(function() {
                                        $(this).prop('checked', baseIds.includes(String($(this).val())));
                                    });
                            } else {
                                row.find('.amount-field input').val(values.amount);
                            }
                        });

                        refreshBaseComponentOptions();
                        refreshComponentsTotalDisplay();

                        $('#assignDrawerLabel').text('Revise Dynamic Payroll Structure');
                    } else {
                        $('#assignDrawerLabel').text('Assign Dynamic Payroll Structure');
                    }

                    drawer && drawer.show();
                },
                error: function() {
                    toastr.error('Failed to load employee details.');
                }
            });
        }

        $('#assignStructureBtn').on('click', function(e) {
            e.preventDefault();
            // No specific employee context — the header button just resets
            // and opens; the drawer requires selecting a row's Assign/Revise
            // to pick an employee since assignment is always per-employee.
            toastr.info('Pick "Assign" / "Revise" next to an employee below.');
        });

        $(document).on('click', '.assign-structure', function(e) {
            e.preventDefault();
            openAssignDrawerForUser($(this).data('user-id'));
        });

        $('#assignForm').on('submit', function(e) {
            e.preventDefault();
            $('.error-text').text('');
            $('#assignFormError').addClass('d-none').text('');

            // Instant feedback before the round-trip — the server
            // re-validates this for real regardless (estimateMonthlyComponentsTotal()
            // in the controller), this is purely so a submit doesn't have to
            // fail a request first to find out.
            if (!refreshComponentsTotalDisplay()) {
                $('.components_error').text('Selected components exceed the Monthly CTC — reduce the amounts/percentages or increase the CTC.');
                toastr.error('Monthly Components exceed the Monthly CTC.');
                return;
            }

            $.ajax({
                url: '{{ route("payroll-employee-structures.store") }}',
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    if (response.success) {
                        drawer && drawer.hide();
                        toastr.success(response.message);
                        setTimeout(() => location.reload(), 1200);
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422 && xhr.responseJSON.errors) {
                        $.each(xhr.responseJSON.errors, function(key, value) {
                            $('.' + key + '_error').text(Array.isArray(value) ? value[0] : value);
                        });
                        toastr.error('Please fix the validation errors');
                    } else {
                        $('#assignFormError').removeClass('d-none').text(xhr.responseJSON?.message || 'Something went wrong.');
                        toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                    }
                }
            });
        });

        if (typeof toastr !== 'undefined') {
            toastr.options = {
                closeButton: true,
                progressBar: true,
                positionClass: 'toast-top-right',
                timeOut: '3000'
            };
        }
    });
</script>
@endsection
