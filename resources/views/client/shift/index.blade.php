@extends('client.layout.master')

@section('style')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        /* ============================================
           MANAGE SHIFTS — matches Shift Settings' theme
           (single blue #0D6EFD, full-width main-content)
           ============================================ */

        /* ============ STAT CARDS ============ */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 10px;
            margin-bottom: 14px;
        }

        .stat-tile {
            background: #fff;
            border: 1px solid #edf2f7;
            border-radius: 10px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all .15s ease;
        }

        .stat-tile:hover {
            border-color: var(--primary-light, #EFF6FF);
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.05);
        }

        .stat-tile__icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-light, #EFF6FF);
            color: var(--icon-color, #0D6EFD);
            font-size: 14px;
            flex-shrink: 0;
        }

        .stat-tile__body {
            display: flex;
            flex-direction: column;
            min-width: 0;
            flex: 1;
        }

        .stat-tile__label {
            font-size: 10px;
            font-weight: 500;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            line-height: 1.2;
            margin-bottom: 2px;
        }

        .stat-tile__value {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary, #0D6EFD);
            line-height: 1.2;
        }

        .stat-tile__value.text-success { color: #059669; }
        .stat-tile__value.text-danger { color: #dc2626; }

        /* ============ MAIN CARD (shift definitions) ============ */
        .shift-card {
            background: #fff;
            border: 1px solid #edf2f7;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 14px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
        }

        .shift-card__head {
            padding: 12px 18px;
            border-bottom: 1px solid #f1f5f9;
            background: #fafbfc;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }

        .shift-card__title {
            margin: 0;
            font-size: 13.5px;
            font-weight: 600;
            color: #1e293b;
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .shift-card__title i { font-size: 14px; color: var(--icon-color, #0D6EFD); }

        .shift-card__body { padding: 0; }

        .shift-card__foot {
            padding: 8px 18px;
            border-top: 1px solid #f1f5f9;
            background: #fafbfc;
        }

        /* ============ TABLE ============ */
        table#shiftList { font-size: 12px; margin: 0; }
        table#shiftList td, table#shiftList th { vertical-align: middle; padding: 8px 14px; }
        table#shiftList thead th { font-size: 10px; text-transform: uppercase; letter-spacing: .02em; color: #64748b; background: #f8fafc; }
        .shift-dot { display: inline-block; width: 9px; height: 9px; border-radius: 3px; margin-right: 6px; vertical-align: middle; }
        .shift-name { font-weight: 600; color: #1e293b; }
        .muted-cell { color: #94a3b8; }
        .shift-manage .badge { font-size: 10px; font-weight: 600; }
        .shift-manage label.form-label { font-size: 11px; margin-bottom: 2px; color: #64748b; }
        .shift-manage .form-control-sm { font-size: 12px; padding: 3px 8px; height: auto; }

        /* ============ STATUS TOGGLE (small, blue) ============ */
        .status-toggle-wrap { display: flex; align-items: center; gap: 6px; }
        .status-toggle {
            appearance: none; -webkit-appearance: none;
            width: 30px; height: 17px; border-radius: 999px;
            background: #cbd5e1; border: none; position: relative;
            cursor: pointer; transition: background .15s ease; flex-shrink: 0;
            outline: none;
        }
        .status-toggle::before {
            content: ''; position: absolute; top: 2px; left: 2px;
            width: 13px; height: 13px; border-radius: 50%; background: #fff;
            transition: transform .15s ease; box-shadow: 0 1px 2px rgba(0,0,0,.15);
        }
        .status-toggle:checked { background: linear-gradient(135deg, #0D6EFD, #0D6EFD); }
        .status-toggle:checked::before { transform: translateX(13px); }
        .status-toggle:disabled { opacity: .5; cursor: wait; }
        .status-toggle-label { font-size: 11px; font-weight: 600; }
        .status-toggle-label.is-active { color: var(--primary, #0D6EFD); }
        .status-toggle-label.is-inactive { color: #94a3b8; }

        /* ============ ROW ACTIONS (no 3-dot dropdown) ============ */
        .row-actions { display: flex; align-items: center; justify-content: flex-end; gap: 4px; }
        .row-action-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 28px; height: 28px; border-radius: 7px;
            border: 1px solid #e2e8f0; background: #fff; color: #64748b;
            transition: all .15s ease; cursor: pointer; text-decoration: none;
        }
        .row-action-btn:hover { border-color: var(--primary, #0D6EFD); color: var(--primary, #0D6EFD); background: var(--primary-light, #EFF6FF); }
        .row-action-btn.danger:hover { border-color: #dc2626; color: #dc2626; background: #fef2f2; }

        /* ============ BUTTONS (header actions) ============ */
        .btn-save {
            display: inline-flex; align-items: center; gap: 6px;
            background: linear-gradient(135deg, #0D6EFD, #0D6EFD); color: #fff; border: none;
            font-size: 12.5px; font-weight: 500; padding: 7px 18px;
            border-radius: 8px; cursor: pointer; transition: all .15s ease; text-decoration: none;
        }
        .btn-save:hover { filter: brightness(0.9); color: #fff; box-shadow: 0 2px 8px rgba(13, 110, 253, 0.3); }

        .btn-ghost {
            display: inline-flex; align-items: center; gap: 5px;
            background: linear-gradient(135deg, #0D6EFD, #0D6EFD); color: #fff; border: 1px solid var(--primary, #0D6EFD);
            font-size: 11.5px; font-weight: 500; padding: 5px 12px;
            border-radius: 7px; text-decoration: none; cursor: pointer; transition: all .15s ease;
        }
        .btn-ghost:hover { filter: brightness(0.9); color: #fff; box-shadow: 0 2px 8px rgba(13, 110, 253, 0.3); }

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
        <x-ui.page-header title="Manage Shifts" :parent="['label' => 'Shift', 'route' => 'shift.roster']">
            <x-slot:actions>
                <a href="{{ route('shift.roster') }}" class="btn-ghost"><i class="feather-grid"></i> Roster &amp; Assign</a>
                <a href="#" class="btn-save" data-bs-toggle="modal" data-bs-target="#addShiftModal"><i class="feather-plus"></i> Add Shift</a>
            </x-slot:actions>
        </x-ui.page-header>

        <div class="main-content" style="padding: 20px !important;">
            @if (session('error'))
                <div class="alert alert-danger py-2">{{ session('error') }}</div>
            @endif

            <div class="stat-grid">
                <div class="stat-tile">
                    <div class="stat-tile__icon"><i class="feather-clock"></i></div>
                    <div class="stat-tile__body">
                        <span class="stat-tile__label">Total</span>
                        <span class="stat-tile__value">{{ $totalShifts }}</span>
                    </div>
                </div>
                <div class="stat-tile">
                    <div class="stat-tile__icon"><i class="feather-check-circle"></i></div>
                    <div class="stat-tile__body">
                        <span class="stat-tile__label">Active</span>
                        <span class="stat-tile__value">{{ $activeShifts }}</span>
                    </div>
                </div>
                <div class="stat-tile">
                    <div class="stat-tile__icon"><i class="feather-slash"></i></div>
                    <div class="stat-tile__body">
                        <span class="stat-tile__label">Inactive</span>
                        <span class="stat-tile__value">{{ $inactiveShifts }}</span>
                    </div>
                </div>
                <div class="stat-tile">
                    <div class="stat-tile__icon"><i class="feather-users"></i></div>
                    <div class="stat-tile__body">
                        <span class="stat-tile__label">Assigned this month</span>
                        <span class="stat-tile__value">{{ $assignedShiftsCount }}</span>
                    </div>
                </div>
            </div>

            <div class="shift-card">
                <div class="shift-card__head">
                    <h5 class="shift-card__title"><i class="feather-list"></i> Shift Definitions</h5>
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
                <div class="shift-card__body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="shiftList">
                            <thead>
                                <tr>
                                    <th style="width:60px">Sr. No.</th>
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
                                        $overnight = \App\Support\ShiftWindow::isOvernight($shift);
                                    @endphp
                                    <tr>
                                        <td class="muted-cell">{{ $shifts->firstItem() + $loop->index }}</td>
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
                                            <div class="status-toggle-wrap">
                                                <input type="checkbox" class="status-toggle change-status" data-id="{{ $shift->id }}"
                                                       {{ $shift->status ? 'checked' : '' }}>
                                                <span class="status-toggle-label {{ $shift->status ? 'is-active' : 'is-inactive' }}">
                                                    {{ $shift->status ? 'Active' : 'Inactive' }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <div class="row-actions">
                                                <a href="#" class="row-action-btn edit-shift" title="Edit"
                                                   data-id="{{ $shift->id }}"
                                                   data-name="{{ $shift->name }}"
                                                   data-color_code="{{ $shift->color_code }}"
                                                   data-start_time="{{ $start->format('H:i') }}"
                                                   data-end_time="{{ $end->format('H:i') }}"
                                                   data-is_overnight="{{ $overnight ? 1 : 0 }}"
                                                   data-grace_minutes="{{ (int) $shift->grace_minutes }}"
                                                   data-break_time="{{ (int) $shift->break_time }}"
                                                   data-status="{{ (int) $shift->status }}"
                                                   data-description="{{ $shift->description }}">
                                                    <i class="feather-edit-3"></i>
                                                </a>
                                                <a href="#" class="row-action-btn danger delete-shift" title="Delete"
                                                   data-id="{{ $shift->id }}" data-name="{{ $shift->name }}">
                                                    <i class="feather-trash-2"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <span class="text-muted">No shifts yet. Click "Add Shift" to create one.</span>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($shifts->hasPages())
                    <div class="shift-card__foot">
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
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input overnight-check" type="checkbox" name="is_overnight" value="1" id="add_is_overnight">
                                    <label class="form-check-label" for="add_is_overnight">Overnight shift (ends next day)</label>
                                </div>
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
                            <div class="col-12"><small class="text-muted">Working hours are calculated from start/end minus break. For a night shift (e.g. 22:00 to 06:00) tick "Overnight shift".</small></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="addShiftForm" class="btn-save">Save Shift</button>
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
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input overnight-check" type="checkbox" name="is_overnight" value="1" id="edit_is_overnight">
                                    <label class="form-check-label" for="edit_is_overnight">Overnight shift (ends next day)</label>
                                </div>
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
                    <button type="submit" form="editShiftForm" class="btn-save">Update Shift</button>
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

            // Typing an end time on/before the start time means the shift ends next day.
            $(document).on('change', '#addShiftForm input[type=time], #editShiftForm input[type=time]', function () {
                const $form = $(this).closest('form');
                const start = $form.find('[name=start_time]').val();
                const end = $form.find('[name=end_time]').val();
                if (start && end) $form.find('.overnight-check').prop('checked', end <= start);
            });

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

            $(document).on('click', '.edit-shift', function (e) {
                e.preventDefault();
                const d = $(this).data();
                $('#edit_id').val(d.id);
                $('#edit_name').val(d.name);
                $('#edit_color_code').val(d.color_code || '#4f46e5');
                $('#edit_start_time').val(d.start_time);
                $('#edit_end_time').val(d.end_time);
                $('#edit_is_overnight').prop('checked', String(d.is_overnight) === '1');
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

            $(document).on('change', '.change-status', function () {
                const $toggle = $(this);
                $toggle.prop('disabled', true);
                $.ajax({
                    url: "{{ route('shift.change-status', '') }}/" + $toggle.data('id'), type: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf },
                    success: r => { toastr.success(r.message || 'Status updated'); setTimeout(() => location.reload(), 500); },
                    error: xhr => {
                        $toggle.prop('disabled', false).prop('checked', !$toggle.is(':checked'));
                        toastr.error(xhr.responseJSON?.message || 'Failed to update status');
                    }
                });
            });

            $(document).on('click', '.delete-shift', function (e) {
                e.preventDefault();
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
