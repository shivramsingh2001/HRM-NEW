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
        .personal-info .input-group-text { background: #EFF6FF; color: #0D6EFD; border-color: #eaeef5; }
        .personal-info .form-control { font-size: 12px; border-color: #eaeef5; }
        .personal-info .fs-12 { font-size: 11px !important; }
        .card-body.personal-info { padding: 16px 18px; }
        .alert-info { background: #EFF6FF; border-color: #dfe5f0; color: #0D6EFD; font-size: 12px; }
        /* .btn-primary now matches the shared theme default exactly
           (theme-custom.css) — no page-local override needed. */
        .btn.btn-lg { padding: 8px 20px; font-size: 13px; }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Apply Leave" :parent="['label' => 'Leave', 'route' => 'leave.view']" />

    <div class="main-content" style="padding: 18px !important;">
        <div class="row">
            <div class="col-lg-12">
                <div class="card border-top-0">
                    <div class="card-header p-0">
                        <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="myTab"
                            role="tablist">
                            <li class="nav-item flex-fill border-top" role="presentation">
                                <a href="javascript:void(0);" class="nav-link active" data-bs-toggle="tab"
                                    data-bs-target="#profileTab" role="tab">Apply Leave</a>
                            </li>
                        </ul>
                    </div>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="profileTab" role="tabpanel">
                            <form action="{{ route('leave.apply-store') }}" method="POST" enctype="multipart/form-data">
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
                                                    name="leave_type" id="leave_type" required>
                                                    <option value="" selected disabled>-- Select Leave Type --
                                                    </option>
                                                    @foreach ($leaveTypes as $leaveType)
                                                        <option value="{{ $leaveType->id }}"
                                                            {{ old('leave_type') == $leaveType->id ? 'selected' : '' }}>
                                                            {{ $leaveType->name }} - {{ number_format($leaveType->available_balance,2) ?? 0.00}}
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
                                                    value="{{ old('start_date', $prefillLeaveDate) }}" placeholder="Enter Start Date" required>
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
                                                    name="start_session" id="start_session" required>
                                                    <option value="" selected disabled>-- Select Session --</option>
                                                    <option value="session1"
                                                        {{ old('start_session') == 'session1' ? 'selected' : '' }}>Session
                                                        1</option>
                                                    <option value="session2"
                                                        {{ old('start_session') == 'session2' ? 'selected' : '' }}>Session
                                                        2</option>
                                                    <option value="fullday"
                                                        {{ old('start_session') == 'fullday' ? 'selected' : '' }}>Full Day
                                                    </option>
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
                                                    value="{{ old('end_date', $prefillLeaveDate) }}" placeholder="Enter End Date" required>
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
                                                <div class="input-group-text">
                                                    <i class="feather-clock"></i>
                                                </div>
                                                <select class="form-control @error('end_session') is-invalid @enderror"
                                                    name="end_session" id="end_session" required>
                                                    <option value="" selected disabled>-- Select Session --</option>
                                                    <option value="session1"
                                                        {{ old('end_session') == 'session1' ? 'selected' : '' }}>Session 1
                                                    </option>
                                                    <option value="session2"
                                                        {{ old('end_session') == 'session2' ? 'selected' : '' }}>Session 2
                                                    </option>
                                                    <option value="fullday"
                                                        {{ old('end_session') == 'fullday' ? 'selected' : '' }}>Full Day
                                                    </option>
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
                                                    placeholder="Enter Reason for Leave" required>{{ old('reason') }}</textarea>
                                            </div>
                                            @error('reason')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <!-- File Attachment -->
                                    <div class="row mb-4 align-items-center">
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
                                                    placeholder="Upload Attachment">
                                            </div>
                                            @error('file')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="text-muted">Maximum file size: 2MB. Allowed file types: pdf, jpg,
                                                png, doc, docx</small>
                                        </div>
                                    </div>

                                    <!-- Calculated Leave Days Display -->
                                    <div class="row mb-4 align-items-center d-none" id="leaveDaysContainer">
                                        <div class="col-lg-4">
                                            <label class="fw-semibold">Total Leave Days: </label>
                                        </div>
                                        <div class="col-lg-8">
                                            <div class="alert alert-info mb-0">
                                                <i class="feather-info me-2"></i>
                                                <span id="totalLeaveDays">0</span> day(s) will be applied
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-0">
                                <div class="card-body pass-info">
                                    <div class="buttons mb-5">
                                        <button class="btn btn-lg btn-primary float-start" type="submit">
                                            <i class="feather-send me-2"></i>
                                            Apply Leave
                                        </button>
                                        <button class="btn btn-lg btn-outline-secondary float-end" type="reset">
                                            <i class="feather-x me-2"></i>
                                            Cancel
                                        </button>
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
            // Auto-fill end date if start date is filled and end date is empty
            $('#start_date').on('change', function() {
                if (!$('#end_date').val()) {
                    $('#end_date').val($(this).val());
                    calculateLeaveDays();
                }
            });

            // Calculate leave days when dates or sessions change
            $('#start_date, #end_date, #start_session, #end_session').on('change', function() {
                calculateLeaveDays();
            });

            function calculateLeaveDays() {
                const startDate = $('#start_date').val();
                const endDate = $('#end_date').val();
                const startSession = $('#start_session').val();
                const endSession = $('#end_session').val();

                if (startDate && endDate && startSession && endSession) {
                    const start = new Date(startDate);
                    const end = new Date(endDate);

                    // Calculate difference in days
                    const diffTime = Math.abs(end - start);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;

                    // Adjust for sessions
                    let totalDays = diffDays;

                    if (diffDays === 1) {
                        // Single day
                        if (startSession === endSession) {
                            totalDays = (startSession === 'fullday') ? 1 : 0.5;
                        }
                    } else {
                        // Multiple days
                        // Adjust first day
                        if (startSession !== 'fullday') {
                            totalDays -= 0.5;
                        }

                        // Adjust last day
                        if (endSession !== 'fullday') {
                            totalDays -= 0.5;
                        }
                    }

                    // Show result
                    $('#totalLeaveDays').text(totalDays);
                    $('#leaveDaysContainer').removeClass('d-none');
                }
            }

            // Keep form data on page refresh
            @if (old('leave_type'))
                $('#leave_type').val('{{ old('leave_type') }}');
            @endif

            @if (old('start_session'))
                $('#start_session').val('{{ old('start_session') }}');
            @endif

            @if (old('end_session'))
                $('#end_session').val('{{ old('end_session') }}');
            @endif

            // Set min date to today — or to the past date passed in ?date= (the dashboard's
            // "Apply Leave" button on an absent day), which the server already accepts.
            const prefillLeaveDate = @json($prefillLeaveDate);
            let today = new Date().toISOString().split('T')[0];
            if (prefillLeaveDate && prefillLeaveDate < today) today = prefillLeaveDate;
            $('#start_date').attr('min', today);
            $('#end_date').attr('min', today);

            // Validate end date is not before start date
            $('#start_date').on('change', function() {
                $('#end_date').attr('min', $(this).val());
            });
        });
    </script>
@endsection
