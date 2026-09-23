@extends('client.layout.master')

@section('style')
<style>
    /* ==================== ALL-BLUE THEME ==================== */
    #holidayList .date-badge {
        font-size: 11px; font-weight: 600; color: #1e3a8a; background: #e3edfe;
        padding: 4px 10px; border-radius: 999px; display: inline-block;
    }
    #holidayList .duration-pill {
        font-size: 9px; color: #6b7385; margin-top: 3px; display: block;
    }

    /* status toggle — sizing only; the checked-state color now comes from
       theme-custom.css's app-wide blue-gradient .form-switch rule. */
    #holidayList .form-check.form-switch .form-check-input { width: 2.2em; height: 1.2em; cursor: pointer; }

    /* single edit icon button */
    .btn-icon-edit {
        width: 30px; height: 30px; padding: 0;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 8px; border: 1px solid #dfe5f0; background: #f4f6fb; color: #475569;
        transition: all .15s;
    }
    .btn-icon-edit:hover { background: #e3edfe; border-color: #1e3a8a; color: #1e3a8a; }
    .btn-icon-edit i { font-size: 14px; }

    .compact-modal .btn-primary { background: #1e3a8a; border-color: #1e3a8a; }
    .compact-modal .btn-primary:hover { background: #16295e; border-color: #16295e; }
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
                <h5 class="m-b-10">Holiday Management</h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.5rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Holiday</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="hstack gap-2">
                 @if(in_array($role,['admin','hr']))
                <div class="dropdown d-none d-sm-flex">
                    <a href="#" class="btn btn-light-brand btn-sm rounded-pill" data-bs-toggle="modal"
                        data-bs-target="#addHolidayModal">
                        <i class="feather-plus me-1"></i>Add Holiday
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>
    <div class="content-area-body pb-0 h-100">
        <div class="row note-has-grid" id="note-full-container">
            <div class="col-lg-12">
                <div class="card stretch stretch-full">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Holiday</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover" id="holidayList">
                                <thead>
                                    <tr class="text-center">
                                        <th>S. No.</th>
                                        <th>Date</th>
                                        <th>Name</th>
                                        <th>Description</th>
                                         @if(in_array($role,['admin','hr']))
                                        <th>Status</th>
                                        <th>Actions</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($holidays as $holiday)
                                        <tr class="text-center">
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                <span class="date-badge">
                                                    {{ $holiday->start_date->format('d M Y') }}
                                                    @if ($holiday->is_multi_day)
                                                        &ndash; {{ $holiday->end_date->format('d M Y') }}
                                                    @endif
                                                </span>
                                                @if ($holiday->is_multi_day)
                                                    <span class="duration-pill">{{ $holiday->duration_days }} days</span>
                                                @endif
                                            </td>
                                            <td>{{ ucfirst($holiday->name) ?? '' }}</td>
                                            <td>{{ $holiday->description ?? '' }}</td>
                                            @if(in_array($role,['admin','hr']))
                                            <td>
                                                <div class="form-check form-switch mb-0 d-flex justify-content-center">
                                                    <input type="checkbox" class="form-check-input toggle-status"
                                                        data-id="{{ $holiday->id }}"
                                                        {{ $holiday->status ? 'checked' : '' }}>
                                                </div>
                                            </td>
                                            @endif
                                             @if(in_array($role,['admin','hr']))
                                            <td>
                                                <a href="#" class="btn-icon-edit edit-holiday"
                                                   data-id="{{ $holiday->id }}"
                                                   data-start-date="{{ $holiday->start_date->format('Y-m-d') }}"
                                                   data-end-date="{{ $holiday->end_date->format('Y-m-d') }}"
                                                   data-name="{{ $holiday->name }}"
                                                   data-description="{{ $holiday->description }}"
                                                   data-status="{{ $holiday->status ? 1 : 0 }}"
                                                   title="Edit">
                                                    <i class="feather feather-edit-3"></i>
                                                </a>
                                            </td>
                                             @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">No holidays configured yet.</td>
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
@endsection

@section('create-modal')
    <!-- Add Holiday Modal -->
    <div class="modal fade-scale" id="addHolidayModal" tabindex="-1" aria-labelledby="addHolidayModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm compact-modal" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Add Holiday</span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form action="{{ route('holiday.create') }}" id="addHolidayForm">
                                <div id="addFormError" class="alert alert-danger d-none"></div>
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="start_date">Start Date *</label>
                                            <input type="date" class="form-control" name="start_date" required
                                                id="start_date">
                                            <small class="text-danger error-text start_date_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="end_date">End Date *</label>
                                            <input type="date" class="form-control" name="end_date" required
                                                id="end_date">
                                            <small class="text-muted d-block" style="font-size:9px;">Same as start date for a single-day holiday.</small>
                                            <small class="text-danger error-text end_date_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="name">Name *</label>
                                            <input type="text" class="form-control" name="name" required
                                                id="name" placeholder="Enter Holiday name">
                                            <small class="text-danger error-text name_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="description">Description</label>
                                            <textarea class="form-control" name="description" id="description" rows="3"
                                                placeholder="Enter Holiday Description..."></textarea>
                                            <small class="text-danger error-text description_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <button class="btn btn-primary" type="submit">Save</button>
                                    </div>
                                    <div class="col-6">
                                        <a href="#" class="btn btn-modal-cancel float-end"
                                            data-bs-dismiss="modal">
                                            Cancel
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

    <!-- Edit Holiday Modal -->
    <div class="modal fade-scale" id="editHolidayModal" tabindex="-1" aria-labelledby="editHolidayModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm compact-modal" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Edit Holiday</span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form id="editHolidayForm">
                                @csrf
                                <div id="editFormError" class="alert alert-danger d-none"></div>
                                <input type="hidden" name="id" id="edit_id">
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_start_date">Start Date *</label>
                                            <input type="date" class="form-control" name="start_date" required
                                                id="edit_start_date">
                                            <small class="text-danger error-text edit_start_date_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_end_date">End Date *</label>
                                            <input type="date" class="form-control" name="end_date" required
                                                id="edit_end_date">
                                            <small class="text-danger error-text edit_end_date_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_name">Name *</label>
                                            <input type="text" class="form-control" name="name" required
                                                id="edit_name" placeholder="Enter Holiday name">
                                            <small class="text-danger error-text edit_name_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_description">Description</label>
                                            <textarea class="form-control" name="description" id="edit_description" rows="3"
                                                placeholder="Enter Holiday Description..."></textarea>
                                            <small class="text-danger error-text edit_description_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="edit_status" name="status" value="1">
                                            <label class="form-check-label fw-semibold" for="edit_status">Active</label>
                                        </div>
                                        <small class="text-danger error-text edit_status_error"></small>
                                    </div>
                                    <div class="col-6">
                                        <button class="btn btn-primary" type="submit">Update</button>
                                    </div>
                                    <div class="col-6">
                                        <a href="#" class="btn btn-modal-cancel float-end"
                                            data-bs-dismiss="modal">
                                            Cancel
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

@section('script-area')
    <script>
        $(document).ready(function() {
            // Add Holiday Form Submission
            $('#addHolidayForm').on('submit', function(e) {
                e.preventDefault();
                $('.error-text').text('');
                $('#addFormError').addClass('d-none').text('');

                $.ajax({
                    url: $(this).attr('action'),
                    type: "POST",
                    data: $(this).serialize(),
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#addHolidayModal').modal('hide');
                            toastr.success(response.message);
                            setTimeout(() => location.reload(), 1000);
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) {
                                $('.' + key + '_error').text(value[0]);
                            });
                            toastr.error('Please fix the validation errors');
                        } else {
                            $('#addFormError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });
            });

            // Auto-fill End Date with Start Date if left blank/earlier
            $('#start_date').on('change', function() {
                if (!$('#end_date').val() || $('#end_date').val() < $(this).val()) {
                    $('#end_date').val($(this).val());
                }
            });

            // Edit Holiday - Open Modal with Data
            $(document).on('click', '.edit-holiday', function(e) {
                e.preventDefault();

                const id = $(this).data('id');
                const startDate = $(this).data('start-date');
                const endDate = $(this).data('end-date');
                const name = $(this).data('name');
                const description = $(this).data('description');
                const status = $(this).data('status');

                $('#edit_id').val(id);
                $('#edit_start_date').val(startDate);
                $('#edit_end_date').val(endDate);
                $('#edit_name').val(name);
                $('#edit_description').val(description);
                $('#edit_status').prop('checked', String(status) === '1');

                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                $('#editHolidayModal').modal('show');
            });

            // Edit Holiday Form Submission
            $('#editHolidayForm').on('submit', function(e) {
                e.preventDefault();
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                const id = $('#edit_id').val();
                const formData = $(this).serializeArray();
                // Checkbox only serializes when checked — make sure 0 is sent when unchecked.
                if (!$('#edit_status').is(':checked')) {
                    formData.push({ name: 'status', value: '0' });
                }

                $.ajax({
                    url: "{{ route('holiday.update', '') }}/" + id,
                    type: "POST",
                    data: $.param(formData),
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#editHolidayModal').modal('hide');
                            toastr.success(response.message);
                            setTimeout(() => location.reload(), 1000);
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) {
                                $('.edit_' + key + '_error').text(value[0]);
                            });
                            toastr.error('Please fix the validation errors');
                        } else {
                            $('#editFormError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });
            });

            // Status toggle switch in the table
            $(document).on('change', '.toggle-status', function() {
                const checkbox = $(this);
                const id = checkbox.data('id');

                $.ajax({
                    url: '{{ url("holidays") }}/' + id + '/status',
                    type: 'PATCH',
                    data: {
                        _token: '{{ csrf_token() }}',
                        status: checkbox.is(':checked') ? 1 : 0
                    },
                    success: function(response) {
                        if (!response.success) {
                            checkbox.prop('checked', !checkbox.is(':checked'));
                        } else {
                            toastr.success(response.message);
                        }
                    },
                    error: function() {
                        checkbox.prop('checked', !checkbox.is(':checked'));
                        toastr.error('Failed to update status.');
                    }
                });
            });

            $('#addHolidayModal').on('hidden.bs.modal', function() {
                $('#addHolidayForm')[0].reset();
                $('.error-text').text('');
                $('#addFormError').addClass('d-none').text('');
            });

            $('#editHolidayModal').on('hidden.bs.modal', function() {
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');
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
