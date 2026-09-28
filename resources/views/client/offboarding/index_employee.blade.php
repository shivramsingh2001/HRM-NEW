@extends('client.layout.master')

@section('style')
    <style>
        /* Same anatomy as the Attendance Regularization / Leave pages —
           single-blue theme, small font, small margin/padding, subtle
           shadow + hover lift on the card. */
        .ob-card {
            background: #fff;
            border: 1px solid #edf2f7;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
            overflow: hidden;
            transition: box-shadow .2s, border-color .2s;
        }
        .ob-card:hover { box-shadow: 0 4px 12px -4px rgba(30, 50, 110, .12); border-color: #dfe5f0; }
        .ob-card-header {
            display: flex; align-items: center; gap: 8px;
            background: #f7f8fb; border-bottom: 1px solid #eaeef5;
            padding: 10px 16px;
        }
        .ob-card-header .ob-icon {
            width: 26px; height: 26px; border-radius: 7px; flex: none;
            background: #e3edfe; color: #1e3a8a;
            display: inline-flex; align-items: center; justify-content: center; font-size: 13px;
        }
        .ob-card-header h6 { font-size: 13px; font-weight: 700; color: #1a2236; margin: 0; }
        .ob-card-body { padding: 16px; }
        .ob-meta { font-size: 11.5px; color: #6b7385; margin-bottom: 4px; }
        .ob-btn {
            display: inline-flex; align-items: center; gap: 6px;
            background: #1e3a8a; color: #fff; border: none;
            font-size: 11.5px; font-weight: 500; padding: 6px 14px;
            border-radius: 8px; text-decoration: none; margin-top: 8px;
            transition: background .2s;
        }
        .ob-btn:hover { background: #16295e; color: #fff; }

        /* ==================== HEADER SUBMIT BUTTON ==================== */
        .ob-header-btn {
            display: inline-flex; align-items: center; gap: 6px;
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            color: #fff; border: none;
            font-size: 12.5px; font-weight: 500; padding: 7px 16px;
            border-radius: 8px; text-decoration: none; cursor: pointer;
        }
        .ob-header-btn:hover { filter: brightness(0.9); color: #fff; }

        /* ==================== DRAWER FORM ==================== */
        .ui-drawer .form-group { margin-bottom: 14px; }
        .ui-drawer label {
            font-size: 11px; font-weight: 600; color: #1a2236;
            margin-bottom: 3px; display: block;
        }
        .ui-drawer .form-control,
        .ui-drawer select.form-control {
            font-size: 12px; padding: 7px 10px; border-radius: 7px; border: 1px solid #dfe5f0;
        }
        .ui-drawer .form-control:focus {
            border-color: #1e3a8a; box-shadow: 0 0 0 .15rem rgba(30, 58, 138, .12);
        }
        .ui-drawer .form-hint { font-size: 10.5px; color: #6b7385; margin-top: 3px; display: block; }
        .ui-drawer .error-text { font-size: 10.5px; color: #dc3545; display: block; margin-top: 2px; }
        #submitResignationDrawer .hint-bar {
            display: flex; align-items: flex-start; gap: 8px;
            padding: 9px 12px; border-radius: 8px; background: #f8fafc;
            border-left: 3px solid var(--primary-mid, #1e3a8a);
            margin-bottom: 14px; font-size: 11px; color: #475569;
        }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="My Offboarding">
        @unless ($activeRequest)
            <x-slot:actions>
                <button type="button" class="ob-header-btn" data-bs-toggle="offcanvas" data-bs-target="#submitResignationDrawer">
                    <i class="feather-user-minus"></i> Submit Resignation
                </button>
            </x-slot:actions>
        @endunless
    </x-ui.page-header>

    <div class="main-content" style="padding: 20px !important;">

        @if (session('success'))
            <div class="alert alert-success py-2" style="font-size:12px;">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger py-2" style="font-size:12px;">{{ session('error') }}</div>
        @endif

        @if ($activeRequest)
            <div class="ob-card">
                <div class="ob-card-header">
                    <span class="ob-icon"><i class="feather-user-minus"></i></span>
                    <h6>{{ $activeRequest->reason_label }}</h6>
                </div>
                <div class="ob-card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="ob-meta">Submitted {{ $activeRequest->created_at->format('d M Y') }}</div>
                            <div class="ob-meta">Last working date: {{ optional($activeRequest->last_working_date)->format('d M Y') }}</div>
                            @if ($activeRequest->status === 'approved')
                                <div class="ob-meta">Current stage: <strong>{{ $activeRequest->stage_label }}</strong></div>
                            @endif
                        </div>
                        <x-ui.status-badge :status="$activeRequest->badge_status" :label="$activeRequest->status_label" />
                    </div>
                    <a href="{{ route('offboarding.show', $activeRequest->id) }}" class="ob-btn"><i class="feather-eye"></i> View Details</a>
                </div>
            </div>
        @else
            <div class="ob-card">
                <div class="ob-card-body text-center">
                    <i class="feather-user-minus" style="font-size:32px;color:#cbd5e1;"></i>
                    <p class="mt-2 mb-0" style="font-size:11.5px;color:#6b7385;">You don't have an active offboarding request.</p>
                </div>
            </div>
        @endif

    </div>
@endsection

@section('create-modal')
    <!-- Submit Resignation Drawer -->
    <x-ui.drawer id="submitResignationDrawer" title="Submit Resignation" width="480px">
        <form id="submitResignationForm">
            @csrf
            <div id="submitResignationError" class="alert alert-danger d-none"></div>

            <input type="hidden" name="employee_id" value="{{ $selectedEmployee?->id }}">
            <div class="form-group">
                <label>Employee</label>
                <input type="text" class="form-control" value="{{ $selectedEmployee?->name }}" disabled>
            </div>

            <div class="form-group">
                <label for="er_reason">Reason *</label>
                <select class="form-control" name="reason" id="er_reason" required>
                    <option value="" disabled selected>Select reason</option>
                    @foreach ($reasons as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <small class="error-text reason_error"></small>
            </div>

            <div class="hint-bar">
                <i class="feather-info"></i>
                <span id="erNoticeHint">Minimum notice period: {{ $noticeDays }} day(s). Last working date must be on or
                    after that.</span>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="er_resignation_date">Request Date</label>
                        <input type="date" class="form-control" id="er_resignation_date" name="resignation_date"
                            value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="er_last_working_date">Last Working Date *</label>
                        <input type="date" class="form-control" id="er_last_working_date" name="last_working_date"
                            required>
                        <small class="form-hint" id="erMinDateHint"></small>
                        <small class="error-text last_working_date_error"></small>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="er_reason_detail">Details</label>
                <textarea class="form-control" id="er_reason_detail" name="reason_detail" rows="3" maxlength="2000"></textarea>
                <small class="error-text reason_detail_error"></small>
            </div>

            <div class="form-group">
                <label for="er_feedback">Additional Feedback (optional)</label>
                <textarea class="form-control" id="er_feedback" name="feedback" rows="2" maxlength="2000"></textarea>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-primary" type="submit" id="submitResignationBtn">Submit Request</button>
                <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="offcanvas">Cancel</button>
            </div>
        </form>
    </x-ui.drawer>
@endsection

@section('script-area')
    <script>
        $(document).ready(function() {
            // Reset drawer on open
            $('#submitResignationDrawer').on('show.bs.offcanvas', function() {
                $('#submitResignationForm')[0].reset();
                $('#submitResignationForm .error-text').text('');
                $('#submitResignationError').addClass('d-none').text('');
                syncNoticeHint();
            });

            const reasonRules = @json($reasonRules);
            const noticeDays = {{ (int) $noticeDays }};

            function todayPlus(days) {
                var d = new Date();
                d.setDate(d.getDate() + days);
                return d.toISOString().split('T')[0];
            }

            function syncNoticeHint() {
                const reason = $('#er_reason').val();
                const rules = reasonRules[reason];
                const requiresNotice = !rules || rules.requires_notice;
                const lastWorkingDate = $('#er_last_working_date');

                if (requiresNotice) {
                    const minDate = todayPlus(noticeDays);
                    lastWorkingDate.attr('min', minDate);
                    $('#erMinDateHint').text('Earliest allowed: ' + minDate);
                    $('#erNoticeHint').text('Minimum notice period: ' + noticeDays +
                        ' day(s). Last working date must be on or after that.');
                } else {
                    lastWorkingDate.removeAttr('min');
                    $('#erMinDateHint').text('');
                    $('#erNoticeHint').text('This reason does not require a minimum notice period.');
                }
            }

            $('#er_reason').on('change', syncNoticeHint);

            $('#submitResignationForm').on('submit', function(e) {
                e.preventDefault();

                const submitBtn = $('#submitResignationBtn');
                const originalText = submitBtn.html();
                submitBtn.prop('disabled', true).html(
                    '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...');
                $('#submitResignationForm .error-text').text('');
                $('#submitResignationError').addClass('d-none').text('');

                $.ajax({
                    url: '{{ route('offboarding.store') }}',
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        submitBtn.prop('disabled', false).html(originalText);
                        if (response.success) {
                            bootstrap.Offcanvas.getInstance(document.getElementById(
                                'submitResignationDrawer'))?.hide();
                            if (response.redirect) {
                                window.location.href = response.redirect;
                            } else {
                                window.location.reload();
                            }
                        } else {
                            $('#submitResignationError').removeClass('d-none').text(response
                                .message || 'Failed to submit request');
                        }
                    },
                    error: function(xhr) {
                        submitBtn.prop('disabled', false).html(originalText);
                        if (xhr.status === 422 && xhr.responseJSON?.errors) {
                            const errors = xhr.responseJSON.errors;
                            Object.keys(errors).forEach(function(field) {
                                $('.' + field + '_error').text(errors[field][0]);
                            });
                        } else {
                            $('#submitResignationError').removeClass('d-none').text(xhr
                                .responseJSON?.message || 'Failed to submit request');
                        }
                    }
                });
            });
        });
    </script>
@endsection
