@extends('client.layout.master')

@section('style')
<style>
    /* action buttons */
    .btn-icon-edit, .btn-icon-delete {
        width: 30px; height: 30px; padding: 0;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 8px; border: 1px solid; transition: all .15s;
    }
    .btn-icon-edit { border-color: #dfe5f0; background: #f4f6fb; color: #475569; }
    .btn-icon-edit:hover { background: var(--primary-light); border-color: var(--primary); color: var(--primary); }
    .btn-icon-delete { border-color: #fbdada; background: var(--danger-light); color: var(--danger); margin-left: 6px; }
    .btn-icon-delete:hover { background: #fde3e3; border-color: var(--danger); color: #dc2626; }
    .btn-icon-edit i, .btn-icon-delete i { font-size: 14px; }

    /* ==================== STRUCTURE DRAWER (Add / Edit) ====================
       Width/header/body chrome comes from the shared .ui-drawer class
       (theme-custom.css) via the x-ui.drawer component — only the form's
       own field styling stays page-local. */
    #structureDrawer .form-section { background: #fbfcfe; border: 1px solid #eaeef5; border-radius: 8px; padding: 10px; margin-bottom: 8px; }
    #structureDrawer .form-section h6 { font-size: 10px; font-weight: 700; color: #1a2236; text-transform: uppercase; letter-spacing: .03em; margin-bottom: 6px; }
    #structureDrawer label { font-size: 10.5px; font-weight: 600; margin-bottom: 2px; }
    #structureDrawer .form-control { font-size: 10.5px; padding: 4px 8px; height: auto; }
    #structureDrawer .row > [class*="col-"] { max-width: 100%; margin-bottom: 4px !important; }
    #structureDrawer .component-row { display: flex; align-items: center; flex-wrap: nowrap; gap: 8px; padding: 5px 0; border-bottom: 1px solid #f4f6fb; }
    #structureDrawer .component-row:last-child { border-bottom: none; }
    #structureDrawer .component-row .form-check.mb-0 { flex: 0 0 auto; }
    #structureDrawer .component-row .comp-name { flex: 1 1 auto; min-width: 0; font-size: 11.5px; font-weight: 600; }
    #structureDrawer .component-row .comp-name label { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    #structureDrawer .component-row .comp-meta { font-size: 8.5px; color: #9aa1b1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    #structureDrawer .component-row .method-field { flex: 0 0 80px; width: 80px; }
    #structureDrawer .component-row .method-field select { font-size: 10px; padding: 3px 4px; height: auto; }
    #structureDrawer .component-row .override-field { flex: 0 0 100px; width: 100px; }
    #structureDrawer .component-row .override-field input { font-size: 10.5px; padding: 3px 6px; height: auto; width: 100%; }
    #structureDrawer .base-components-row {
        display: flex; flex-wrap: wrap; align-items: center; gap: 6px 10px;
        padding: 4px 0 8px 26px; margin-top: -4px; border-bottom: 1px solid #f4f6fb;
    }
    #structureDrawer .base-components-label { font-size: 9px; font-weight: 600; color: var(--gray-500); text-transform: uppercase; }
    #structureDrawer .base-comp-check {
        display: inline-flex; align-items: center; gap: 3px; font-size: 10px; font-weight: 400;
        background: #eef2ff; border: 1px solid #dbe2fb; border-radius: 999px; padding: 2px 8px; cursor: pointer;
    }
    #structureDrawer .base-comp-check input { margin: 0; }
    #structureDrawer .btn { padding: 4px 12px; font-size: 11px; border-radius: 7px; }
    #structureDrawer .btn-modal-cancel { background: #f4f6fb; border-color: #dfe5f0; color: #475569; }
    #structureDrawer .btn-modal-cancel:hover { background: var(--primary-light); border-color: var(--primary); color: var(--primary); }
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
                <h5 class="m-b-10">Payroll Structures</h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.57rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Payroll Structures</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="hstack gap-2">
                @if (in_array($role, ['admin', 'hr']))
                    <a href="#" class="btn btn-light-brand btn-sm rounded-pill" id="addStructureBtn">
                        <i class="feather-plus me-1"></i>Add Structure
                    </a>
                @endif
            </div>
        </div>
    </div>

    <div class="content-area-body">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Calculation Type</th>
                                <th>Components</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="structuresTableBody">
                            @forelse ($structures as $structure)
                                <tr id="structure-row-{{ $structure->id }}">
                                    <td>
                                        <strong>{{ $structure->name }}</strong>
                                        @if ($structure->description)
                                            <div class="text-muted" style="font-size:8px;">{{ $structure->description }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $structure->payroll_calculation_type === 'hour_based' ? 'Hour-based' : 'Day-based' }}
                                        @if ($structure->payroll_calculation_type === 'hour_based')
                                            <div class="text-muted" style="font-size:8px;">{{ $structure->working_hours_per_day }} hrs/day</div>
                                        @endif
                                    </td>
                                    <td>{{ $structure->components_count }} component{{ $structure->components_count == 1 ? '' : 's' }}</td>
                                    <td>
                                        <x-ui.status-badge :status="$structure->status ? 'active' : 'inactive'" />
                                    </td>
                                    <td class="text-end">
                                        <a href="#" class="btn-icon-edit edit-structure" data-id="{{ $structure->id }}" title="Edit" data-bs-toggle="tooltip">
                                            <i class="feather feather-edit-3"></i>
                                        </a>
                                        <a href="#" class="btn-icon-delete delete-structure" data-id="{{ $structure->id }}" title="Delete" data-bs-toggle="tooltip">
                                            <i class="feather feather-trash-2"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        No payroll structures yet. Click "Add Structure" to build one from your components.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    <x-ui.drawer id="structureDrawer" title="Add Payroll Structure" width="480px">
            @if ($components->isEmpty())
                <div class="alert alert-warning">
                    No payroll components exist yet. <a href="{{ route('payroll-components.index') }}">Create one first</a>.
                </div>
            @else
                <form id="structureForm">
                    @csrf
                    <input type="hidden" name="id" id="structure_id">
                    <div id="structureFormError" class="alert alert-danger d-none"></div>

                    <div class="form-section">
                        <h6>Basics</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="structure_name">Structure Name *</label>
                                <input type="text" class="form-control" name="name" id="structure_name" placeholder="e.g. Standard Tech Employee" required>
                                <small class="text-danger error-text name_error"></small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="payroll_calculation_type">Calculation Type *</label>
                                <select class="form-control" name="payroll_calculation_type" id="payroll_calculation_type" required>
                                    <option value="day_based">Day-based</option>
                                    <option value="hour_based">Hour-based</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3" id="working_hours_wrap" style="display:none;">
                                <label for="working_hours_per_day">Working Hours/Day</label>
                                <input type="number" step="0.5" class="form-control" name="working_hours_per_day" id="working_hours_per_day" value="8">
                            </div>
                            <div class="col-12 mb-3">
                                <label for="structure_description">Description</label>
                                <textarea class="form-control" name="description" id="structure_description" rows="2"></textarea>
                            </div>
                        </div>
                    </div>

                    @php $structureEarningComponents = $components->where('component_type', 'earning'); @endphp
                    @foreach (['earning' => 'Earnings', 'deduction' => 'Deductions', 'employer_contribution' => 'Employer Contributions', 'reimbursement' => 'Reimbursements'] as $type => $label)
                        @php $typeComponents = $components->where('component_type', $type); @endphp
                        @if ($typeComponents->isNotEmpty())
                            <div class="form-section">
                                <h6>{{ $label }}</h6>
                                @foreach ($typeComponents as $c)
                                    <div class="component-row" data-component-id="{{ $c->id }}">
                                        <div class="form-check mb-0">
                                            <input type="checkbox" class="form-check-input component-toggle"
                                                name="components[{{ $c->id }}][enabled]" value="1"
                                                id="comp_{{ $c->id }}" checked>
                                        </div>
                                        <div class="comp-name">
                                            <label for="comp_{{ $c->id }}" class="mb-0">{{ $c->name }}</label>
                                            <div class="comp-meta">
                                                {{ $c->calculation_method === 'percentage' ? rtrim(rtrim($c->percentage_value, '0'), '.') . '% of ' . str_replace('_', ' ', $c->calculation_base_type === 'component' ? optional($c->baseComponent)->name : $c->calculation_base) : '₹' . number_format($c->default_amount ?? 0, 2) . ' default' }}
                                            </div>
                                        </div>
                                        <div class="method-field">
                                            <select class="form-control override-method"
                                                name="components[{{ $c->id }}][override_calculation_method]"
                                                data-component-id="{{ $c->id }}">
                                                <option value="fixed_amount" {{ $c->calculation_method === 'fixed_amount' ? 'selected' : '' }}>Fixed</option>
                                                <option value="percentage" {{ $c->calculation_method === 'percentage' ? 'selected' : '' }}>% based</option>
                                            </select>
                                        </div>
                                        <div class="override-field">
                                            <input type="number" step="0.01" min="0" class="form-control override-amount-input"
                                                data-component-id="{{ $c->id }}"
                                                name="components[{{ $c->id }}][override_amount]"
                                                placeholder="₹{{ $c->default_amount ?? 0 }}" title="Override amount (blank = use catalog default)"
                                                style="{{ $c->calculation_method === 'percentage' ? 'display:none;' : '' }}">
                                            <input type="number" step="0.001" min="0" max="100" class="form-control override-percentage-input"
                                                data-component-id="{{ $c->id }}"
                                                name="components[{{ $c->id }}][override_percentage]"
                                                placeholder="{{ $c->percentage_value }}%" title="Override percentage (blank = use catalog default)"
                                                style="{{ $c->calculation_method === 'percentage' ? '' : 'display:none;' }}">
                                        </div>
                                    </div>
                                    @if ($structureEarningComponents->isNotEmpty() && in_array($type, ['deduction', 'employer_contribution']))
                                        {{-- Deductions/Employer Contributions only — an Earning itself
                                             switched to "% Of" doesn't get this. Which Earnings this
                                             template's percentage components are calculated on, e.g.
                                             "12% of Basic + HRA" — only ever offers Earnings that are
                                             actually checked/enabled above (filtered live by JS), same
                                             behavior as the Employee Assign/Revise drawer. Submitted as
                                             components[id][base_component_ids][], overriding the catalog
                                             default for this template only. --}}
                                        <div class="base-components-row" data-component-id="{{ $c->id }}" style="display:{{ $c->calculation_method === 'percentage' ? 'flex' : 'none' }};">
                                            <span class="base-components-label">% Of (Earnings):</span>
                                            @foreach ($structureEarningComponents as $ec)
                                                @if ($ec->id !== $c->id)
                                                    <label class="base-comp-check" data-earning-id="{{ $ec->id }}">
                                                        <input type="checkbox"
                                                            name="components[{{ $c->id }}][base_component_ids][]"
                                                            value="{{ $ec->id }}"
                                                            {{ $c->baseComponents->contains('id', $ec->id) ? 'checked' : '' }}>
                                                        {{ $ec->name }}
                                                    </label>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    @endforeach

                    <div class="row">
                        <div class="col-6">
                            <button type="submit" class="btn btn-primary" id="structureSubmitBtn">
                                <i class="feather-save me-2"></i>Save Structure
                            </button>
                        </div>
                        <div class="col-6">
                            <a href="#" class="btn btn-modal-cancel float-end" data-bs-dismiss="offcanvas">
                                <i class="feather-x me-2"></i>Cancel
                            </a>
                        </div>
                    </div>
                </form>
            @endif
    </x-ui.drawer>
