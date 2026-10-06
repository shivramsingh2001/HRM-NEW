{{--
    Raise a request for an employee (admin / HR). Posts to the module's
    `on-behalf` route; the request is saved already APPROVED by the caller and
    logged (created_by + audit log "…created_on_behalf", visible on Employee 360
    → Activity). Put it in the page's create-modal section, with
    <x-on-behalf.button module="…" /> in the page header.
    module: loan | overtime | expense | leave | regularization
--}}
@props(['module'])

@php
    $allowed = in_array(auth()->user()->role ?? null, ['admin', 'hr'], true);
@endphp

@if ($allowed)
@php
    $id = 'onBehalf' . ucfirst($module) . 'Modal';
    $config = [
        'loan' => ['title' => 'Create loan request', 'route' => 'loan.approvals.on-behalf', 'note' => 'The loan is created already approved, with its repayment schedule. Disburse it as usual when the money is paid.'],
        'overtime' => ['title' => 'Add overtime for an employee', 'route' => 'overtime.on-behalf', 'note' => 'Saved as approved. Past dates are allowed; the employee\'s overtime eligibility and limits still apply.'],
        'expense' => ['title' => 'Add expense for an employee', 'route' => 'expense.on-behalf', 'note' => 'Saved as approved (budget and advance-balance rules apply). Payment stays a separate step.'],
        'leave' => ['title' => 'Apply leave for an employee', 'route' => 'leave.on-behalf', 'note' => 'Saved as approved and the leave balance is deducted right away. Notice period is not enforced.'],
        'regularization' => ['title' => 'Regularize attendance for an employee', 'route' => 'attendance-regularization.on-behalf', 'note' => 'Saved as approved and the attendance is corrected right away. Any past date; request limits do not apply.'],
    ][$module];

    $employees = \App\Models\User::withoutGlobalScopes()->where('tenant_id', auth()->user()->tenant_id)->where('status', 1)
        ->orderBy('name')->get(['id', 'name', 'employee_id', 'email']);

    $loanCategories = $module === 'loan' ? \App\Models\LoanCategory::where('status', 1)->orderBy('name')->get() : collect();
    $leaveTypes = $module === 'leave' ? \App\Models\LeaveType::where('status', 1)->orderBy('name')->get(['id', 'name']) : collect();
    $expenseTypes = $module === 'expense' ? \App\Models\ExpenseType::where('status', 1)->orderBy('name')->get(['id', 'name']) : collect();
    $projects = $module === 'expense' ? \App\Models\Project::whereNotIn('status', ['completed', 'cancelled'])->orderBy('name')->get(['id', 'name']) : collect();
    $today = now()->toDateString();
@endphp

