@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== DESIGNATION DETAIL — all-blue theme (mirrors department detail + client/announcement) ==================== */
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

        .desig-detail-badge {
            padding: 3px 10px;
            border-radius: 30px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .2px;
        }

        .badge-active {
            background: #3b82f6;
            color: #fff;
        }

        .badge-inactive {
            background: #1e3a8a;
            color: #fff;
        }

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

        .customers-nav-tabs .nav-link {
            color: #6b7385;
        }

        #membersTable .badge.bg-success { background-color: #3b82f6 !important; }
        #membersTable .badge.bg-danger { background-color: #1e3a8a !important; }
    </style>
@endsection

@section('content-area')
    <div class="content-area-header sticky-top">
        <div class="page-header-left d-flex align-items-center gap-2">
            <div class="page-header-title">
                <h5 class="m-b-10">Designation Management</h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.5rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('designation.index') }}">Designations</a></li>
                <li class="breadcrumb-item">Details</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <span class="desig-detail-badge {{ $designation->status ? 'badge-active' : 'badge-inactive' }}">
                {{ $designation->status ? 'Active' : 'Inactive' }}
            </span>
        </div>
    </div>

    <div class="main-content" style="padding: 20px !important;">
        <div class="row">
            <div class="col-lg-12">
                <div class="card border-top-0">
                    <div class="card-header p-0">
                        <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="myTab"
                            role="tablist">
                            <li class="nav-item flex-fill border-top" role="presentation">
                                <a href="javascript:void(0);" class="nav-link active" data-bs-toggle="tab"
                                    data-bs-target="#profileTab" role="tab">Designation Information</a>
                            </li>
                        </ul>
                    </div>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="profileTab" role="tabpanel">
                            <div class="card-body personal-info">
                                <div class="mb-4 d-flex align-items-center justify-content-between">
                                    <h5 class="fw-bold mb-0 me-4">
                                        <span class="d-block mb-2">Designation Information:</span>
                                        <span class="fs-12 fw-normal text-muted text-truncate-1-line">Read-only
                                            overview — use the edit icon on the Designation card to update these
                                            details.</span>
                                    </h5>
                                </div>

                                <div class="row mb-4 align-items-center">
                                    <div class="col-lg-3">
                                        <label for="name" class="fw-semibold">Name: </label>
                                    </div>
                                    <div class="col-lg-9">
                                        <div class="input-group">
                                            <div class="input-group-text"><i class="feather-user"></i></div>
                                            <input type="text" id="name"
                                                value="{{ ucfirst($designation->name) ?? '' }}" class="form-control"
                                                readonly placeholder="Designation Name">
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4 align-items-center">
                                    <div class="col-lg-3">
                                        <label for="description" class="fw-semibold">Description: </label>
                                    </div>
                                    <div class="col-lg-9">
                                        <div class="input-group">
                                            <div class="input-group-text"><i class="feather-file-text"></i></div>
                                            <textarea id="description" class="form-control" readonly placeholder="Designation Description" rows="3">{{ $designation->description ?? '' }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body personal-info">
                                <div class="mb-4 d-flex align-items-center justify-content-between">
                                    <h5 class="fw-bold mb-0 me-4">
                                        <span class="d-block mb-2">Designation Members:</span>
                                        <span class="fs-12 fw-normal text-muted text-truncate-1-line">
                                            Total Members: {{ $users->count() }}
                                        </span>
                                    </h5>
                                </div>

                                <div class="row mb-4 align-items-center">
                                    <div class="col-12">
                                        <div class="table-responsive">
                                            <table class="table table-hover" id="membersTable">
                                                <thead>
                                                    <tr>
                                                        <!--<th class="text-center">S. No.</th>-->
                                                        <th class="text-start">Employee</th>
                                                        <th class="text-center">Designation</th>
                                                        <th class="text-center">Employee ID</th>
                                                        <th class="text-center">Joining Date</th>
                                                        <th class="text-center">Status</th>
                                                        <!--<th class="text-end">Action</th>-->
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse($users as $index => $employee)
                                                        <tr>
                                                            <!--<td class="text-center">{{ $index + 1 }}</td>-->
                                                            <td>
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <div class="avatar-image"
                                                                        style="width: 32px; height: 32px;">
                                                                        <div class="employee-avatar">
                                                                            {{ strtoupper(substr($employee->name, 0, 2)) }}
                                                                        </div>
                                                                    </div>
                                                                    <div>
                                                                        <span
                                                                            class="d-block fw-medium">{{ $employee->name }}
                                                                            <small>(
                                                                                {{ $employee->employee_id }})</small></span>
                                                                        <span
                                                                            class="fs-11 text-muted">{{ $employee->email }}</span>
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
                                                                    <span
                                                                        class="fw-medium">{{ \Carbon\Carbon::parse($employee->jobDetails->joining_date)->format('d M Y') }}</span>
                                                                    <span
                                                                        class="fs-11 text-muted d-block">{{ \Carbon\Carbon::parse($employee->jobDetails->joining_date)->diffForHumans() }}</span>
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
                                                            <!--<td class="text-end">-->
                                                            <!--    <div class="dropdown">-->
                                                            <!--        <a href="javascript:void(0);" class="avatar-text avatar-md ms-auto" data-bs-toggle="dropdown">-->
                                                            <!--            <i class="feather-more-vertical"></i>-->
                                                            <!--        </a>-->
                                                            <!--        <div class="dropdown-menu dropdown-menu-end">-->
                                                            <!--            <a href="{{ route('employee.show', encrypt($employee->id)) }}" class="dropdown-item">-->
                                                            <!--                <i class="feather-eye me-2"></i> View Details-->
                                                            <!--            </a>-->
                                                            <!--            <a href="javascript:void(0);" class="dropdown-item" onclick="sendMessage({{ $employee->id }})">-->
                                                            <!--                <i class="feather-message me-2"></i> Send Message-->
                                                            <!--            </a>-->
                                                            <!--        </div>-->
                                                            <!--    </div>-->
                                                            <!--</td>-->
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="6" class="text-center text-muted py-4">
                                                                <i class="feather-users fs-1 d-block mb-2"></i>
                                                                No members found in this designation.
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
