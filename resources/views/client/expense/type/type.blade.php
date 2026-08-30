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
                <h5 class="m-b-10">Expense Type Management</h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.57rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                
                <li class="breadcrumb-item">Expense Type</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="hstack gap-2">
                <div class="hstack">

                </div>
                 @if(in_array($role,['admin','hr']))
                <div class="dropdown d-none d-sm-flex">
                    <a href="#" class="btn btn-light-brand btn-sm rounded-pill" data-bs-toggle="modal"
                        data-bs-target="#addexpensetypeModal">Add Expense Type</a>
                </div>
                @endif
            </div>
        </div>
    </div>
    <div class="content-area-body pb-0 h-100">
        <div class="row note-has-grid" id="note-full-container">
            <!--! BEGIN: [Single Note Item] !-->
            @foreach ($expenseTypes as $expenseType)
                <div class="col-xxl-4 col-xl-6 col-lg-4 col-sm-6 single-note-item">
                    <div class="card card-body mb-4 stretch stretch-full position-relative shadow-sm border-0">

                        <!-- Top Right: Status + View -->
                        <div class="position-absolute top-0 end-0 d-flex align-items-center gap-2 m-4">

                            <!-- Status Badge -->
                            <span class="badge {{ $expenseType->status ? 'bg-success' : 'bg-danger' }}">
                                {{ $expenseType->status == 1 ? 'Active' : 'Inactive' }}
                            </span>
                            
                             <a class="edit-expense-type text-decoration-none" href="#" 
                               data-id="{{ $expenseType->id }}"
                               data-name="{{ $expenseType->name }}"
                               data-description="{{ $expenseType->description }}"
                               data-status="{{ $expenseType->status }}">
                                <i class="fa fa-pencil text-secondary"></i>
                            </a>
                        
                        </div>

                        <span class="side-stick"></span>

                        <!-- Title -->
                        <h5 class="note-title text-truncate w-75 mb-1">
                            {{ $expenseType->name }}
                            {{-- <i class="point bi bi-circle-fill ms-1 fs-7 text-success"></i> --}}
                        </h5>

                        <p class="fs-11 text-muted mb-2">
                            <i class="bi bi-calendar-event me-1"></i>
                            {{ date('d M Y', strtotime($expenseType->created_at)) }}
                        </p>

                        <!-- Description -->
                        <div class="note-content">
                            <p class="text-muted fs-12 text-truncate-3-line">
                                {{ $expenseType->description ?? 'No description provided.' }}
                            </p>
                        </div>

                    </div>
                </div>
            @endforeach
            <!--! BEGIN: [Single Note Item] !-->
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- Add Expense Type Modal -->
    <div class="modal fade-scale" id="addexpensetypeModal" tabindex="-1" aria-labelledby="addexpensetypeModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Add Expense Type</span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form action="{{ route('expense-type.create') }}" id="addexpensetypeForm">
                                <div id="addFormError" class="alert alert-danger d-none"></div>
                                <div class="row">
                                    
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="name">Name *</label>
                                            <input type="text" class="form-control" name="name" required
                                                id="name" placeholder="Enter Expense Type name">
                                            <small class="text-danger error-text name_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="description">Description</label>
                                            <textarea class="form-control" name="description" id="description" rows="3"
                                                placeholder="Enter Expense Type Description..."></textarea>
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

    <!-- Edit Expense Type Modal -->
    <div class="modal fade-scale" id="editexpensetypeModal" tabindex="-1" aria-labelledby="editexpensetypeModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Edit Expense Type</span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form id="editexpensetypeForm">
                                @csrf
                                <div id="editFormError" class="alert alert-danger d-none"></div>
                                <input type="hidden" name="id" id="edit_id">
                                <div class="row">
                                    
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_name">Name *</label>
                                            <input type="text" class="form-control" name="name" required
                                                id="edit_name" placeholder="Enter Expense Type name">
                                            <small class="text-danger error-text edit_name_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_description">Description</label>
                                            <textarea class="form-control" name="description" id="edit_description" rows="3"
                                                placeholder="Enter Expense Type Description..."></textarea>
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
            // Add Expense Type Form Submission
            $('#addexpensetypeForm').on('submit', function(e) {
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
                            $('#addexpensetypeModal').modal('hide');
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

            // Edit Expense Type - Open Modal with Data
            $(document).on('click', '.edit-expense-type', function(e) {
                e.preventDefault();
                
                // Get data from the clicked row
                const id = $(this).data('id');
                const name = $(this).data('name');
                const description = $(this).data('description');
                const status = $(this).data('status');

                // Populate the edit form
                $('#edit_id').val(id);
                $('#edit_name').val(name);
                $('#edit_description').val(description);
                $('#edit_status').val(status);

                // Clear previous errors
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                // Show the modal
                $('#editexpensetypeModal').modal('show');
            });

            // Edit Expense Type Form Submission
            $('#editexpensetypeForm').on('submit', function(e) {
                e.preventDefault();
                
                // Reset errors
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                const id = $('#edit_id').val();
                
                $.ajax({
                    url: "{{ route('expense-type.update', '') }}/" + id,
                    type: "POST",
                    data: $(this).serialize(),
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#editexpensetypeModal').modal('hide');
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
            $('#addexpensetypeModal').on('hidden.bs.modal', function() {
                $('#addexpensetypeForm')[0].reset();
                $('.error-text').text('');
                $('#addFormError').addClass('d-none').text('');
            });

            $('#editexpensetypeModal').on('hidden.bs.modal', function() {
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');
            });
        });
    </script>
@endsection