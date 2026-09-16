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
               <h5 class="m-b-10">Designation Management</h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.5rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Designation</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="hstack gap-2">
                <div class="hstack">

                </div>
                @if(in_array($role,['admin','hr']))
                <div class="dropdown d-none d-sm-flex">
                    <a href="#" class="btn btn-light-brand btn-sm rounded-pill" data-bs-toggle="modal"
                        data-bs-target="#addDesignations">Add Designation</a>
                </div>
                @endif
            </div>
        </div>
    </div>
    <div class="content-area-body pb-0 h-100">
        <div class="row note-has-grid" id="note-full-container">
            <!--! BEGIN: [Single Note Item] !-->
            @foreach ($designations as $designation)
                <div class="col-xxl-4 col-xl-6 col-lg-4 col-sm-6 single-note-item">
                    <div class="card card-body mb-4 stretch stretch-full position-relative shadow-sm border-0">

                        <!-- Top Right: Status + View -->
                        <div class="position-absolute top-0 end-0 d-flex align-items-center gap-2 m-4">

                            <!-- Status Badge -->
                            <span class="badge {{ $designation->status ? 'bg-success' : 'bg-danger' }}">
                                {{ $designation->status ? 'Active' : 'Inactive' }}
                            </span>
                            @if(in_array($role,['admin','hr']))
                            <!-- View Icon -->
                            <a href="{{ route('designation.detail', ['id' => encrypt($designation->id)]) }}"
                                class="text-decoration-none">
                                <i class="bi bi-eye-fill fs-5 text-muted"></i>
                            </a>
                            @endif

                        </div>

                        <span class="side-stick"></span>

                        <!-- Title -->
                        <h5 class="note-title text-truncate w-75 mb-1">
                            {{ $designation->name }}
                            {{-- <i class="point bi bi-circle-fill ms-1 fs-7 text-success"></i> --}}
                        </h5>

                        <p class="fs-11 text-muted mb-2">
                            <i class="bi bi-calendar-event me-1"></i>
                            {{ date('d M Y', strtotime($designation->created_at)) }}
                        </p>

                        <!-- Credit Info Box -->
                        <div class="d-flex align-items-center justify-content-between bg-light rounded-3 px-3 py-1 mb-2">
                            <!-- Credit Type -->
                            <div>
                                <small class="text-muted d-block fs-10">Employee</small>
                               
                                <span class="fw-bold fs-6 text-dark">
                                    <i class="bi bi-person-badge fs-14 me-1 text-primary"></i>
                                    {{ $designation->employees_count ?? 0 }}
                                </span>
                            </div>

                           
                        </div>

                        <!-- Description -->
                        <div class="note-content">
                            <p class="text-muted fs-12 text-truncate-3-line">
                                {{ $designation->description ?? 'No description provided.' }}
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
    <div class="modal fade-scale" id="addDesignations" tabindex="-1" aria-labelledby="addDesignations" aria-hidden="true"
        data-bs-dismiss="ou">
        <div class="modal-dialog modal-dialog-centered modal-sm compact-modal" role="document">
            <div class="modal-content">

                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Add Designation</span>
                        {{-- <small class="d-block fs-11 fw-normal text-muted">Designation must have a head!</small> --}}
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon"
                        data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>

                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form action="#" id="addDesignationForm">
                                <div id="formError" class="alert alert-danger d-none"></div>
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="name">Name *</label>
                                            <input type="text" class="form-control" name="name" required
                                                id="name" placeholder="Enter Designation name">
                                            <small class="text-danger error-text name_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="description">Description
                                            </label>
                                            <textarea class="form-control" name="description" id="description" rows="3"
                                                placeholder="Enter Designation Description..."></textarea>
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
@endsection


@section('script-area')
    <script>
        $(document).ready(function() {

            $('#addDesignationForm').on('submit', function(e) {

                e.preventDefault();
                // Reset errors
                $('.error-text').text('');
                $('#formError').addClass('d-none').text('');

                $.ajax({
                    url: "{{ route('designation.create') }}",
                    type: "POST",
                    data: $(this).serialize(),
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#addDesignations').modal('hide');
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
                            $('#formError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                        }
                    }
                });

            });

        });
    </script>
@endsection
