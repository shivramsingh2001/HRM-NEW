@extends('client.layout.master')

@section('style')
    <style>
        .error-message {
            color: #dc3545;
            font-size: 0.875em;
            margin-top: 0.25rem;
        }

        .is-invalid {
            border-color: #dc3545 !important;
        }

        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #dc3545;
        }

        /* ==================== BLUE THEME, COMPACT SPACING ==================== */
        .personal-info .row.mb-4 { margin-bottom: 14px !important; }
        .personal-info label { font-size: 12px; }
        .personal-info .input-group-text { background: #e3edfe; color: #1e3a8a; border-color: #eaeef5; }
        .personal-info .form-control { font-size: 12px; border-color: #eaeef5; }
        .personal-info .fs-12 { font-size: 11px !important; }
        .card-body.personal-info { padding: 16px 18px; }
        /* Status now rendered via the ui.status-badge Blade component
           (shared status-color mapping) and .btn-primary matches the
           shared theme default exactly — the old per-page overrides for
           both are no longer needed. */
        .btn.btn-lg { padding: 8px 20px; font-size: 13px; }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Update Leave" :parent="['label' => 'Leave', 'route' => 'leave.view']" />
    <div class="main-content" style="padding: 18px !important;">
        <div class="row">
            <div class="col-lg-12">
                <div class="card border-top-0">
                    <div class="card-header p-0">
                        <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="myTab"
                            role="tablist">
                            <li class="nav-item flex-fill border-top" role="presentation">
                                <a href="javascript:void(0);" class="nav-link active" data-bs-toggle="tab"
                                    data-bs-target="#profileTab" role="tab">Update Leave</a>
                            </li>
                        </ul>
                    </div>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="profileTab" role="tabpanel">
                            <form action="{{ route('leave.update-store', ['id' => encrypt($leave->id)]) }}" method="POST"
                                enctype="multipart/form-data">
                                @csrf

                                <!-- Display general error messages -->
                                @if (session('error'))
                                    <div class="alert alert-danger alert-dismissible fade show m-4" role="alert">
                                        <i class="feather-alert-circle me-2"></i>
                                        {{ session('error') }}
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"
                                            aria-label="Close"></button>
                                    </div>
                                @endif

                                @if (session('success'))
                                    <div class="alert alert-success alert-dismissible fade show m-4" role="alert">
                                        <i class="feather-check-circle me-2"></i>
                                        {{ session('success') }}
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"
                                            aria-label="Close"></button>
                                    </div>
                                @endif

                                <!-- Show status warning if leave is already approved/rejected -->
                                @if ($leave->status != 'pending')
                                    <div class="alert alert-warning alert-dismissible fade show m-4" role="alert">
                                        <i class="feather-alert-triangle me-2"></i>
                                        This leave application is already <strong>{{ ucfirst($leave->status) }}</strong>.
                                        Only pending leaves can be modified.
                                    </div>
                                @endif

                                <div class="card-body personal-info">
                                    <div class="mb-4 d-flex align-items-center justify-content-between">
                                        <h5 class="fw-bold mb-0 me-4">
                                            <span class="d-block mb-2">Leave Details:</span>
                                            <span class="fs-12 fw-normal text-muted text-truncate-1-line">* marked
                                                fields must be filled !</span>
                                        </h5>
                                    </div>

                                    <!-- Leave Type -->
                                    <div class="row mb-4 align-items-center">
                                        <div class="col-lg-4">
                                            <label for="leave_type" class="fw-semibold">Type of Leave *: </label>
                                        </div>
                                        <div class="col-lg-8">
                                            <div class="input-group">
                                                <div class="input-group-text"><i class="feather-send"></i></div>
                                                <select class="form-control @error('leave_type') is-invalid @enderror"
                                                    name="leave_type" id="leave_type" required
                                                    {{ $leave->status != 'pending' ? 'disabled' : '' }}>
                                                    <option value="" disabled>-- Select Leave Type --</option>
                                                    @foreach ($leaveTypes as $leaveType)
                                                        <option value="{{ $leaveType->id }}"
                                                            {{ old('leave_type', $leave->leave_type) == $leaveType->id ? 'selected' : '' }}>
                                                            {{ $leaveType->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            @error('leave_type')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <!-- Start Date -->
                                    <div class="row mb-4 align-items-center">
                                        <div class="col-lg-4">
                                            <label for="start_date" class="fw-semibold">Start Date *: </label>
                                        </div>
                                        <div class="col-lg-8">
                                            <div class="input-group">
                                                <div class="input-group-text">
                                                    <i class="fa-regular fa-calendar-plus"></i>
                                                </div>
                                                <input type="date" name="start_date" id="start_date"
                                                    class="form-control @error('start_date') is-invalid @enderror"
                                                    value="{{ old('start_date', $leave->start_date) }}"
                                                    placeholder="Enter Start Date" required
                                                    {{ $leave->status != 'pending' ? 'disabled' : '' }}>
                                            </div>
                                            @error('start_date')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <!-- Start Session -->
                                    <div class="row mb-4 align-items-center">
                                        <div class="col-lg-4">
                                            <label for="start_session" class="fw-semibold">Start Session *: </label>
                                        </div>
                                        <div class="col-lg-8">
                                            <div class="input-group">
                                                <div class="input-group-text"><i class="feather-clock"></i></div>
                                                <select class="form-control @error('start_session') is-invalid @enderror"
                                                    name="start_session" id="start_session" required
                                                    {{ $leave->status != 'pending' ? 'disabled' : '' }}>
                                                    <option value="" disabled>-- Select Session --</option>
                                                    <option value="session1"
                                                        {{ old('start_session', $leave->start_session) == 'session1' ? 'selected' : '' }}>
                                                        Session 1</option>
                                                    <option value="session2"
                                                        {{ old('start_session', $leave->start_session) == 'session2' ? 'selected' : '' }}>
                                                        Session 2</option>
                                                    <option value="fullday"
                                                        {{ old('start_session', $leave->start_session) == 'fullday' ? 'selected' : '' }}>
                                                        Full Day</option>
                                                </select>
                                            </div>
                                            @error('start_session')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <!-- End Date -->
                                    <div class="row mb-4 align-items-center">
                                        <div class="col-lg-4">
                                            <label for="end_date" class="fw-semibold">End Date *: </label>
                                        </div>
                                        <div class="col-lg-8">
                                            <div class="input-group">
                                                <div class="input-group-text">
                                                    <i class="fa-regular fa-calendar-xmark"></i>
                                                </div>
                                                <input type="date" name="end_date" id="end_date"
                                                    class="form-control @error('end_date') is-invalid @enderror"
                                                    value="{{ old('end_date', $leave->end_date) }}"
                                                    placeholder="Enter End Date" required
                                                    {{ $leave->status != 'pending' ? 'disabled' : '' }}>
                                            </div>
                                            @error('end_date')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <!-- End Session -->
                                    <div class="row mb-4 align-items-center">
                                        <div class="col-lg-4">
                                            <label for="end_session" class="fw-semibold">End Session *: </label>
                                        </div>
                                        <div class="col-lg-8">
                                            <div class="input-group">
                                                <div class="input-group-text"><i class="feather-clock"></i></div>
                                                <select class="form-control @error('end_session') is-invalid @enderror"
                                                    name="end_session" id="end_session" required
                                                    {{ $leave->status != 'pending' ? 'disabled' : '' }}>
                                                    <option value="" disabled>-- Select Session --</option>
                                                    <option value="session1"
                                                        {{ old('end_session', $leave->end_session) == 'session1' ? 'selected' : '' }}>
                                                        Session 1</option>
                                                    <option value="session2"
                                                        {{ old('end_session', $leave->end_session) == 'session2' ? 'selected' : '' }}>
                                                        Session 2</option>
                                                    <option value="fullday"
                                                        {{ old('end_session', $leave->end_session) == 'fullday' ? 'selected' : '' }}>
                                                        Full Day</option>
                                                </select>
                                            </div>
                                            @error('end_session')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <!-- Reason -->
                                    <div class="row mb-4 align-items-center">
                                        <div class="col-lg-4">
                                            <label for="reason" class="fw-semibold">Reason *: </label>
                                        </div>
                                        <div class="col-lg-8">
                                            <div class="input-group">
                                                <div class="input-group-text">
                                                    <i class="fa-solid fa-clipboard-question"></i>
                                                </div>
                                                <textarea name="reason" id="reason" class="form-control @error('reason') is-invalid @enderror"
                                                    placeholder="Enter Reason for Leave" required {{ $leave->status != 'pending' ? 'disabled' : '' }}>{{ old('reason', $leave->reason) }}</textarea>
                                            </div>
                                            @error('reason')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <!-- Current File Attachment -->
                                    @if ($leave->file)
                                        <div class="row mb-4 align-items-center">
                                            <div class="col-lg-4">
                                                <label class="fw-semibold">Current Attachment: </label>
                                            </div>
                                            <div class="col-lg-8">
                                                <div
                                                    class="alert alert-light d-flex align-items-center justify-content-between">
                                                    <div>
                                                        <i class="feather-paperclip me-2"></i>
                                                        <a href="{{ file_url($leave->file, 'leave') }}" target="_blank"
                                                            class="text-primary">
                                                            View Current File
                                                        </a>
                                                    </div>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" id="replaceFile"
                                                            name="replace_file" value="1">
                                                        <label class="form-check-label" for="replaceFile">
                                                            Replace File
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    <!-- New File Attachment -->
                                    <div class="row mb-4 align-items-center" id="newFileContainer"
                                        style="{{ $leave->file ? 'display:none;' : '' }}">
                                        <div class="col-lg-4">
                                            <label for="file" class="fw-semibold">Attachment : </label>
                                        </div>
                                        <div class="col-lg-8">
                                            <div class="input-group">
                                                <div class="input-group-text">
                                                    <i class="feather-file-plus"></i>
                                                </div>
                                                <input type="file" name="file" id="file"
                                                    class="form-control @error('file') is-invalid @enderror"
                                                    placeholder="Upload Attachment"
                                                    {{ $leave->status != 'pending' ? 'disabled' : '' }}>
                                            </div>
                                            @error('file')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="text-muted">Maximum file size: 2MB. Allowed file types: pdf, jpg,
                                                png, doc, docx</small>
                                        </div>
                                    </div>

                                    <!-- Current Status Display -->
                                    <div class="row mb-4 align-items-center">
                                        <div class="col-lg-4">
                                            <label class="fw-semibold">Current Status: </label>
                                        </div>
                                        <div class="col-lg-8">
                                            <x-ui.status-badge :status="$leave->status" />
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-0">
                                <div class="card-body pass-info">
                                    <div class="buttons mb-5">
                                        @if ($leave->status == 'pending')
                                            <button class="btn btn-lg btn-primary float-start" type="submit"
                                                id="submitBtn">
                                                <i class="feather-save me-2"></i>
                                                Update Leave
                                            </button>
                                        @endif
                                        <a href="{{ route('leave.view') }}"
                                            class="btn btn-lg btn-outline-secondary float-end">
                                            <i class="feather-arrow-left me-2"></i>
                                            Back to List
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
@endsection

@section('create-modal')
@endsection

@section('script-area')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Show/hide new file input based on replace file checkbox
            $('#replaceFile').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#newFileContainer').show();
                    $('#newFileContainer input').prop('required', true);
                } else {
                    $('#newFileContainer').hide();
                    $('#newFileContainer input').prop('required', false);
                }
            });

            // Set min date to today
            const today = new Date().toISOString().split('T')[0];
            $('#start_date').attr('min', today);
            $('#end_date').attr('min', $('#start_date').val() || today);
            $('#start_date').on('change', function() {
                $('#end_date').attr('min', $(this).val());
            });

            // Disable form if leave is not pending
            @if ($leave->status != 'pending')
                $('#submitBtn').prop('disabled', true);
            @endif
        });
    </script>
@endsection
