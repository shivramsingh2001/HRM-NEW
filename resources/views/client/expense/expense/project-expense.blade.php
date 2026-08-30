@extends('client.layout.master')

@section('style')
@endsection

@section('content-area')
    <div class="content-area-header sticky-top">
        <div class="page-header-left d-flex align-items-center gap-2">
            <div class="page-header-title">
                <h5 class="m-b-10"></h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.5rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Project Expense </li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="hstack gap-2">
                <div class="hstack">

                </div>
                <div class="dropdown d-none d-sm-flex">
                    <a href="#" class="btn btn-light-brand btn-sm rounded-pill" data-bs-toggle="modal"
                        data-bs-target="#addexpenseModal">Add Project Expense </a>
                </div>
            </div>
        </div>
    </div>
    <div class="content-area-body pb-0 h-100">
        <div class="row note-has-grid" id="note-full-container">
            <div class="col-lg-12">
                <div class="card stretch stretch-full">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Expense </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover" id="customerList">
                                <thead>
                                    <tr class="text-center">
                                        <th>S. No.</th>
                                        <th>Project</th>
                                        <th>Expense Type</th>
                                        <th>Amount</th>
                                        <th>Description</th>
                                        <th>File</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($expenses as $expense)
                                        <tr class="text-start">
                                            <td>{{ $loop->iteration }}</td>
                                            
                                            <td>{{ $expense->project_name ?? 'NA' }}</td>
                                            <td>{{ $expense->expenseType->name ?? ucfirst($expense->expense_type) }}</td>
                                            <td>{{ $expense->amount ?? '' }}</td>
                                            <td>{{ $expense->description ?? '' }}</td>
                                            <td>
                                                @if (!empty($expense->file))
                                                    @php
                                                        $filePath = $expense->file;
                                                        $extension = strtolower(
                                                            pathinfo($filePath, PATHINFO_EXTENSION),
                                                        );
                                                        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                                                    @endphp

                                                    @if (in_array($extension, $imageExtensions))
                                                        {{-- Image Preview --}}
                                                        <a href="{{ asset($filePath) }}" target="_blank">
                                                            <img src="{{ asset($filePath) }}" alt="Expense File"
                                                                style="width:30px; height:30px; border-radius:50%;">
                                                        </a>
                                                    @else
                                                        {{-- Download Button --}}
                                                        <a href="{{ asset($filePath) }}" class="btn btn-sm btn-secondary"
                                                            download>
                                                            <i class="fa fa-download"></i>
                                                        </a>
                                                    @endif
                                                @else
                                                    <span class="text-muted">No File</span>
                                                @endif
                                            </td>

                                            <td>
                                                @if ($expense->status == 'complete')
                                                    <span class="badge bg-success">{{ ucfirst($expense->status) }}</span>
                                                @elseif($expense->status == 'cancelled')
                                                    <span class="badge bg-danger">{{ ucfirst($expense->status) }}</span>
                                                @elseif($expense->status == 'approved')
                                                    <span class="badge bg-info">{{ ucfirst($expense->status) }}</span>
                                                @else
                                                    <span class="badge bg-warning">{{ ucfirst($expense->status) }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="dropdown">
                                                    <a href="#" class="avatar-text avatar-md"
                                                        data-bs-toggle="dropdown" data-bs-offset="0,21">
                                                        <i class="feather feather-more-horizontal"></i>
                                                    </a>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item edit-expense" href="#"
                                                                data-id="{{ $expense->id }}"
                                                                data-expense_type="{{ $expense->expense_typeid }}"
                                                             
                                                                data-project_id="{{ $expense->project_id }}"
                                                              
                                                                data-amount="{{ $expense->amount }}"
                                                                data-description="{{ $expense->description }}"
                                                                data-status="{{ $expense->status }}">
                                                                <i class="feather feather-edit-3 me-3"></i>
                                                                <span>Edit</span>
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
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
    <!-- Add Expense  Modal -->
    <div class="modal fade-scale" id="addexpenseModal" tabindex="-1" aria-labelledby="addexpenseModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Add Project Expense </span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form action="{{ route('expense.create') }}" id="addexpenseForm" enctype="multipart/form-data">
                                @csrf
                                <div id="addFormError" class="alert alert-danger d-none"></div>
                                <div class="row">
                                      <div class="col-md-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="project_id">Projects *</label>
                                            <select class="form-control" name="project_id" required id="project_id">
                                                <option value="" disabled selected>-- Select Projects --</option>
                                                @foreach ($projects as $project)
                                                    <option value="{{ $project->id }}">{{ $project->name ?? '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="text-danger error-text project_id_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="expense_type">Expense Type *</label>
                                            <select class="form-control" name="expense_type" required id="expense_type">
                                                <option value="" disabled selected>-- Select Expense Type --</option>
                                                @foreach ($expenseTypes as $expenseType)
                                                    <option value="{{ $expenseType->id }}">{{ $expenseType->name ?? '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="text-danger error-text expense_type_error"></small>
                                        </div>
                                    </div>
                                  
                                    <div class="col-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="amount">Amount *</label>
                                            <input type="number" step="any" class="form-control" name="amount"
                                                id="amount" required placeholder="Enter Amount">
                                            <small class="text-danger error-text amount_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="file">File </label>
                                            <input type="file" class="form-control" name="file" id="file">
                                            <small class="text-danger error-text file_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="description">Description</label>
                                            <textarea class="form-control" name="description" id="description" rows="3"
                                                placeholder="Enter Expense  Description..."></textarea>
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

    <!-- Edit Expense  Modal -->
    <div class="modal fade-scale" id="editexpenseModal" tabindex="-1" aria-labelledby="editexpenseModal"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Edit Project Expense </span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form id="editexpenseForm" enctype="multipart/form-data">
                                @csrf
                                <div id="editFormError" class="alert alert-danger d-none"></div>
                                <input type="hidden" name="id" id="edit_id">
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_expense_type">Expense Type *</label>
                                            <select class="form-control" name="expense_type" required
                                                id="edit_expense_type">
                                                <option value="" disabled selected>-- Select Expense Type --</option>
                                                @foreach ($expenseTypes as $expenseType)
                                                    <option value="{{ $expenseType->id }}">{{ $expenseType->name ?? '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="text-danger error-text edit_expense_type_error"></small>
                                        </div>
                                    </div>
                                   
                                    <div class="col-md-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="project_id">Projects *</label>
                                            <select class="form-control" name="project_id" required id="edit_project_id">
                                                <option value="" disabled selected>-- Select Projects --</option>
                                                @foreach ($projects as $project)
                                                    <option value="{{ $project->id }}">{{ $project->name ?? '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="text-danger error-text edit_project_id_error"></small>
                                        </div>
                                    </div>
                                   
                                    <div class="col-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_amount">Amount *</label>
                                            <input type="number" step="any" class="form-control" name="amount"
                                                id="edit_amount" required placeholder="Enter Amount">
                                            <small class="text-danger error-text edit_amount_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_file">File </label>
                                            <input type="file" class="form-control" name="file" id="edit_file">
                                            <small class="text-danger error-text edit_file_error"></small>
                                            <div id="currentFile" class="mt-2"></div>
                                        </div>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_description">Description</label>
                                            <textarea class="form-control" name="description" id="edit_description" rows="3"
                                                placeholder="Enter Expense Description..."></textarea>
                                            <small class="text-danger error-text edit_description_error"></small>
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
            // Add Expense Form Submission with FormData for file upload
            $('#addexpenseForm').on('submit', function(e) {
                e.preventDefault();
                // Reset errors
                $('.error-text').text('');
                $('#addFormError').addClass('d-none').text('');

                // Create FormData object
                let formData = new FormData(this);

                $.ajax({
                    url: $(this).attr('action'),
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#addexpenseModal').modal('hide');
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

            // Edit Expense - Open Modal with Data
            $(document).on('click', '.edit-expense', function(e) {
                e.preventDefault();

                // Get data from the clicked row
                const id = $(this).data('id');
                const expense_type = $(this).data('expense_type');
              
                const project_id = $(this).data('project_id');
             
                const amount = $(this).data('amount');
                const description = $(this).data('description');
                const status = $(this).data('status');

                // Populate the edit form
                $('#edit_id').val(id);
                $('#edit_expense_type').val(expense_type);
              
                $('#edit_amount').val(amount);
                $('#edit_description').val(description);
                $('#edit_status').val(status);
              
                $('#edit_project_id').val(project_id);
                $('#edit_status').val(status);

                // Clear previous errors
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                // Clear current file display
                $('#currentFile').html('');

                // Show the modal
                $('#editexpenseModal').modal('show');
            });

            // Edit Expense Form Submission with FormData
            $('#editexpenseForm').on('submit', function(e) {
                e.preventDefault();

                // Reset errors
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                const id = $('#edit_id').val();

                // Create FormData object
                let formData = new FormData(this);

                // Append method for PUT request
                formData.append('_method', 'POST');

                $.ajax({
                    url: "{{ route('expense.update', '') }}/" + id,
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#editexpenseModal').modal('hide');
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
            $('#addexpenseModal').on('hidden.bs.modal', function() {
                $('#addexpenseForm')[0].reset();
                $('.error-text').text('');
                $('#addFormError').addClass('d-none').text('');
            });

            $('#editexpenseModal').on('hidden.bs.modal', function() {
                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');
                $('#currentFile').html('');
            });
        });
    </script>
@endsection
