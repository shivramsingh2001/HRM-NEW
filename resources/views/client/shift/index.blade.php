@extends('client.layout.master')

@section('style')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        .shift-manage .content-area-body { padding: 10px 14px !important; }
        .shift-manage .card { margin-bottom: 0; }
        .shift-manage .card-header { padding: 8px 12px; }
        .shift-manage .card-header .card-title { font-size: 13px; }
        .shift-manage .card-body { padding: 0; }
        .shift-manage .card-footer { padding: 6px 12px; }

        .shift-stat { border: 1px solid #edf2f7; border-radius: 10px; background: #fff; padding: 9px 12px; }
        .shift-stat .label { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: .03em; }
        .shift-stat .value { font-size: 18px; font-weight: 700; color: #1e293b; line-height: 1.2; }

        .shift-manage table#shiftList { font-size: 12px; margin: 0; }
        .shift-manage table#shiftList td, .shift-manage table#shiftList th { vertical-align: middle; padding: 5px 10px; }
        .shift-manage table#shiftList thead th { font-size: 10px; text-transform: uppercase; letter-spacing: .02em; color: #64748b; background: #f8fafc; }
        .shift-dot { display: inline-block; width: 9px; height: 9px; border-radius: 3px; margin-right: 6px; vertical-align: middle; }
        .shift-name { font-weight: 600; color: #1e293b; }
        .muted-cell { color: #94a3b8; }
        .shift-manage .badge { font-size: 10px; font-weight: 600; }
        .shift-manage label.form-label { font-size: 11px; margin-bottom: 2px; color: #64748b; }
        .shift-manage .form-control-sm { font-size: 12px; padding: 3px 8px; height: auto; }
        .shift-manage .btn-sm { font-size: 12px; padding: 3px 9px; }

        .modal-custom .modal-content { border: none; border-radius: 14px; }
        .modal-custom .modal-title { font-size: 15px; }
        .modal-custom .form-label { font-size: 11px; margin-bottom: 2px; color: #64748b; }
        .modal-custom .modal-header, .modal-custom .modal-footer { padding: 10px 16px; }
        .error-text { display: block; font-size: 11px; }
    </style>
@endsection

@php
    $role = Auth::user()->role;
@endphp

@section('content-area')
    <div class="shift-manage">
    <div class="content-area-header sticky-top">
        <div class="page-header-left d-flex align-items-center gap-2">
            <div class="page-header-title">
                <h5 class="m-b-10">Manage Shifts</h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.5rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('shift.roster') }}">Shift</a></li>
                <li class="breadcrumb-item">Manage Shifts</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="hstack gap-2">
                <a href="{{ route('shift.roster') }}" class="btn btn-light-brand btn-sm rounded-pill">
                    <i class="feather-grid me-1"></i>Roster &amp; Assign
                </a>
                <a href="#" class="btn btn-primary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#addShiftModal">
                    <i class="feather-plus me-1"></i>Add Shift
                </a>
            </div>
        </div>
    </div>

    <div class="content-area-body">
        @if (session('error'))
            <div class="alert alert-danger py-2">{{ session('error') }}</div>
        @endif

        <div class="row g-2 mb-2">
            <div class="col-3"><div class="shift-stat"><div class="label">Total</div><div class="value">{{ $totalShifts }}</div></div></div>
            <div class="col-3"><div class="shift-stat"><div class="label">Active</div><div class="value text-success">{{ $activeShifts }}</div></div></div>
            <div class="col-3"><div class="shift-stat"><div class="label">Inactive</div><div class="value text-danger">{{ $inactiveShifts }}</div></div></div>
            <div class="col-3"><div class="shift-stat"><div class="label">Assigned this month</div><div class="value">{{ $assignedShiftsCount }}</div></div></div>
        </div>

        <div class="card stretch stretch-full">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Shift Definitions</h5>
                <form method="GET" action="{{ route('shift.index') }}" class="d-flex gap-2" id="shiftFilterForm">
                    <input type="text" name="search" class="form-control form-control-sm" style="width: 180px"
                           placeholder="Search name" value="{{ request('search') }}">
                    <select name="status" class="form-control form-control-sm" style="width: 130px" onchange="this.form.submit()">
                        <option value="">All statuses</option>
                        <option value="1" @selected(request('status') === '1')>Active</option>
                        <option value="0" @selected(request('status') === '0')>Inactive</option>
                    </select>
                    <button class="btn btn-sm btn-primary" type="submit"><i class="feather-search"></i></button>
                    @if (request('search') || request('status') !== null)
                        <a href="{{ route('shift.index') }}" class="btn btn-sm btn-light">Reset</a>
                    @endif
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover" id="shiftList">
                        <thead>
                            <tr>
                                <th>Shift</th>
                                <th>Timing</th>
                                <th>Duration</th>
                                <th>Grace</th>
                                <th>Break</th>
                                <th>Assigned (this month)</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($shifts as $shift)
                                @php
                                    $start = \Carbon\Carbon::parse($shift->start_time);
                                    $end = \Carbon\Carbon::parse($shift->end_time);
                                    $overnight = $end->lessThanOrEqualTo($start);
                                @endphp
                                <tr>
                                    <td>
                                        <span class="shift-dot" style="background: {{ $shift->color_code ?: '#4f46e5' }}"></span>
                                        <span class="shift-name">{{ $shift->name }}</span>
                                        @if ($shift->description)
                                            <div class="muted-cell small">{{ \Illuminate\Support\Str::limit($shift->description, 60) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $start->format('h:i A') }} &ndash; {{ $end->format('h:i A') }}
                                        @if ($overnight)<span class="badge bg-light text-dark ms-1">+1 day</span>@endif
                                    </td>
                                    <td>{{ $shift->total_hours }} h</td>
                                    <td>{{ (int) $shift->grace_minutes }} min</td>
                                    <td>{{ (int) $shift->break_time }} min</td>
                                    <td>
                                        @if (($shift->assigned_this_month ?? 0) > 0)
                                            <span class="badge bg-info">{{ $shift->assigned_this_month }}</span>
                                        @else
                                            <span class="muted-cell">0</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($shift->status)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-danger">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <a href="#" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21">
                                                <i class="feather feather-more-horizontal"></i>
                                            </a>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item edit-shift" href="#"
                                                       data-id="{{ $shift->id }}"
                                                       data-name="{{ $shift->name }}"
                                                       data-color_code="{{ $shift->color_code }}"
                                                       data-start_time="{{ $start->format('H:i') }}"
                                                       data-end_time="{{ $end->format('H:i') }}"
                                                       data-grace_minutes="{{ (int) $shift->grace_minutes }}"
                                                       data-break_time="{{ (int) $shift->break_time }}"
                                                       data-status="{{ (int) $shift->status }}"
                                                       data-description="{{ $shift->description }}">
                                                        <i class="feather-edit-3 me-2"></i>Edit
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item change-status" href="#" data-id="{{ $shift->id }}">
                                                        <i class="feather-power me-2"></i>{{ $shift->status ? 'Deactivate' : 'Activate' }}
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger delete-shift" href="#"
                                                       data-id="{{ $shift->id }}" data-name="{{ $shift->name }}">
                                                        <i class="feather-trash-2 me-2"></i>Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <span class="text-muted">No shifts yet. Click "Add Shift" to create one.</span>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($shifts->hasPages())
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted" style="font-size:11px">
                            Showing {{ $shifts->firstItem() }}&ndash;{{ $shifts->lastItem() }} of {{ $shifts->total() }}
                        </span>
                        <div>{{ $shifts->appends(request()->query())->links() }}</div>
                    </div>
                </div>
            @endif
        </div>
    </div>
    </div>
@endsection

@section('create-modal')
    {{-- Add Shift --}}
    <div class="modal fade modal-custom" id="addShiftModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Shift</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="addFormError"></div>
                    <form id="addShiftForm">
                        @csrf
                        <div class="row g-3">
                            <div class="col-8">
                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required>
                                <small class="text-danger error-text name_error"></small>
                            </div>
                            <div class="col-4">
                                <label class="form-label">Colour</label>
                                <input type="color" name="color_code" class="form-control form-control-color" value="#4f46e5">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Start time <span class="text-danger">*</span></label>
                                <input type="time" name="start_time" class="form-control" required>
                                <small class="text-danger error-text start_time_error"></small>
                            </div>
                            <div class="col-6">
                                <label class="form-label">End time <span class="text-danger">*</span></label>
                                <input type="time" name="end_time" class="form-control" required>
                                <small class="text-danger error-text end_time_error"></small>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Grace (min)</label>
                                <input type="number" min="0" max="120" name="grace_minutes" class="form-control" value="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Break (min)</label>
                                <input type="number" min="0" max="180" name="break_time" class="form-control" value="0">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="col-12"><small class="text-muted">Working hours are calculated from start/end minus break. End on or before start = overnight shift.</small></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="addShiftForm" class="btn btn-primary">Save Shift</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit Shift --}}
    <div class="modal fade modal-custom" id="editShiftModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Shift</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="editFormError"></div>
                    <form id="editShiftForm">
                        @csrf
                        <input type="hidden" name="id" id="edit_id">
                        <div class="row g-3">
                            <div class="col-8">
                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="edit_name" class="form-control" required>
                                <small class="text-danger error-text edit_name_error"></small>
                            </div>
                            <div class="col-4">
                                <label class="form-label">Colour</label>
                                <input type="color" name="color_code" id="edit_color_code" class="form-control form-control-color">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Start time <span class="text-danger">*</span></label>
                                <input type="time" name="start_time" id="edit_start_time" class="form-control" required>
                                <small class="text-danger error-text edit_start_time_error"></small>
                            </div>
                            <div class="col-6">
                                <label class="form-label">End time <span class="text-danger">*</span></label>
                                <input type="time" name="end_time" id="edit_end_time" class="form-control" required>
                                <small class="text-danger error-text edit_end_time_error"></small>
                            </div>
                            <div class="col-4">
                                <label class="form-label">Grace (min)</label>
                                <input type="number" min="0" max="120" name="grace_minutes" id="edit_grace_minutes" class="form-control">
                            </div>
                            <div class="col-4">
                                <label class="form-label">Break (min)</label>
                                <input type="number" min="0" max="180" name="break_time" id="edit_break_time" class="form-control">
                            </div>
                            <div class="col-4">
                                <label class="form-label">Status</label>
                                <select name="status" id="edit_status" class="form-control">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="editShiftForm" class="btn btn-primary">Update Shift</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Delete confirm --}}
    <div class="modal fade modal-custom" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Shift</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Delete <strong id="deleteShiftName"></strong>? This cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        $(function () {
            toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 3000 };
            @if (session('success')) toastr.success(@json(session('success'))); @endif

            const csrf = $('meta[name="csrf-token"]').attr('content');
            let deleteId = null;

            function fillErrors(prefix, errors) {
                $.each(errors, (k, v) => $('.' + prefix + k + '_error').text(v[0]));
            }

            $('#addShiftForm').on('submit', function (e) {
                e.preventDefault();
                $('.error-text').text(''); $('#addFormError').addClass('d-none').text('');
                $.ajax({
                    url: "{{ route('shift.store') }}", type: 'POST', data: $(this).serialize(),
                    headers: { 'X-CSRF-TOKEN': csrf },
                    success: r => { toastr.success(r.message || 'Shift created'); setTimeout(() => location.reload(), 800); },
                    error: xhr => {
                        if (xhr.status === 422) fillErrors('', xhr.responseJSON.errors);
                        else $('#addFormError').removeClass('d-none').text(xhr.responseJSON?.message || 'Something went wrong.');
                    }
                });
            });

            $(document).on('click', '.edit-shift', function () {
                const d = $(this).data();
                $('#edit_id').val(d.id);
                $('#edit_name').val(d.name);
                $('#edit_color_code').val(d.color_code || '#4f46e5');
                $('#edit_start_time').val(d.start_time);
                $('#edit_end_time').val(d.end_time);
                $('#edit_grace_minutes').val(d.grace_minutes);
                $('#edit_break_time').val(d.break_time);
                $('#edit_status').val(d.status);
                $('#edit_description').val(d.description || '');
                $('.error-text').text(''); $('#editFormError').addClass('d-none');
                new bootstrap.Modal('#editShiftModal').show();
            });

            $('#editShiftForm').on('submit', function (e) {
                e.preventDefault();
                $('.error-text').text(''); $('#editFormError').addClass('d-none').text('');
                const id = $('#edit_id').val();
                $.ajax({
                    url: "{{ route('shift.update', '') }}/" + id, type: 'POST', data: $(this).serialize(),
                    headers: { 'X-CSRF-TOKEN': csrf },
                    success: r => { toastr.success(r.message || 'Shift updated'); setTimeout(() => location.reload(), 800); },
                    error: xhr => {
                        if (xhr.status === 422) fillErrors('edit_', xhr.responseJSON.errors);
                        else $('#editFormError').removeClass('d-none').text(xhr.responseJSON?.message || 'Something went wrong.');
                    }
                });
            });

            $(document).on('click', '.change-status', function () {
                $.ajax({
                    url: "{{ route('shift.change-status', '') }}/" + $(this).data('id'), type: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf },
                    success: r => { toastr.success(r.message || 'Status updated'); setTimeout(() => location.reload(), 600); },
                    error: xhr => toastr.error(xhr.responseJSON?.message || 'Failed to update status')
                });
            });

            $(document).on('click', '.delete-shift', function () {
                deleteId = $(this).data('id');
                $('#deleteShiftName').text($(this).data('name'));
                new bootstrap.Modal('#deleteModal').show();
            });

            $('#confirmDelete').on('click', function () {
                if (!deleteId) return;
                $.ajax({
                    url: "{{ route('shift.destroy', '') }}/" + deleteId, type: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrf },
                    success: r => { toastr.success(r.message || 'Shift deleted'); setTimeout(() => location.reload(), 800); },
                    error: xhr => {
                        bootstrap.Modal.getInstance(document.getElementById('deleteModal'))?.hide();
                        toastr.error(xhr.responseJSON?.message || 'Failed to delete shift');
                    }
                });
            });

            ['addShiftModal', 'editShiftModal'].forEach(id => {
                document.getElementById(id).addEventListener('hidden.bs.modal', () => {
                    $('#' + (id === 'addShiftModal' ? 'addShiftForm' : 'editShiftForm'))[0].reset();
                    $('.error-text').text('');
                });
            });
        });
    </script>
@endsection
