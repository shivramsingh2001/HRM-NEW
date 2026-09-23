@extends('client.layout.master')

@section('content-area')
    <style>
        .bud-sm { font-size: 12px; padding: 3px 8px; }
        .bud-lbl { font-size: 11.5px; font-weight: 600; color: #64748b; }
    </style>
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Expense Budgets</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('expense.view-all') }}">Expenses</a></li>
                <li class="breadcrumb-item active">Budgets</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="d-flex gap-2 align-items-center flex-nowrap text-nowrap">
                <form method="GET" class="d-flex gap-1 align-items-center flex-nowrap mb-0">
                    <label class="mb-0 text-muted fw-semibold" style="font-size:12px">Fiscal year</label>
                    <select name="fiscal_year" class="form-select form-select-sm bud-sm" style="width:auto;min-width:90px" onchange="this.form.submit()">
                        @foreach ($years as $y)
                            <option value="{{ $y }}" @selected($y === $fiscalYear)>{{ $y }}</option>
                        @endforeach
                    </select>
                </form>
                <button type="button" class="btn btn-primary btn-sm text-nowrap" data-bs-toggle="modal" data-bs-target="#budgetModal"
                    onclick="openBudgetCreate()"><i class="feather-plus me-1"></i>Add budget</button>
                <a href="{{ route('expense.view-all') }}" class="btn btn-light-brand btn-sm text-nowrap"><i
                        class="feather-arrow-left me-1"></i>Back</a>
            </div>
        </div>
    </div>

    <div class="main-content" style="padding: 20px !important;">
        <div class="alert alert-info small">
            A budget caps <b>approved spend</b> (settlements + reimbursements — advances are cash handed out, not spend) for a
            fiscal year. Leave department / project / category blank to mean "any". <b>Every</b> budget that matches a claim
            is a separate cap. <b>Warn</b> approves but tells the approver; <b>Block</b> refuses the approval.
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Sr. No.</th>
                            <th>Scope</th>
                            <th class="text-end">Budget</th>
                            <th class="text-end">Used</th>
                            <th class="text-end">Remaining</th>
                            <th style="min-width:170px">Usage</th>
                            <th>When exceeded</th>
                            <th class="text-end text-nowrap" style="width:90px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($budgets as $b)
                            @php
                                $bar = $b['percent'] >= 100 ? 'bg-danger' : ($b['percent'] >= 80 ? 'bg-warning' : 'bg-success');
                            @endphp
                            <tr>
                                <td class="text-muted">{{ $loop->iteration }}</td>
                                <td class="fw-semibold">{{ $b['label'] }}</td>
                                <td class="text-end">₹{{ number_format($b['allocated'], 2) }}</td>
                                <td class="text-end">₹{{ number_format($b['used'], 2) }}</td>
                                <td class="text-end {{ $b['remaining'] < 0 ? 'text-danger fw-bold' : '' }}">
                                    ₹{{ number_format($b['remaining'], 2) }}</td>
                                <td>
                                    <div class="progress" style="height:8px;">
                                        <div class="progress-bar {{ $bar }}" style="width: {{ min(100, $b['percent']) }}%"></div>
                                    </div>
                                    <div class="small text-muted">{{ $b['percent'] }}%</div>
                                </td>
                                <td>
                                    @if ($b['enforcement'] === 'block')
                                        <span class="badge bg-danger">Block approval</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Warn only</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="hstack gap-2 justify-content-end flex-nowrap">
                                        <a href="javascript:void(0)" class="avatar-text avatar-md" title="Edit"
                                            data-bs-toggle="modal" data-bs-target="#budgetEditModal"
                                            onclick="openBudgetEdit({{ $b['id'] }}, @js($b['label']), {{ $b['allocated'] }}, '{{ $b['enforcement'] }}')"><i
                                                class="feather-edit-3"></i></a>
                                        <a href="javascript:void(0)" class="avatar-text avatar-md bg-soft-danger text-danger" title="Delete"
                                            onclick="deleteBudget({{ $b['id'] }}, @js($b['label']))"><i
                                                class="feather-trash-2"></i></a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">No budgets for {{ $fiscalYear }}. Add one to
                                    start tracking spend against a cap.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    <div class="modal fade" id="budgetModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
            <div class="modal-content">
                <div class="modal-header py-2 px-3">
                    <h5 class="modal-title fs-6">Add budget</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label bud-lbl mb-1">Fiscal year *</label>
                            <select id="b_fy" class="form-select form-select-sm bud-sm">
                                @foreach ($years as $y)
                                    <option value="{{ $y }}" @selected($y === $fiscalYear)>{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label bud-lbl mb-1">Budget amount (₹) *</label>
                            <input type="number" id="b_amount" class="form-control form-control-sm bud-sm" min="0" step="0.01">
                        </div>
                        <div class="col-4">
                            <label class="form-label bud-lbl mb-1">Department</label>
                            <select id="b_dept" class="form-select form-select-sm bud-sm">
                                <option value="">Any</option>
                                @foreach ($departments as $d)
                                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label bud-lbl mb-1">Project</label>
                            <select id="b_project" class="form-select form-select-sm bud-sm">
                                <option value="">Any</option>
                                @foreach ($projects as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label bud-lbl mb-1">Category</label>
                            <select id="b_type" class="form-select form-select-sm bud-sm">
                                <option value="">Any</option>
                                @foreach ($expenseTypes as $t)
                                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label bud-lbl mb-1">When a claim would exceed it</label>
                            <select id="b_enf" class="form-select form-select-sm bud-sm">
                                <option value="warn">Warn only — approve, but tell the approver</option>
                                <option value="block">Block — refuse the approval</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2 px-3">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="saveBudget">Save budget</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="budgetEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2 px-3">
                    <h5 class="modal-title fs-6">Edit budget</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="fw-semibold mb-2" id="e_label"></div>
                    <label class="form-label bud-lbl mb-1">Budget amount (₹) *</label>
                    <input type="number" id="e_amount" class="form-control form-control-sm bud-sm mb-2" min="0" step="0.01">
                    <label class="form-label bud-lbl mb-1">When exceeded</label>
                    <select id="e_enf" class="form-select form-select-sm bud-sm">
                        <option value="warn">Warn only</option>
                        <option value="block">Block approval</option>
                    </select>
                    <input type="hidden" id="e_id">
                </div>
                <div class="modal-footer py-2 px-3">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="saveEdit">Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        toastr.options = {
            closeButton: true,
            progressBar: true,
            timeOut: 4000
        };
        const csrf = $('meta[name="csrf-token"]').attr('content');
        const base = @json(url('expense/budgets'));

        function done(res) {
            toastr.success(res.message);
            setTimeout(() => location.reload(), 900);
        }

        function fail(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Something went wrong.');
        }

        function openBudgetCreate() {
            $('#b_amount').val('');
            $('#b_dept,#b_project,#b_type').val('');
            $('#b_enf').val('warn');
        }

        $('#saveBudget').on('click', function() {
            $.post(base, {
                _token: csrf,
                fiscal_year: $('#b_fy').val(),
                allocated_amount: $('#b_amount').val(),
                department_id: $('#b_dept').val(),
                project_id: $('#b_project').val(),
                expense_type_id: $('#b_type').val(),
                enforcement: $('#b_enf').val(),
            }).done(done).fail(fail);
        });

        function openBudgetEdit(id, label, amount, enforcement) {
            $('#e_id').val(id);
            $('#e_label').text(label);
            $('#e_amount').val(amount);
            $('#e_enf').val(enforcement);
        }

        $('#saveEdit').on('click', function() {
            $.ajax({
                url: base + '/' + $('#e_id').val(),
                type: 'PUT',
                data: {
                    _token: csrf,
                    allocated_amount: $('#e_amount').val(),
                    enforcement: $('#e_enf').val()
                },
            }).done(done).fail(fail);
        });

        function deleteBudget(id, label) {
            if (!confirm('Delete the budget "' + label + '"? Approvals will no longer be checked against it.')) return;
            $.ajax({
                url: base + '/' + id,
                type: 'DELETE',
                data: {
                    _token: csrf
                }
            }).done(done).fail(fail);
        }
    </script>
@endsection
