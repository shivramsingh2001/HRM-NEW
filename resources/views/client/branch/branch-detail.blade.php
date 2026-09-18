@extends('client.layout.master')

@section('style')
    <style>
        .employee-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }

        .br-detail-badge {
            padding: 3px 10px;
            border-radius: 30px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .2px;
        }

        .badge-active { background: #3b82f6; color: #fff; }
        .badge-inactive { background: #1e3a8a; color: #fff; }

        .personal-info .input-group-text {
            background: #e3edfe;
            color: #1e3a8a;
            border-color: #dfe5f0;
        }

        .personal-info .form-control[readonly] {
            background: #f8fafc;
            border-color: #dfe5f0;
            color: #1a2236;
        }

        .customers-nav-tabs .nav-link.active {
            color: #1e3a8a;
            border-color: #dfe5f0 #dfe5f0 #fff;
        }

        .customers-nav-tabs .nav-link { color: #6b7385; }

        #membersTable .badge.bg-success { background-color: #3b82f6 !important; }
        #membersTable .badge.bg-danger { background-color: #1e3a8a !important; }

        .compact-modal .modal-header .fs-18 { font-size: 13px !important; }
        .compact-modal .form-group { margin-bottom: 0; }
        .compact-modal .form-control,
        .compact-modal .form-check-label { font-size: 11.5px; }
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
                <h5 class="m-b-10">Branch Management</h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.5rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('branch.index') }}">Branch</a></li>
                <li class="breadcrumb-item">Branch Details</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <span class="br-detail-badge {{ $branch->status ? 'badge-active' : 'badge-inactive' }}">
                {{ $branch->status ? 'Active' : 'Inactive' }}
            </span>
        </div>
    </div>

    <div class="main-content" style="padding: 20px !important;">
        <div class="row">
            <div class="col-lg-12">
                <div class="card border-top-0">
                    <div class="card-header p-0">
                        <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="myTab" role="tablist">
                            <li class="nav-item flex-fill border-top" role="presentation">
                                <a href="javascript:void(0);" class="nav-link active" data-bs-toggle="tab"
                                    data-bs-target="#profileTab" role="tab">Branch Information</a>
                            </li>
                        </ul>
                    </div>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="profileTab" role="tabpanel">
                            <div class="card-body personal-info">
                                <div class="mb-4 d-flex align-items-center justify-content-between">
                                    <h5 class="fw-bold mb-0 me-4">
                                        <span class="d-block mb-2">Branch Information:</span>
                                        <span class="fs-12 fw-normal text-muted text-truncate-1-line">Read-only
                                            overview — use the edit icon on the Branch card to update these
                                            details.</span>
                                    </h5>
                                </div>

                                <div class="row mb-4 align-items-center">
                                    <div class="col-lg-3">
                                        <label class="fw-semibold">Branch Name: </label>
                                    </div>
                                    <div class="col-lg-9">
                                        <div class="input-group">
                                            <div class="input-group-text"><i class="feather-home"></i></div>
                                            <input type="text" value="{{ $branch->name }}" class="form-control" readonly>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4 align-items-center">
                                    <div class="col-lg-3">
                                        <label class="fw-semibold">Branch Manager: </label>
                                    </div>
                                    <div class="col-lg-9">
                                        <div class="input-group">
                                            <div class="input-group-text"><i class="feather-user"></i></div>
                                            <input type="text" value="{{ $branch->branchHead->name ?? 'N/A' }}" class="form-control" readonly>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4 align-items-center">
                                    <div class="col-lg-3">
                                        <label class="fw-semibold">Address: </label>
                                    </div>
                                    <div class="col-lg-9">
                                        <div class="input-group">
                                            <div class="input-group-text"><i class="feather-map-pin"></i></div>
                                            <textarea class="form-control" readonly rows="2">{{ $branch->address }}</textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4 align-items-center">
                                    <div class="col-lg-3">
                                        <label class="fw-semibold">City / State / Country: </label>
                                    </div>
                                    <div class="col-lg-9">
                                        <div class="input-group">
                                            <div class="input-group-text"><i class="feather-globe"></i></div>
                                            <input type="text" value="{{ collect([$branch->city, $branch->state, $branch->country])->filter()->implode(', ') ?: 'N/A' }}" class="form-control" readonly>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4 align-items-center">
                                    <div class="col-lg-3">
                                        <label class="fw-semibold">Postal Code: </label>
                                    </div>
                                    <div class="col-lg-9">
                                        <div class="input-group">
                                            <div class="input-group-text"><i class="feather-hash"></i></div>
                                            <input type="text" value="{{ $branch->postal_code ?? 'N/A' }}" class="form-control" readonly>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4 align-items-center">
                                    <div class="col-lg-3">
                                        <label class="fw-semibold">Phone: </label>
                                    </div>
                                    <div class="col-lg-9">
                                        <div class="input-group">
                                            <div class="input-group-text"><i class="feather-phone"></i></div>
                                            <input type="text" value="{{ $branch->phone ?? 'N/A' }}" class="form-control" readonly>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4 align-items-center">
                                    <div class="col-lg-3">
                                        <label class="fw-semibold">Email: </label>
                                    </div>
                                    <div class="col-lg-9">
                                        <div class="input-group">
                                            <div class="input-group-text"><i class="feather-mail"></i></div>
                                            <input type="text" value="{{ $branch->email ?? 'N/A' }}" class="form-control" readonly>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4 align-items-center">
                                    <div class="col-lg-3">
                                        <label class="fw-semibold">Description: </label>
                                    </div>
                                    <div class="col-lg-9">
                                        <div class="input-group">
                                            <div class="input-group-text"><i class="feather-file-text"></i></div>
                                            <textarea class="form-control" readonly rows="3">{{ $branch->description }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body personal-info">
                                <div class="mb-4 d-flex align-items-center justify-content-between">
                                    <h5 class="fw-bold mb-0 me-4">
                                        <span class="d-block mb-2">Branch Members:</span>
                                        <span class="fs-12 fw-normal text-muted text-truncate-1-line">
                                            Total Members: {{ $users->count() }}
                                        </span>
                                    </h5>
                                    @if (in_array($role, ['admin', 'hr']))
                                        <a href="javascript:void(0);" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                            data-bs-target="#assignEmployeesModal">
                                            <i class="feather-users me-2"></i>Assign Employees
                                        </a>
                                    @endif
                                </div>

                                <div class="row mb-4 align-items-center">
                                    <div class="col-12">
                                        <div class="table-responsive">
                                            <table class="table table-hover" id="membersTable">
                                                <thead>
                                                    <tr>
                                                        <th class="text-start">Employee</th>
                                                        <th class="text-center">Designation</th>
                                                        <th class="text-center">Employee ID</th>
                                                        <th class="text-center">Joining Date</th>
                                                        <th class="text-center">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse($users as $employee)
                                                        <tr>
                                                            <td>
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <div class="avatar-image" style="width: 32px; height: 32px;">
                                                                        <div class="employee-avatar">
                                                                            {{ strtoupper(substr($employee->name, 0, 2)) }}
                                                                        </div>
                                                                    </div>
                                                                    <div>
                                                                        <span class="d-block fw-medium">{{ $employee->name }}
                                                                            <small>({{ $employee->employee_id }})</small></span>
                                                                        <span class="fs-11 text-muted">{{ $employee->email }}</span>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td class="text-center">
                                                                {{ $employee->jobDetails->designation->name ?? ($employee->jobDetails->Designation->name ?? 'N/A') }}
                                                            </td>
                                                            <td class="text-center">
                                                                {{ $employee->employee_id ?? ($employee->jobDetails->employee_id ?? 'N/A') }}
                                                            </td>
                                                            <td class="text-center">
                                                                @if ($employee->jobDetails && $employee->jobDetails->joining_date)
                                                                    <span class="fw-medium">{{ \Carbon\Carbon::parse($employee->jobDetails->joining_date)->format('d M Y') }}</span>
                                                                    <span class="fs-11 text-muted d-block">{{ \Carbon\Carbon::parse($employee->jobDetails->joining_date)->diffForHumans() }}</span>
                                                                @else
                                                                    N/A
                                                                @endif
                                                            </td>
                                                            <td class="text-center">
                                                                @if ($employee->status == 1)
                                                                    <span class="badge bg-success">Active</span>
                                                                @else
                                                                    <span class="badge bg-danger">Inactive</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="5" class="text-center text-muted py-4">
                                                                <i class="feather-users fs-1 d-block mb-2"></i>
                                                                No members found in this branch.
                                                            </td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    @if (in_array($role, ['admin', 'hr']))
        <!-- Assign Employees Modal -->
        <div class="modal fade-scale" id="assignEmployeesModal" tabindex="-1" aria-labelledby="assignEmployeesModal" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-md compact-modal" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="d-flex flex-column mb-0">
                            <span class="fs-18 fw-bold mb-1">Assign Employees</span>
                            <small class="d-block fs-11 fw-normal text-muted">Assign employees to {{ $branch->name }}</small>
                        </h2>
                        <a href="javascript:void(0)" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                            <i class="feather-x text-danger"></i>
                        </a>
                    </div>
                    <div class="modal-body p-0">
                        <div class="card m-0">
                            <div class="card-body">
                                <form action="{{ route('branch.assign-employees', ['id' => encrypt($branch->id)]) }}" method="POST" id="assignEmployeesForm">
                                    @csrf
                                    <div id="assignFormError" class="alert alert-danger d-none"></div>
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <label class="fw-semibold d-block mb-2">Assign</label>
                                            <div class="d-flex gap-3">
                                                <div class="form-check">
                                                    <input class="form-check-input assign-type-radio" type="radio" name="assign_type"
                                                        id="assign_type_user" value="user" checked>
                                                    <label class="form-check-label" for="assign_type_user">Selected employees</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input assign-type-radio" type="radio" name="assign_type"
                                                        id="assign_type_department" value="department">
                                                    <label class="form-check-label" for="assign_type_department">By department</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input assign-type-radio" type="radio" name="assign_type"
                                                        id="assign_type_all" value="all">
                                                    <label class="form-check-label" for="assign_type_all">Everyone</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 mb-3 assign-type-panel" id="assign_panel_user">
                                            <label class="fw-semibold" for="assign_user_ids">Employees *</label>
                                            <select class="form-control select2" name="user_ids[]" id="assign_user_ids" multiple>
                                                @foreach (\App\Models\User::where('status', 1)->orderBy('name')->get() as $employee)
                                                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                                @endforeach
                                            </select>
                                            <small class="text-danger error-text user_ids_error"></small>
                                        </div>

                                        <div class="col-12 mb-3 assign-type-panel d-none" id="assign_panel_department">
                                            <label class="fw-semibold" for="assign_department_id">Department *</label>
                                            <select class="form-control" name="department_id" id="assign_department_id">
                                                <option value="">-- Select Department --</option>
                                                @foreach (\App\Models\Department::where('status', 1)->orderBy('name')->get() as $department)
                                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                                @endforeach
                                            </select>
                                            <small class="text-danger error-text department_id_error"></small>
                                        </div>

                                        <div class="col-6">
                                            <button class="btn btn-primary" type="submit">
                                                <i class="feather-users me-2"></i>Assign
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <a href="javascript:void(0)" class="btn btn-modal-cancel float-end" data-bs-dismiss="modal">
                                                <i class="feather-x me-2"></i>Cancel
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('script-area')
    <script>
        $(document).ready(function() {
            $('.assign-type-radio').on('change', function() {
                $('.assign-type-panel').addClass('d-none');
                $('#assign_panel_' + $(this).val()).removeClass('d-none');
            });

            $('#assignEmployeesForm').on('submit', function(e) {
                e.preventDefault();
                $('.error-text').text('');
                $('#assignFormError').addClass('d-none').text('');

                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: $(this).serialize(),
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function(response) {
                        if (response.success) {
                            $('#assignEmployeesModal').modal('hide');
                            toastr.success(response.message);
                            setTimeout(() => location.reload(), 1000);
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) { $('.' + key + '_error').text(value[0]); });
                            toastr.error('Please fix the validation errors');
                        } else {
                            $('#assignFormError').removeClass('d-none').text(xhr.responseJSON?.message || 'Something went wrong.');
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });
            });

            if (typeof toastr !== 'undefined') {
                toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 3000 };
            }
        });
    </script>
@endsection