@once
    {{-- Employee picker options: avatar + name (ID) + email — same design as Leave Credit → Add Manual Credit --}}
    <style>
        .emp-opt { display: flex; align-items: center; gap: 8px; }
        .emp-opt-avatar {
            width: 26px; height: 26px; border-radius: 50%; flex: none;
            background: #EFF6FF; color: #0D6EFD; font-size: 10px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
        }
        .emp-opt-text { display: flex; flex-direction: column; min-width: 0; flex: 1; line-height: 1.25; }
        .emp-opt-name { font-size: 11.5px; font-weight: 600; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .emp-opt-email { font-size: 10px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .emp-opt-dropdown .select2-results__option { padding: 6px 10px; }
        /* Hover keeps the text colours; only a light blue row background. */
        .emp-opt-dropdown .select2-results__option--highlighted,
        .emp-opt-dropdown .select2-results__option--highlighted[aria-selected] { background: #EFF6FF !important; color: #0f172a !important; }
        .emp-opt-dropdown .select2-search__field { font-size: 11.5px; padding: 5px 8px; border-radius: 6px; }
        /* The closed box: the picked name stays inside it, on one line. */
        .emp-opt-select .select2-selection--single { height: 34px !important; padding: 0 28px 0 10px !important; display: flex; align-items: center; border: 1px solid #e2e8f0; border-radius: 8px; background-image: none; }
        .emp-opt-select .select2-selection--single .select2-selection__rendered { padding: 0 !important; line-height: 32px !important; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
        .emp-opt-select .select2-selection--single .select2-selection__arrow { height: 32px !important; top: 1px !important; right: 6px !important; }
    </style>
@endonce

<x-ui.modal :id="$id" :title="$config['title']" size="md">
    <form class="on-behalf-form" action="{{ route($config['route']) }}" method="POST" enctype="multipart/form-data" novalidate>
        @csrf
        <div class="alert alert-danger d-none ob-error py-2 px-3 small"></div>

        <div class="row g-2">
            <div class="col-12">
                <label class="fw-semibold small">Employee *</label>
                <select name="user_id" class="form-control ob-employee" required>
                    <option value="">Select employee</option>
                    @foreach ($employees as $e)
                        <option value="{{ $e->id }}" data-name="{{ $e->name }}" data-empid="{{ $e->employee_id }}" data-email="{{ $e->email }}">{{ $e->name }}{{ $e->employee_id ? ' (' . $e->employee_id . ')' : '' }} {{ $e->email }}</option>
                    @endforeach
                </select>
            </div>

            @if ($module === 'loan')
                <div class="col-12">
                    <label class="fw-semibold small">Type *</label>
                    <select name="loan_kind" class="form-control ob-kind">
                        <option value="loan">Loan (EMI / lump sum)</option>
                        <option value="salary_advance">Salary advance (deducted from one salary month)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="fw-semibold small">Category *</label>
                    <select name="loan_type_id" class="form-control ob-category" required>
                        <option value="">Select category</option>
                        @foreach ($loanCategories as $c)
                            @php $cKind = $c->kind ?? 'loan'; @endphp
                            <option value="{{ $c->id }}" data-kind="{{ $cKind }}">{{ $c->name }}
                                @if ($cKind === 'salary_advance')
                                    ({{ $c->max_percent_of_gross ? rtrim(rtrim(number_format((float) $c->max_percent_of_gross, 2), '0'), '.') . '% of gross' : 'advance' }}{{ $c->max_amount ? ', max ₹' . number_format($c->max_amount) : '' }})
                                @else
                                    ({{ (float) $c->default_interest_rate }}%{{ $c->max_amount ? ', max ₹' . number_format($c->max_amount) : '' }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="fw-semibold small">Amount (₹) *</label>
                    <input type="number" name="amount" class="form-control" min="1" step="0.01" required>
                </div>
                <div class="col-md-6 ob-advance d-none">
                    <label class="fw-semibold small">Against salary of *</label>
                    <select name="advance_month" class="form-control">
                        @for ($i = 0; $i <= \App\Services\Loan\SalaryAdvanceService::MONTHS_AHEAD; $i++)
                            @php $m = now()->startOfMonth()->addMonthsNoOverflow($i); @endphp
                            <option value="{{ $m->format('Y-m') }}">{{ $m->format('F Y') }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-6 ob-loan-only">
                    <label class="fw-semibold small">Repayment *</label>
                    <select name="repayment_type" class="form-control ob-repayment">
                        <option value="emi">Monthly EMI</option>
                        <option value="lumpsum">Lump sum</option>
                    </select>
                </div>
                <div class="col-md-6 ob-emi ob-loan-only">
                    <label class="fw-semibold small">Tenure (months) *</label>
                    <input type="number" name="tenure_months" class="form-control" min="1" max="60">
                </div>
                <div class="col-md-6 ob-lumpsum d-none">
                    <label class="fw-semibold small">Repay within (months) *</label>
                    <input type="number" name="lumpsum_tenure_months" class="form-control" min="1" max="24">
                </div>
                <div class="col-12">
                    <label class="fw-semibold small">Purpose *</label>
                    <input type="text" name="purpose" class="form-control" maxlength="255" required>
                </div>
                <div class="col-12">
                    <label class="fw-semibold small">Description</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
            @elseif ($module === 'overtime')
                <div class="col-md-6">
                    <label class="fw-semibold small">Date *</label>
                    <input type="date" name="date" class="form-control" value="{{ $today }}" required>
                </div>
                <div class="col-md-6">
                    <label class="fw-semibold small">Overtime hours *</label>
                    <input type="number" name="overtime_hours" class="form-control" min="0.5" max="24" step="0.5" required>
                </div>
                <div class="col-12">
                    <label class="fw-semibold small">Reason *</label>
                    <textarea name="reason" class="form-control" rows="2" required></textarea>
                </div>
            @elseif ($module === 'expense')
                <div class="col-md-6">
                    <label class="fw-semibold small">Expense type *</label>
                    <select name="expense_type" class="form-control" required>
                        <option value="">Select type</option>
                        @foreach ($expenseTypes as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="fw-semibold small">Requirement *</label>
                    <select name="requirement_type" class="form-control ob-requirement">
                        <option value="reimbursement">Reimbursement</option>
                        <option value="settlement">Settlement (against an advance)</option>
                        <option value="advance">Advance</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="fw-semibold small">Amount (₹) *</label>
                    <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required>
                </div>
                <div class="col-md-6">
                    <label class="fw-semibold small">Date *</label>
                    <input type="date" name="date" class="form-control" value="{{ $today }}" required>
                </div>
                <div class="col-12">
                    <label class="fw-semibold small">Project</label>
                    <select name="project_id" class="form-control">
                        <option value="">None</option>
                        @foreach ($projects as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="fw-semibold small">Description</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-12">
                    <label class="fw-semibold small">Receipts</label>
                    <input type="file" name="files[]" class="form-control" accept=".jpg,.jpeg,.png,.pdf" multiple>
                </div>
                <div class="col-12 ob-shortfall d-none">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="cover_shortfall" value="1" id="{{ $id }}_shortfall">
                        <label class="form-check-label small" for="{{ $id }}_shortfall">
                            If the employee's advance balance is short, settle what it covers and pay the rest as a reimbursement
                        </label>
                    </div>
                </div>
            @elseif ($module === 'leave')
                <div class="col-12">
                    <label class="fw-semibold small">Leave type *</label>
                    <select name="leave_type" class="form-control" required>
                        <option value="">Select leave type</option>
                        @foreach ($leaveTypes as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="fw-semibold small">From *</label>
                    <input type="date" name="start_date" class="form-control" value="{{ $today }}" required>
                </div>
                <div class="col-md-6">
                    <label class="fw-semibold small">Session</label>
                    <select name="start_session" class="form-control">
                        <option value="fullday">Full day</option>
                        <option value="session1">First half</option>
                        <option value="session2">Second half</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="fw-semibold small">To *</label>
                    <input type="date" name="end_date" class="form-control" value="{{ $today }}" required>
                </div>
                <div class="col-md-6">
                    <label class="fw-semibold small">Session</label>
                    <select name="end_session" class="form-control">
                        <option value="fullday">Full day</option>
                        <option value="session1">First half</option>
                        <option value="session2">Second half</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="fw-semibold small">Reason *</label>
                    <textarea name="reason" class="form-control" rows="2" maxlength="500" required></textarea>
                </div>
            @elseif ($module === 'regularization')
                <div class="col-md-6">
                    <label class="fw-semibold small">Date *</label>
                    <input type="date" name="date" class="form-control" max="{{ $today }}" value="{{ $today }}" required>
                </div>
                <div class="col-md-6">
                    <label class="fw-semibold small">Correction *</label>
                    <select name="request_type" class="form-control ob-regtype">
                        <option value="both">In and out time</option>
                        <option value="in_time">In time only</option>
                        <option value="out_time">Out time only</option>
                        <option value="full_day">Full day present</option>
                        <option value="wfh_not_marked">WFH not marked</option>
                        <option value="technical_issue">Technical issue</option>
                    </select>
                </div>
                <div class="col-md-6 ob-in">
                    <label class="fw-semibold small">In time</label>
                    <input type="time" name="in_time" class="form-control">
                </div>
                <div class="col-md-6 ob-out">
                    <label class="fw-semibold small">Out time</label>
                    <input type="time" name="out_time" class="form-control">
                </div>
                <div class="col-12">
                    <label class="fw-semibold small">Reason * <span class="text-muted fw-normal">(at least 10 characters)</span></label>
                    <textarea name="reason" class="form-control" rows="2" required></textarea>
                </div>
                <div class="col-12">
                    <label class="fw-semibold small">Attachment</label>
                    <input type="file" name="file" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                </div>
            @endif
        </div>

        <div class="small text-muted mt-2">
            <i class="feather-info me-1"></i>{{ $config['note'] }} Logged with your name; the employee is notified.
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary btn-sm ob-submit"><i class="feather-check me-1"></i>Save &amp; approve</button>
            <button type="button" class="btn btn-modal-cancel btn-sm" data-bs-dismiss="modal">Cancel</button>
        </div>
    </form>
</x-ui.modal>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const $modal = $('#{{ $id }}');
        const $form = $modal.find('form.on-behalf-form');

        if ($.fn.select2) {
            const employeeOption = function (item) {
                if (!item.id) return item.text;
                const data = $(item.element).data();
                const name = String(data.name || '');
                const $row = $(
                    '<span class="emp-opt"><span class="emp-opt-avatar"></span>' +
                    '<span class="emp-opt-text"><span class="emp-opt-name"></span><span class="emp-opt-email"></span></span></span>'
                );
                $row.find('.emp-opt-avatar').text(name.split(' ').filter(Boolean).map(w => w[0]).join('').toUpperCase().substring(0, 2) || 'NA');
                $row.find('.emp-opt-name').text(name + (data.empid ? ' (' + data.empid + ')' : ''));
                $row.find('.emp-opt-email').text(data.email || '');
                return $row;
            };
            $form.find('.ob-employee').select2({
                placeholder: 'Select employee',
                width: '100%',
                dropdownParent: $modal,
                dropdownCssClass: 'emp-opt-dropdown',
                templateResult: employeeOption,
                // Search still matches name, ID and email (the option text); the box shows name (ID).
                templateSelection: function (item) {
                    if (!item.id) return item.text;
                    const data = $(item.element).data();
                    return data.name + (data.empid ? ' (' + data.empid + ')' : '');
                },
            });
            $form.find('.ob-employee').next('.select2-container').addClass('emp-opt-select');
        }

        // Module-specific field toggles.
        $form.find('.ob-repayment').on('change', function () {
            const lump = this.value === 'lumpsum';
            $form.find('.ob-emi').toggleClass('d-none', lump);
            $form.find('.ob-lumpsum').toggleClass('d-none', !lump);
        });
        // Loan vs salary advance: an advance picks a salary month, no repayment / tenure; categories filter by type.
        $form.find('.ob-kind').on('change', function () {
            const adv = this.value === 'salary_advance';
            $form.find('.ob-advance').toggleClass('d-none', !adv);
            $form.find('.ob-loan-only').toggleClass('d-none', adv);
            if (adv) { $form.find('.ob-lumpsum').addClass('d-none'); } else { $form.find('.ob-repayment').trigger('change'); }
            const $cat = $form.find('.ob-category');
            $cat.find('option[data-kind]').each(function () {
                $(this).prop('hidden', $(this).data('kind') !== (adv ? 'salary_advance' : 'loan'));
            });
            if ($cat.find('option:selected').prop('hidden')) $cat.val('');
        }).trigger('change');
        $form.find('.ob-requirement').on('change', function () {
            $form.find('.ob-shortfall').toggleClass('d-none', this.value !== 'settlement');
        });
        $form.find('.ob-regtype').on('change', function () {
            $form.find('.ob-in').toggleClass('d-none', !['both', 'in_time'].includes(this.value));
            $form.find('.ob-out').toggleClass('d-none', !['both', 'out_time'].includes(this.value));
        });

        $form.on('submit', function (e) {
            e.preventDefault();
            const $err = $form.find('.ob-error').addClass('d-none').empty();
            const $btn = $form.find('.ob-submit').prop('disabled', true);

            $.ajax({
                url: $form.attr('action'),
                type: 'POST',
                data: new FormData(this),
                processData: false,
                contentType: false,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' },
            }).done(function (res) {
                if (res.success) {
                    $modal.modal('hide');
                    if (typeof toastr !== 'undefined') toastr.success(res.message);
                    setTimeout(() => location.reload(), 900);
                } else {
                    $err.text(res.message || 'Could not save.').removeClass('d-none');
                }
            }).fail(function (xhr) {
                const j = xhr.responseJSON || {};
                let msg = j.message || 'Something went wrong. Please try again.';
                if (j.errors) {
                    msg = Object.values(j.errors).map(v => Array.isArray(v) ? v[0] : v).join('<br>');
                }
                $err.html(msg).removeClass('d-none');
                // A settlement larger than the advance balance: offer the shortfall option.
                if (j.available !== undefined || /balance/i.test(j.message || '')) {
                    $form.find('.ob-shortfall').removeClass('d-none');
                }
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });

        $modal.on('hidden.bs.modal', function () {
            $form[0].reset();
            $form.find('.ob-error').addClass('d-none').empty();
            $form.find('.ob-employee').val('').trigger('change');
            $form.find('.ob-kind, .ob-repayment, .ob-requirement, .ob-regtype').trigger('change');
        });
    });
</script>
@endif
