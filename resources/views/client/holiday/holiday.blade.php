@extends('client.layout.master')

@section('style')
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
                <div class="hstack">

                </div>
                 @if(in_array($role,['admin','hr']))
                <div class="dropdown d-none d-sm-flex">
                    <a href="#" class="btn btn-light-brand btn-sm rounded-pill" data-bs-toggle="modal"
                        data-bs-target="#addHolidayModal">Add Holiday</a>
                </div>
                @endif
            </div>
        </div>
    </div>
    <div class="content-area-body pb-0 h-100">
        <div class="row note-has-grid" id="note-full-container">
            <!--! BEGIN: [Single Note Item] !-->

            <div class="col-lg-12">
                <div class="card stretch stretch-full">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Holiday</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover" id="customerList">
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
                                    @foreach ($holidays as $holiday)
                                        <tr class="text-center">
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                {{ date('d-M-Y',strtotime($holiday->start_date)) ?? '' }}
                                              
                                            </td>
                                            <td>{{ ucfirst($holiday->name) ?? '' }}</td>
                                            <td>{{ $holiday->description ?? '' }}</td>
                                            @if(in_array($role,['admin','hr']))
                                            <td>
                                                @if ($holiday->status == 1)
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-danger">Inactive</span>
                                                @endif
                                            </td>
                                            @endif
                                             @if(in_array($role,['admin','hr']))
                                            <td>
                                                <div class="dropdown">
                                                    <a href="#" class="avatar-text avatar-md"
                                                        data-bs-toggle="dropdown" data-bs-offset="0,21">
                                                        <i class="feather feather-more-horizontal"></i>
                                                    </a>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item edit-holiday" href="#" 
                                                               data-id="{{ $holiday->id }}"
                                                               data-start-date="{{ $holiday->start_date }}"
                                                               data-end-date="{{ $holiday->end_date }}"
                                                               data-name="{{ $holiday->name }}"
                                                               data-description="{{ $holiday->description }}"
                                                               data-status="{{ $holiday->status }}">
                                                                <i class="feather feather-edit-3 me-3"></i>
                                                                <span>Edit</span>
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                             @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <!--! BEGIN: [Single Note Item] !-->
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- Add Holiday Modal -->
    <div class="modal fade-scale" id="addHolidayModal" tabindex="-1" aria-labelledby="addHolidayModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
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
                                            <label class="fw-semibold" for="end_date">End Date</label>
                                            <input type="date" class="form-control" name="end_date"
                                                id="end_date">
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
                                        <a href="#" class="btn btn-danger text-warning float-end"
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
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
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
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_status">Status *</label>
                                            <select class="form-control" name="status" id="edit_status" required>
                                                <option value="1">Active</option>
                                                <option value="0">Inactive</option>
                                            </select>
                                            <small class="text-danger error-text edit_status_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <button class="btn btn-primary" type="submit">Update</button>
                                    </div>
                                    <div class="col-6">
                                        <a href="#" class="btn btn-danger text-warning float-end"
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
                // Reset errors
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
                            location.reload();
                        }
                    },
                    error: function(xhr) {
                        // ✅ Validation error
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) {
                                $('.' + key + '_error').text(value[0]);
                            });
                        }
                        // ✅ Server error
                        else {
                            $('#addFormError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                        }
                    }
                });
            });

            // Edit Holiday - Open Modal with Data
            $(document).on('click', '.edit-holiday', function(e) {
                e.preventDefault();
                
                // Get data from the clicked row
                const id = $(this).data('id');
                const startDate = $(this).data('start-date');
                const endDate = $(this).data('end-date');
                const name = $(this).data('name');
                const description = $(this).data('description');
                const status = $(this).data('status');

                // Format dates for input fields (YYYY-MM-DD)
                const formatDate = (dateString) => {
                    if (!dateString) return '';
                    const date = new Date(dateString);
                    return date.toISOString().split('T')[0];
                };

                // Populate the edit form
                $('#edit_id').val(id);
                $('#edit_start_date').val(formatDate(startDate));
             
                $('#edit_name').val(name);
                $('#edit_description').val(description);
                $('#edit_status').val(status);

                // Clear previous errors
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                // Show the modal
                $('#editHolidayModal').modal('show');
            });

            // Edit Holiday Form Submission
            $('#editHolidayForm').on('submit', function(e) {
                e.preventDefault();
                
                // Reset errors
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                const id = $('#edit_id').val();
                
                $.ajax({
                    url: "{{ route('holiday.update', '') }}/" + id,
                    type: "POST",
                    data: $(this).serialize(),
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#editHolidayModal').modal('hide');
                            location.reload();
                        }
                    },
                    error: function(xhr) {
                        // ✅ Validation error
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) {
                                $('.edit_' + key + '_error').text(value[0]);
                            });
                        }
                        // ✅ Server error
                        else {
                            $('#editFormError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                        }
                    }
                });
            });

            // Clear form when modal is closed
            $('#addHolidayModal').on('hidden.bs.modal', function() {
                $('#addHolidayForm')[0].reset();
                $('.error-text').text('');
                $('#addFormError').addClass('d-none').text('');
            });

            $('#editHolidayModal').on('hidden.bs.modal', function() {
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');
            });
        });
    </script>
@endsection