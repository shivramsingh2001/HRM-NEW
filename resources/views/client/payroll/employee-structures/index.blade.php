@extends('client.layout.master')

@section('style')
<style>
    /* ==================== ALL-BLUE THEME ==================== */
    #assignmentsTable .badge { font-size: 10px; padding: 3px 9px; font-weight: 700; }
    #assignmentsTable .badge.bg-success { background-color: #2563eb !important; }
    #assignmentsTable .badge.bg-warning { background-color: #93c5fd !important; color: #1e3a8a !important; }

    /* search bar */
    .structure-search { position: relative; max-width: 360px; }
    .structure-search input {
        padding-left: 34px; border-radius: 999px; border: 1px solid #dfe5f0; font-size: 12px;
        background: #fff; transition: all .15s;
    }
    .structure-search input:focus {
        border-color: #1e3a8a; box-shadow: 0 0 0 .18rem rgba(30, 58, 138, .12); outline: none;
    }
    .structure-search i {
        position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #93a1b8; font-size: 13px;
    }
    .structure-search .btn-search {
        border-radius: 999px; background: #1e3a8a; border-color: #1e3a8a; color: #fff; font-size: 11.5px; padding: 6px 16px;
    }
    .structure-search .btn-search:hover { background: #16295e; border-color: #16295e; }

    /* action buttons */
    .btn-icon-view, .btn-icon-revise {
        height: 30px; padding: 0 10px; font-size: 11px; font-weight: 600;
        display: inline-flex; align-items: center; justify-content: center; gap: 5px;
        border-radius: 8px; border: 1px solid; transition: all .15s;
    }
    .btn-icon-view { border-color: #bfd3f7; background: #eef3fd; color: #1e3a8a; }
    .btn-icon-view:hover { background: #dbe6fb; border-color: #1e3a8a; }
    .btn-icon-revise { border-color: #1e3a8a; background: #1e3a8a; color: #fff; margin-left: 6px; }
    .btn-icon-revise:hover { background: #16295e; border-color: #16295e; color: #fff; }
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
    .view-drawer .breakdown-row .value { font-weight: 700; color: #1e3a8a; }

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
    .structure-drawer .btn { padding: 4px 12px; font-size: 11px; border-radius: 7px; }
    .structure-drawer .btn-primary { background: #1e3a8a; border-color: #1e3a8a; }
    .structure-drawer .btn-primary:hover { background: #16295e; border-color: #16295e; }
    .structure-drawer .btn-modal-cancel { background: #f4f6fb; border-color: #dfe5f0; color: #475569; }
    .structure-drawer .btn-modal-cancel:hover { background: #e3edfe; border-color: #1e3a8a; color: #1e3a8a; }
</style>
@endsection

@php
    $user = Auth::user();
    $role = $user->role;
@endphp

@section('content-area')
    <div class="content-area-header sticky-top">
        <div class="page-header-left d-flex align-items-center gap-2">
            <div class="page-header-title">
                <h5 class="m-b-10">Employee Payroll Structures (Dynamic)</h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.57rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Employee Payroll Structures</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="hstack gap-2">
                @if (in_array($role, ['admin', 'hr']))
                    <a href="#" class="btn btn-light-brand btn-sm rounded-pill" id="assignStructureBtn">
                        <i class="feather-plus me-1"></i>Assign Structure
                    </a>
                @endif
            </div>
        </div>
    </div>

    <div class="content-area-body">
        <form method="GET" class="mb-3">
            <div class="structure-search d-inline-flex align-items-center gap-2">
                <div class="position-relative flex-grow-1">
                    <i class="feather-search"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search by name or employee ID..." value="{{ request('search') }}">
                </div>
                <button type="submit" class="btn btn-search">Search</button>
            </div>
        </form>

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
                                                style="background:#1e3a8a;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;">
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
                        <div class="col-12 mb-3">
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
                    <h6>Components</h6>
                    <div id="componentsError" class="text-danger error-text components_error mb-2"></div>
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
                                                <option value="percentage">% of Basic</option>
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
                    {
                        id: {{ $sc->payroll_component_master_id }},
                        method: '{{ $sc->override_calculation_method ?? $sc->component->calculation_method }}',
                        amount: {{ $sc->override_amount ?? $sc->component->default_amount ?? 'null' }},
                        percentage: {{ $sc->override_percentage ?? $sc->component->percentage_value ?? 'null' }}
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
                        '<div class="employee-avatar" style="background:#1e3a8a;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;">' +
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

        $(document).on('change', '.component-method', function() {
            const row = $(this).closest('.component-row');
            const isPercentage = $(this).val() === 'percentage';
            row.find('.amount-field').toggle(!isPercentage);
            row.find('.percentage-field').toggle(isPercentage);
        });

        $('#payroll_structure_id').on('change', function() {
            const structureId = $(this).val();

            $('.component-row').each(function() {
                $(this).find('.component-toggle').prop('checked', false);
            });

            if (!structureId || !structureComponents[structureId]) {
                return;
            }

            structureComponents[structureId].forEach(function(comp) {
                const row = $('.component-row[data-component-id="' + comp.id + '"]');
                if (!row.length) return;

                row.find('.component-toggle').prop('checked', true);
                row.find('.component-method').val(comp.method).trigger('change');
                if (comp.method === 'percentage') {
                    row.find('.percentage-field input').val(comp.percentage);
                } else {
                    row.find('.amount-field input').val(comp.amount);
                }
            });
        });

        function resetAssignForm() {
            $('#assignForm')[0].reset();
            $('.component-row .amount-field').show();
            $('.component-row .percentage-field').hide();
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
                            } else {
                                row.find('.amount-field input').val(values.amount);
                            }
                        });

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