@endsection

@section('script-area')
<script>
    $(document).ready(function() {
        const drawerEl = document.getElementById('structureDrawer');
        const drawer = drawerEl ? bootstrap.Offcanvas.getOrCreateInstance(drawerEl) : null;

        function toggleHours() {
            $('#working_hours_wrap').toggle($('#payroll_calculation_type').val() === 'hour_based');
        }
        $('#payroll_calculation_type').on('change', toggleHours);

        // Per-component row: switch between the amount/percentage override input
        // based on that row's own "Fixed / % based" select.
        function syncOverrideMethodRow($select) {
            const componentId = $select.data('component-id');
            const isPercentage = $select.val() === 'percentage';
            $('.override-amount-input[data-component-id="' + componentId + '"]').toggle(!isPercentage);
            $('.override-percentage-input[data-component-id="' + componentId + '"]').toggle(isPercentage);
            $('.base-components-row[data-component-id="' + componentId + '"]').toggle(isPercentage);
            if (isPercentage) refreshBaseComponentOptions();
        }

        // The "% Of (Earnings)" list must only ever offer Earnings that are
        // actually enabled/checked for THIS template — not the whole
        // catalog. Hides (and un-checks) any option whose own main enable
        // checkbox isn't ticked, same behavior as the Employee
        // Assign/Revise drawer.
        function refreshBaseComponentOptions() {
            $('.base-comp-check[data-earning-id]').each(function() {
                const earningId = $(this).data('earning-id');
                const isEnabled = $('#comp_' + earningId).is(':checked');
                $(this).toggle(isEnabled);
                if (!isEnabled) {
                    $(this).find('input[type="checkbox"]').prop('checked', false);
                }
            });
        }

        $(document).on('change', '.override-method', function() {
            syncOverrideMethodRow($(this));
        });

        $(document).on('change', '.component-toggle', refreshBaseComponentOptions);

        function syncAllOverrideMethodRows() {
            $('.override-method').each(function() {
                syncOverrideMethodRow($(this));
            });
        }

        function resetDrawerForAdd() {
            $('#structureForm')[0].reset();
            $('#structure_id').val('');
            $('.error-text').text('');
            $('#structureFormError').addClass('d-none').text('');
            $('#structureDrawerLabel').text('Add Payroll Structure');
            $('#structureSubmitBtn').html('<i class="feather-save me-2"></i>Save Structure');
            toggleHours();
            syncAllOverrideMethodRows();
            refreshBaseComponentOptions();
        }

        $('#addStructureBtn').on('click', function(e) {
            e.preventDefault();
            resetDrawerForAdd();
            drawer && drawer.show();
        });

        // ---- Open Edit drawer, populate from AJAX ----
        $(document).on('click', '.edit-structure', function(e) {
            e.preventDefault();
            const id = $(this).data('id');

            $.ajax({
                url: '{{ url("payroll-structures") }}/' + id,
                type: 'GET',
                success: function(response) {
                    if (!response.success) return;
                    const d = response.data;

                    resetDrawerForAdd();
                    $('#structure_id').val(d.id);
                    $('#structure_name').val(d.name);
                    $('#structure_description').val(d.description);
                    $('#payroll_calculation_type').val(d.payroll_calculation_type);
                    $('#working_hours_per_day').val(d.working_hours_per_day || 8);
                    toggleHours();

                    // reset every component to unchecked/blank, then apply this structure's selection
                    $('.component-toggle').prop('checked', false);
                    $('.override-field input').val('');

                    (d.selected_component_ids || []).forEach(function(componentId) {
                        $('#comp_' + componentId).prop('checked', true);
                    });

                    $.each(d.overrides || {}, function(componentId, values) {
                        if (values.override_calculation_method) {
                            $('.override-method[data-component-id="' + componentId + '"]').val(values.override_calculation_method);
                        }
                        if (values.override_amount !== null && values.override_amount !== undefined) {
                            $('input[name="components[' + componentId + '][override_amount]"]').val(values.override_amount);
                        }
                        if (values.override_percentage !== null && values.override_percentage !== undefined) {
                            $('input[name="components[' + componentId + '][override_percentage]"]').val(values.override_percentage);
                        }
                        if (values.base_component_ids && values.base_component_ids.length) {
                            const baseIds = values.base_component_ids.map(String);
                            $('.base-components-row[data-component-id="' + componentId + '"] input[type="checkbox"]')
                                .each(function() {
                                    $(this).prop('checked', baseIds.includes(String($(this).val())));
                                });
                        }
                    });

                    syncAllOverrideMethodRows();
                    refreshBaseComponentOptions();

                    $('#structureDrawerLabel').text('Edit Payroll Structure');
                    $('#structureSubmitBtn').html('<i class="feather-save me-2"></i>Update Structure');

                    drawer && drawer.show();
                },
                error: function() {
                    toastr.error('Failed to load structure details.');
                }
            });
        });

        // ---- Add/Edit submission (same form, branches on structure_id) ----
        $('#structureForm').on('submit', function(e) {
            e.preventDefault();
            $('.error-text').text('');
            $('#structureFormError').addClass('d-none').text('');

            const id = $('#structure_id').val();
            const url = id ? ('{{ url("payroll-structures") }}/' + id) : '{{ route("payroll-structures.store") }}';
            const method = id ? 'PUT' : 'POST';

            $.ajax({
                url: url,
                type: method,
                data: $(this).serialize(),
                success: function(response) {
                    if (response.success) {
                        drawer && drawer.hide();
                        toastr.success(response.message);
                        setTimeout(() => location.reload(), 1000);
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422 && xhr.responseJSON.errors) {
                        $.each(xhr.responseJSON.errors, function(key, value) {
                            $('.' + key + '_error').text(Array.isArray(value) ? value[0] : value);
                        });
                        toastr.error('Please fix the validation errors');
                    } else {
                        $('#structureFormError').removeClass('d-none').text(xhr.responseJSON?.message || 'Something went wrong.');
                        toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                    }
                }
            });
        });

        // ---- Delete ----
        $(document).on('click', '.delete-structure', function(e) {
            e.preventDefault();
            const id = $(this).data('id');

            if (!confirm('Delete this structure template? Employees already assigned via it keep their own component snapshot.')) {
                return;
            }

            $.ajax({
                url: '{{ url("payroll-structures") }}/' + id,
                type: 'DELETE',
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        $('#structure-row-' + id).fadeOut(200, function() { $(this).remove(); });
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to delete structure.');
                }
            });
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
