@extends('client.layout.master')

@section('style')
<style>
    /* ==================== ALL-BLUE THEME ==================== */
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

    .bonus-filter select {
        border-radius: 999px; border: 1px solid #dfe5f0; font-size: 12px; padding: 6px 14px; max-width: 260px;
    }
    .bonus-filter select:focus { border-color: #1e3a8a; box-shadow: 0 0 0 .18rem rgba(30, 58, 138, .12); }

    #bonusesTable .badge { font-size: 10px; padding: 3px 9px; font-weight: 700; }
    #bonusesTable .badge.bg-warning { background-color: #93c5fd !important; color: #1e3a8a !important; }
    #bonusesTable .badge.bg-success { background-color: #2563eb !important; }
    #bonusesTable .badge.bg-danger { background-color: #bfd3f7 !important; color: #1e3a8a !important; }

    .btn-icon-edit, .btn-approve, .btn-reject {
        height: 28px; padding: 0 10px; font-size: 10.5px; font-weight: 600;
        display: inline-flex; align-items: center; justify-content: center; gap: 4px;
        border-radius: 7px; border: 1px solid; transition: all .15s;
    }
    .btn-icon-edit { border-color: #dfe5f0; background: #f4f6fb; color: #475569; margin-right: 6px; }
    .btn-icon-edit:hover { background: #e3edfe; border-color: #1e3a8a; color: #1e3a8a; }
    .btn-approve { border-color: #1e3a8a; background: #1e3a8a; color: #fff; }
    .btn-approve:hover { background: #16295e; border-color: #16295e; color: #fff; }
    .btn-reject { border-color: #bfd3f7; background: #eef3fd; color: #1e3a8a; margin-left: 6px; }
    .btn-reject:hover { background: #dbe6fb; border-color: #1e3a8a; }
    .btn-icon-edit i, .btn-approve i, .btn-reject i { font-size: 11px; }

    /* ==================== BONUS DRAWER (Add / Edit) ==================== */
    .bonus-drawer { width: 480px; max-width: 92vw; }
    .bonus-drawer .offcanvas-header { padding: 8px 14px; border-bottom: 1px solid #eaeef5; }
    .bonus-drawer .offcanvas-header h5 { font-size: 13px; font-weight: 700; margin: 0; color: #1a2236; }
    .bonus-drawer .offcanvas-body { padding: 10px 14px; }
    .bonus-drawer .form-section { background: #fbfcfe; border: 1px solid #eaeef5; border-radius: 8px; padding: 10px; margin-bottom: 8px; }
    .bonus-drawer label { font-size: 10.5px; font-weight: 600; margin-bottom: 2px; }
    .bonus-drawer .form-control { font-size: 10.5px; padding: 4px 8px; height: auto; }
    .bonus-drawer .row > [class*="col-"] { margin-bottom: 4px !important; }
    .bonus-drawer .btn { padding: 4px 12px; font-size: 11px; border-radius: 7px; }
    .bonus-drawer .btn-primary { background: #1e3a8a; border-color: #1e3a8a; }
    .bonus-drawer .btn-primary:hover { background: #16295e; border-color: #16295e; }
    .bonus-drawer .btn-modal-cancel { background: #f4f6fb; border-color: #dfe5f0; color: #475569; }
    .bonus-drawer .btn-modal-cancel:hover { background: #e3edfe; border-color: #1e3a8a; color: #1e3a8a; }
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
                <h5 class="m-b-10">Payroll Bonuses</h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.57rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Payroll Bonuses</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="hstack gap-2">
                @if (in_array($role, ['admin', 'hr']))
                    <a href="#" class="btn btn-light-brand btn-sm rounded-pill" id="addBonusBtn">
                        <i class="feather-plus me-1"></i>Add Bonus
                    </a>
                @endif
            </div>
        </div>
    </div>

    <div class="content-area-body">
        @php
            $totalCount = $bonuses->total();
            $pendingCount = (clone $bonuses->getCollection())->where('status', 'draft')->count();
        @endphp

        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon"><i class="feather-gift"></i></div>
                <div class="stats-info">
                    <h3>{{ $totalCount }}</h3>
                    <p>Total Bonuses</p>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="feather-clock"></i></div>
                <div class="stats-info">
                    <h3>{{ $pendingCount }}</h3>
                    <p>Pending on this page</p>
                </div>
            </div>
        </div>

        <form method="GET" class="mb-3 bonus-filter">
            <select name="status" class="form-control" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                @foreach (['draft' => 'Pending Approval', 'approved' => 'Approved', 'included_in_payroll' => 'Included in Payroll', 'paid' => 'Paid', 'cancelled' => 'Cancelled/Rejected'] as $val => $label)
                    <option value="{{ $val }}" {{ request('status') == $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </form>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0" id="bonusesTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Type</th>
                                <th>Name</th>
                                <th>Amount</th>
                                <th>Target Month</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($bonuses as $bonus)
                                <tr>
                                    <td>
                                        @if ($bonus->user)
                                            <div class="employee-info">
                                                <div class="employee-avatar"
                                                    style="background:#1e3a8a;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;">
                                                    {{ strtoupper(substr($bonus->user->name, 0, 2)) }}</div>
                                                <div class="employee-details">
                                                    <div class="employee-name">
                                                        {{ $bonus->user->name }}
                                                        <small class="text-muted">({{ $bonus->user->employee_id ?? 'N/A' }})</small>
                                                    </div>
                                                    <div class="employee-email">{{ $bonus->user->email ?? '' }}</div>
                                                </div>
                                            </div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $bonus->bonus_type)) }}</td>
                                    <td>{{ $bonus->name }}</td>
                                    <td>₹{{ number_format($bonus->amount, 2) }}</td>
                                    <td>{{ optional($bonus->targetPeriod)->year_month ?? '—' }}</td>
                                    <td>
                                        <span class="badge {{ $bonus->status === 'draft' ? 'bg-warning' : ($bonus->status === 'cancelled' ? 'bg-danger' : 'bg-success') }}">
                                            {{ ucfirst(str_replace('_', ' ', $bonus->status)) }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        @if ($bonus->status === 'draft' && in_array($role, ['admin', 'hr']))
                                            <a href="#" class="btn-icon-edit edit-bonus" data-id="{{ $bonus->id }}">
                                                <i class="feather feather-edit-3"></i> Edit
                                            </a>
                                            <form action="{{ route('payroll-bonuses.decide', [$bonus->id, 'approve']) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn-approve"><i class="feather-check"></i> Approve</button>
                                            </form>
                                            <form action="{{ route('payroll-bonuses.decide', [$bonus->id, 'reject']) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn-reject"><i class="feather-x"></i> Reject</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No bonuses recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{ $bonuses->links() }}
    </div>
@endsection

@section('create-modal')
    <div class="offcanvas offcanvas-end bonus-drawer" tabindex="-1" id="bonusDrawer" aria-labelledby="bonusDrawerLabel">
        <div class="offcanvas-header">
            <h5 id="bonusDrawerLabel">Add Payroll Bonus</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div id="bonusFormError" class="alert alert-danger d-none"></div>

            <form id="bonusForm">
                @csrf
                <input type="hidden" name="id" id="bonus_id">

                <div class="form-section">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label for="user_id">Employee *</label>
                            <select class="form-control" name="user_id" id="user_id" required>
                                <option value="">-- Select Employee --</option>
                                @foreach ($employees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->name }} ({{ $emp->employee_id }})</option>
                                @endforeach
                            </select>
                            <small class="text-danger error-text user_id_error"></small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="bonus_type">Bonus Type *</label>
                            <select class="form-control" name="bonus_type" id="bonus_type" required>
                                @foreach (['performance' => 'Performance', 'festival' => 'Festival', 'referral' => 'Referral', 'retention' => 'Retention', 'statutory_annual' => 'Statutory Annual Bonus', 'one_off' => 'One-off', 'other' => 'Other'] as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="amount">Amount (₹) *</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="amount" id="amount" required>
                            <small class="text-danger error-text amount_error"></small>
                        </div>
                        <div class="col-12 mb-3">
                            <label for="name">Name / Description *</label>
                            <input type="text" class="form-control" name="name" id="name" placeholder="e.g. Diwali Bonus 2026" required>
                            <small class="text-danger error-text name_error"></small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="target_month">Target Payroll Month *</label>
                            <input type="month" class="form-control" name="target_month" id="target_month" required>
                            <small class="text-danger error-text target_month_error"></small>
                        </div>
                        <div class="col-md-6 mb-3 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_taxable" name="is_taxable" value="1" checked>
                                <label class="form-check-label" for="is_taxable">Taxable</label>
                            </div>
                        </div>
                        <div class="col-12 mb-3">
                            <label for="notes">Notes</label>
                            <textarea class="form-control" name="notes" id="notes" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-6">
                        <button type="submit" class="btn btn-primary" id="bonusSubmitBtn">
                            <i class="feather-save me-2"></i>Save Bonus
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
    $(document).ready(function() {
        const drawerEl = document.getElementById('bonusDrawer');
        const drawer = drawerEl ? bootstrap.Offcanvas.getOrCreateInstance(drawerEl) : null;

        function resetBonusForm() {
            $('#bonusForm')[0].reset();
            $('#bonus_id').val('');
            $('.error-text').text('');
            $('#bonusFormError').addClass('d-none').text('');
            $('#target_month').val('{{ now()->format('Y-m') }}');
            $('#bonusDrawerLabel').text('Add Payroll Bonus');
            $('#bonusSubmitBtn').html('<i class="feather-save me-2"></i>Save Bonus');
        }

        $('#addBonusBtn').on('click', function(e) {
            e.preventDefault();
            resetBonusForm();
            drawer && drawer.show();
        });

        $(document).on('click', '.edit-bonus', function(e) {
            e.preventDefault();
            const id = $(this).data('id');

            $.ajax({
                url: '{{ url("payroll-bonuses") }}/' + id,
                type: 'GET',
                success: function(response) {
                    if (!response.success) return;
                    const d = response.data;

                    resetBonusForm();
                    $('#bonus_id').val(d.id);
                    $('#user_id').val(d.user_id);
                    $('#bonus_type').val(d.bonus_type);
                    $('#name').val(d.name);
                    $('#amount').val(d.amount);
                    $('#target_month').val(d.target_month);
                    $('#is_taxable').prop('checked', !!d.is_taxable);
                    $('#notes').val(d.notes);

                    $('#bonusDrawerLabel').text('Edit Payroll Bonus');
                    $('#bonusSubmitBtn').html('<i class="feather-save me-2"></i>Update Bonus');

                    drawer && drawer.show();
                },
                error: function() {
                    toastr.error('Failed to load bonus details.');
                }
            });
        });

        $('#bonusForm').on('submit', function(e) {
            e.preventDefault();
            $('.error-text').text('');
            $('#bonusFormError').addClass('d-none').text('');

            const id = $('#bonus_id').val();
            const url = id ? ('{{ url("payroll-bonuses") }}/' + id) : '{{ route("payroll-bonuses.store") }}';
            const method = id ? 'PUT' : 'POST';

            $.ajax({
                url: url,
                type: method,
                data: $(this).serialize(),
                success: function(response) {
                    if (response.success) {
                        drawer && drawer.hide();
                        toastr.success(response.message);
                        setTimeout(() => location.reload(), 1000);
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422 && xhr.responseJSON.errors) {
                        $.each(xhr.responseJSON.errors, function(key, value) {
                            $('.' + key + '_error').text(Array.isArray(value) ? value[0] : value);
                        });
                        toastr.error('Please fix the validation errors');
                    } else {
                        $('#bonusFormError').removeClass('d-none').text(xhr.responseJSON?.message || 'Something went wrong.');
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
