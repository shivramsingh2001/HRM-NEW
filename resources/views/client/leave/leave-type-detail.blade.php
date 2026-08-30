@extends('client.layout.master')

@section('style')
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Leave Type Detail</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('leave-type.index') }}">Leave Types</a></li>
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
                                <a href="javascript:void(0);" class="nav-link active" data-bs-toggle="tab"
                                    data-bs-target="#profileTab" role="tab">Leave Type Information</a>
                            </li>
                        </ul>
                    </div>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="profileTab" role="tabpanel">
                            <div class="card-body personal-info">
                                <div class="mb-4 d-flex align-items-center justify-content-between">
                                    <h5 class="fw-bold mb-0 me-4">
                                        <span class="d-block mb-2">Leave Type Information:</span>
                                        <span class="fs-12 fw-normal text-muted text-truncate-1-line">* marked
                                            fields must be filled ! </span>
                                    </h5>
                                    <h5 class="fw-bold mb-0">
                                        <a href="javascript:void(0);" class="btn btn-warning" data-bs-toggle="modal"
                                            data-bs-target="#changeLeaveTypeDetails">Change
                                            Leave Type Details</a>
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
                                                value="{{ ucfirst($leaveType->name) ?? '' }}" class="form-control" readonly
                                                placeholder="Leave Type Name">
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4 align-items-center">
                                    <div class="col-lg-3">
                                        <label for="credit_type" class="fw-semibold">Credit Type: </label>
                                    </div>
                                    <div class="col-lg-9">
                                        <div class="input-group">
                                            <div class="input-group-text"><i class="feather-repeat"></i></div>
                                            <input type="text" id="credit_type"
                                                value="{{ ucfirst($leaveType->credit_type) ?? '' }}" class="form-control"
                                                readonly placeholder="Leave Credit Type">
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4 align-items-center">
                                    <div class="col-lg-3">
                                        <label for="credit_value" class="fw-semibold">Credit Value: </label>
                                    </div>
                                    <div class="col-lg-9">
                                        <div class="input-group">
                                            <div class="input-group-text"><i class="feather-hash"></i></div>
                                            <input type="text" id="credit_value"
                                                value="{{ $leaveType->credit_value ?? '' }}" class="form-control" readonly
                                                placeholder="Leave Credit Value">
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
                                            <textarea id="description" class="form-control" readonly placeholder="Leave Type Description" rows="3">{{ $leaveType->description ?? '' }}</textarea>
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
    <!-- Update Leave Type Modal -->
    <div class="modal fade" id="changeLeaveTypeDetails" tabindex="-1" aria-labelledby="changeLeaveTypeDetailsLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Update Leave Type Details</span>
                        <small class="d-block fs-11 fw-normal text-muted">Update leave type information</small>
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form id="updateLeaveTypeForm"
                                action="{{ route('leave-type.update', ['id' => encrypt($leaveType->id)]) }}"
                                method="POST">
                                @csrf

                                <div id="updateFormError" class="alert alert-danger d-none"></div>
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_name">Leave Type Name *</label>
                                            <input type="text" class="form-control" name="name" id="edit_name"
                                                value="{{ $leaveType->name }}" placeholder="Enter leave type name">
                                            <small class="text-danger error-text name_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_credit_type">Credit Type *</label>
                                            <select class="form-control" name="credit_type" id="edit_credit_type"
                                                required>
                                                <option value="no"
                                                    {{ $leaveType->credit_type == 'no' ? 'selected' : '' }}>
                                                    No Credit
                                                </option>
                                                <option value="weekly"
                                                    {{ $leaveType->credit_type == 'weekly' ? 'selected' : '' }}>
                                                    Weekly
                                                </option>
                                                <option value="monthly"
                                                    {{ $leaveType->credit_type == 'monthly' ? 'selected' : '' }}>
                                                    Monthly
                                                </option>
                                                <option value="yearly"
                                                    {{ $leaveType->credit_type == 'yearly' ? 'selected' : '' }}>
                                                    Yearly
                                                </option>
                                            </select>
                                            <small class="text-danger error-text credit_type_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_credit_value">Credit Value *</label>
                                            <input type="number" step="any"class="form-control" name="credit_value"
                                                required id="edit_credit_value" placeholder="Enter credit value"
                                                value="{{ $leaveType->credit_value }}">
                                            <small class="text-danger error-text credit_value_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_status">Status *</label>
                                            <select class="form-control" name="status" id="edit_status" required>
                                                <option value="1" {{ $leaveType->status == '1' ? 'selected' : '' }}>
                                                    Active
                                                </option>
                                                <option value="0" {{ $leaveType->status == '0' ? 'selected' : '' }}>
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
                                                placeholder="Enter leave type description...">{{ $leaveType->description }}</textarea>
                                            <small class="text-danger error-text description_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                                                Cancel
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                Update Leave Type
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
            $('#updateLeaveTypeForm').on('submit', function(e) {
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
                            alert('Leave Type updated successfully!');

                            // Close modal
                            $('#changeLeaveTypeDetails').modal('hide');

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
            $('#changeLeaveTypeDetails').on('hidden.bs.modal', function() {
                $('.error-text').text('');
                $('#updateFormError').addClass('d-none').text('');
            });
        });
    </script>
@endsection
