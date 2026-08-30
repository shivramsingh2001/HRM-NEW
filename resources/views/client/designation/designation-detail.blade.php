@extends('client.layout.master')

@section('style')
 <style>
        .employee-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #4f46e5;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }
    </style>
@endsection
@php
    $user = Auth::user();
    $role = $user->role;
@endphp
@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Designation Management</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('designation.index') }}">Designations</a></li>
                <li class="breadcrumb-item active">Details</li>
            </ul>
        </div>
    </div>

    <div class="main-content" style="padding: 30px !important;">
        <div class="row">
            <div class="col-lg-12">
                <div class="card border-top-0">
                    <div class="card-header p-0">
                        <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="myTab"
                            role="tablist">
                            <li class="nav-item flex-fill border-top" role="presentation">
                                <a href="#" class="nav-link active" data-bs-toggle="tab" data-bs-target="#profileTab"
                                    role="tab">Designation Information</a>
                            </li>
                        </ul>
                    </div>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="profileTab" role="tabpanel">
                            <div class="card-body personal-info">
                                <div class="mb-4 d-flex align-items-center justify-content-between">
                                    <h5 class="fw-bold mb-0 me-4">
                                        <span class="d-block mb-2">Designation Information:</span>
                                        <span class="fs-12 fw-normal text-muted text-truncate-1-line">* marked
                                            fields must be filled ! </span>
                                    </h5>
                                    @if (in_array($role, ['admin', 'manager']))
                                        <h5 class="fw-bold mb-0">
                                            <a href="javascript:void(0);" class="btn btn-warning" data-bs-toggle="modal"
                                                data-bs-target="#changedesignationDetails">Change
                                                Designation Details</a>
                                        </h5>
                                    @endif
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
                                <div class="card-body personal-info">
                                    <div class="mb-4 d-flex align-items-center justify-content-between">
                                        <h5 class="fw-bold mb-0 me-4">
                                            <span class="d-block mb-2">Department Members:</span>
                                            <span class="fs-12 fw-normal text-muted text-truncate-1-line">
                                                Total Members: {{ $users->count() }}
                                            </span>
                                        </h5>
                                    </div>

                               
                                </div>
                                     <div class="row mb-4 align-items-center">
                                        <div class="col-12">
                                            <div class="table-responsive">
                                                <table class="table table-hover">
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
                                                                    No members found in this department.
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
    <!-- Update Designation Modal -->
    <div class="modal fade" id="changedesignationDetails" tabindex="-1" aria-labelledby="changedesignationDetailsLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Update Designation Details</span>
                        <small class="d-block fs-11 fw-normal text-muted">Update Designation information</small>
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form id="updatedesignationForm"
                                action="{{ route('designation.update', ['id' => encrypt($designation->id)]) }}"
                                method="POST">
                                @csrf

                                <div id="updateFormError" class="alert alert-danger d-none"></div>
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_name">Designation Name *</label>
                                            <input type="text" class="form-control" name="name" id="edit_name"
                                                value="{{ $designation->name }}" placeholder="Enter Designation name">
                                            <small class="text-danger error-text name_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_status">Status *</label>
                                            <select class="form-control" name="status" id="edit_status" required>
                                                <option value="1"
                                                    {{ $designation->status == '1' ? 'selected' : '' }}>
                                                    Active
                                                </option>
                                                <option value="0"
                                                    {{ $designation->status == '0' ? 'selected' : '' }}>
                                                    Inactive
                                                </option>
                                            </select>
                                            <small class="text-danger error-text status_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_description">Description</label>
                                            <textarea class="form-control" name="description" id="edit_description" rows="3"
                                                placeholder="Enter Designation description...">{{ $designation->description }}</textarea>
                                            <small class="text-danger error-text description_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                                                Cancel
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                Update Designation
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        $(document).ready(function() {
            // Update form submission
            $('#updatedesignationForm').on('submit', function(e) {
                e.preventDefault();
                console.log('Update form submitted');

                // Get form data
                var formData = $(this).serialize();
                console.log('Form data:', formData);

                // Reset errors
                $('.error-text').text('');
                $('#updateFormError').addClass('d-none').text('');

                // Show loading
                var submitBtn = $(this).find('button[type="submit"]');
                var originalText = submitBtn.text();
                submitBtn.prop('disabled', true).text('Updating...');

                $.ajax({
                    url: $(this).attr('action'),
                    type: "POST",
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        console.log('Update success:', response);
                        submitBtn.prop('disabled', false).text(originalText);

                        if (response.success) {
                            // Show success message
                            alert('Designation updated successfully!');

                            // Close modal
                            $('#changedesignationDetails').modal('hide');

                            // Reload page to show updated data
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('Update error:', error);
                        console.log('Status:', status);
                        console.log('XHR response:', xhr.responseText);

                        submitBtn.prop('disabled', false).text(originalText);

                        if (xhr.status === 422) {
                            // Validation errors
                            console.log('Validation errors:', xhr.responseJSON.errors);
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) {
                                $('.' + key + '_error').text(value[0]);
                            });
                        } else if (xhr.status === 500) {
                            // Server error
                            $('#updateFormError')
                                .removeClass('d-none')
                                .text('Server error. Please try again later.');
                        } else {
                            // Other errors
                            let errorMsg = xhr.responseJSON?.message ||
                                'Something went wrong. Please try again.';
                            console.log('Error message:', errorMsg);

                            $('#updateFormError')
                                .removeClass('d-none')
                                .text(errorMsg);
                        }
                    }
                });
            });

            // Clear errors when modal is closed
            $('#changedesignationDetails').on('hidden.bs.modal', function() {
                $('.error-text').text('');
                $('#updateFormError').addClass('d-none').text('');
            });
        });
    </script>
@endsection
