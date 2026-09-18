@extends('client.layout.master')

@section('style')
<style>
    .component-type-badge { font-size: 9.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .02em; padding: 3px 8px; border-radius: 999px; }
    .type-earning { background: var(--success-light); color: var(--success); }
    .type-deduction { background: var(--danger-light); color: var(--danger); }
    .type-employer_contribution { background: var(--primary-light); color: var(--primary); }
    .type-reimbursement { background: var(--purple-light); color: var(--purple); }
    .method-pill { font-size: 9px; color: #6b7385; }

    /* active/edit column polish */
    .form-check.form-switch .form-check-input.toggle-status { width: 2.2em; height: 1.2em; cursor: pointer; }
    .form-check.form-switch .form-check-input.toggle-status:checked { background-color: var(--success); border-color: var(--success); }
    .form-check.form-switch .form-check-input.toggle-status:focus { box-shadow: 0 0 0 .2rem rgba(16, 185, 129, .18); }
    .btn-icon-edit {
        width: 30px; height: 30px; padding: 0;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 8px; border: 1px solid #dfe5f0; background: #f4f6fb; color: #475569;
        transition: all .15s;
    }
    .btn-icon-edit:hover { background: var(--primary-light); border-color: var(--primary); color: var(--primary); }
    .btn-icon-edit i { font-size: 14px; }

    /* ==================== COMPONENT DRAWERS (Add / Edit) ====================
       Width/header/body chrome comes from the shared .ui-drawer class
       (theme-custom.css) via the x-ui.drawer component — only the form's
       own field styling stays page-local. */
    #addComponentModal .card, #editComponentModal .card { border: none; }
    #addComponentModal .card-body, #editComponentModal .card-body { padding: 0; }
    #addComponentModal .btn, #editComponentModal .btn {
        padding: 4px 10px; font-size: 11px; border-radius: 7px; border: 1px solid transparent;
    }
    #addComponentModal .btn-modal-cancel, #editComponentModal .btn-modal-cancel { background: #f4f6fb; border-color: #dfe5f0; color: #475569; }
    #addComponentModal .btn-modal-cancel:hover, #editComponentModal .btn-modal-cancel:hover { background: var(--primary-light); border-color: var(--primary); color: var(--primary); }
    #addComponentModal .form-section, #editComponentModal .form-section { background: #fbfcfe; border: 1px solid #eaeef5; border-radius: 8px; padding: 10px; margin-bottom: 8px; }
    #addComponentModal .form-section h6, #editComponentModal .form-section h6 { font-size: 10px; font-weight: 700; color: #1a2236; text-transform: uppercase; letter-spacing: .03em; margin-bottom: 7px; }
    #addComponentModal .form-section label, #editComponentModal .form-section label { font-size: 10px; font-weight: 600; margin-bottom: 2px; }
    #addComponentModal .row > [class*="col-"], #editComponentModal .row > [class*="col-"] { flex: 0 0 100%; max-width: 100%; margin-bottom: 6px !important; }
    #addComponentModal .form-control, #editComponentModal .form-control,
    #addComponentModal .form-check-label, #editComponentModal .form-check-label { font-size: 10.5px; }
    #addComponentModal .form-control, #editComponentModal .form-control { padding: 3px 8px; height: auto; }
    #addComponentModal small, #editComponentModal small { font-size: 9px; }
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
                <h5 class="m-b-10">Payroll Components</h5>
            </div>
            <ul class="breadcrumb" style="margin-bottom:0.57rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Payroll Components</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="hstack gap-2">
                @if (in_array($role, ['admin', 'hr']))
                    <a href="#" class="btn btn-light-brand btn-sm rounded-pill" data-bs-toggle="offcanvas" data-bs-target="#addComponentModal">
                        <i class="feather-plus me-1"></i>Add Component
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
                                <th>Sr.No</th>
                                <th>Priority</th>
                                <th>Component</th>
                                <th>Type</th>
                                <th>Calculation</th>
                                <th>Proration</th>
                                <th>Wage Ceiling</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($components as $component)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $component->priority }}</td>
                                    <td>
                                        <strong>{{ $component->name }}</strong>
                                        <div class="text-muted" style="font-size:8px;">{{ $component->code }}</div>
                                    </td>
                                    <td>
                                        <span class="component-type-badge type-{{ $component->component_type }}">
                                            {{ str_replace('_', ' ', $component->component_type) }}
                                        </span>
                                        @if ($component->is_statutory)
                                            <div class="method-pill mt-1">{{ strtoupper($component->statutory_type) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($component->calculation_method === 'percentage')
                                            {{ rtrim(rtrim($component->percentage_value, '0'), '.') }}%
                                            of
                                            {{ $component->calculation_base_type === 'component'
                                                ? optional($component->baseComponent)->name
                                                : str_replace('_', ' ', $component->calculation_base) }}
                                        @else
                                            ₹{{ number_format($component->default_amount ?? 0, 2) }} (default)
                                        @endif
                                    </td>
                                    <td class="method-pill">{{ str_replace('_', ' ', $component->proration_rule) }}</td>
                                    <td>
                                        @if ($component->has_wage_ceiling)
                                            ₹{{ number_format($component->ceiling_amount, 0) }}
                                            <div class="method-pill">{{ $component->ceiling_apply_rule === 'ceiling_exclude' ? 'excluded above' : 'capped before %' }}</div>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="form-check form-switch mb-0">
                                            <input type="checkbox" class="form-check-input toggle-status"
                                                data-id="{{ $component->id }}"
                                                {{ $component->is_active ? 'checked' : '' }}>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <a href="#" class="btn-icon-edit edit-component"
                                            data-bs-toggle="offcanvas" data-bs-target="#editComponentModal"
                                            data-id="{{ $component->id }}"
                                            data-name="{{ $component->name }}"
                                            data-code="{{ $component->code }}"
                                            data-component_type="{{ $component->component_type }}"
                                            data-is_statutory="{{ $component->is_statutory ? 1 : 0 }}"
                                            data-statutory_type="{{ $component->statutory_type }}"
                                            data-is_taxable="{{ $component->is_taxable ? 1 : 0 }}"
                                            data-calculation_method="{{ $component->calculation_method }}"
                                            data-default_amount="{{ $component->default_amount }}"
                                            data-percentage_value="{{ $component->percentage_value }}"
                                            data-calculation_base_type="{{ $component->calculation_base_type }}"
                                            data-calculation_base="{{ $component->calculation_base }}"
                                            data-calculation_base_component_id="{{ $component->calculation_base_component_id }}"
                                            data-priority="{{ $component->priority }}"
                                            data-proration_rule="{{ $component->proration_rule }}"
                                            data-has_wage_ceiling="{{ $component->has_wage_ceiling ? 1 : 0 }}"
                                            data-ceiling_amount="{{ $component->ceiling_amount }}"
                                            data-ceiling_apply_rule="{{ $component->ceiling_apply_rule }}"
                                            data-affects_gross="{{ $component->affects_gross ? 1 : 0 }}"
                                            data-affects_ctc="{{ $component->affects_ctc ? 1 : 0 }}"
                                            data-affects_net="{{ $component->affects_net ? 1 : 0 }}"
                                            data-display_order="{{ $component->display_order }}"
                                            data-is_system_default="{{ $component->is_system_default ? 1 : 0 }}"
                                            title="Edit">
                                            <i class="feather feather-edit-3"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        No payroll components yet. Click "Add Component" to create one.
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
    @php
        $componentTypes = ['earning' => 'Earning', 'deduction' => 'Deduction', 'employer_contribution' => 'Employer Contribution', 'reimbursement' => 'Reimbursement'];
        $statutoryTypes = ['pf' => 'PF', 'esi' => 'ESI', 'pt' => 'Professional Tax', 'tds' => 'TDS', 'lwf' => 'LWF'];
        $baseTypes = ['basic' => 'Basic Salary', 'gross_pass1' => 'Gross Earnings (for deductions)', 'ctc' => 'CTC'];
        $prorationRules = [
            'prorate_by_payable_days' => 'Prorate by payable days',
            'prorate_by_worked_hours' => 'Prorate by worked hours',
            'prorate_by_lop_days' => 'Prorate by LOP days only',
            'no_proration' => 'No proration (always full value)',
        ];
        $ceilingRules = ['cap_base_before_percentage' => 'Cap base, then apply % (PF-style)', 'ceiling_exclude' => 'Not applicable at all above ceiling (ESI-style)'];
    @endphp

    {{-- ==================== Add Component Drawer ==================== --}}
    <x-ui.drawer id="addComponentModal" title="Add Payroll Component" width="480px">
                    <div class="card m-0">
                        <div class="card-body">
                            <form action="{{ route('payroll-components.store') }}" method="POST" id="addComponentForm">
                                @csrf
                                <div id="addComponentFormError" class="alert alert-danger d-none"></div>

                                <div class="form-section">
                                    <h6>Basics</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="name">Component Name *</label>
                                            <input type="text" class="form-control" name="name" id="name" placeholder="e.g. House Rent Allowance" required>
                                            <small class="text-danger error-text name_error"></small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="code">Code *</label>
                                            <input type="text" class="form-control" name="code" id="code" placeholder="e.g. hra" required>
                                            <small class="text-danger error-text code_error"></small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="component_type">Type *</label>
                                            <select class="form-control" name="component_type" id="component_type" required>
                                                @foreach ($componentTypes as $val => $label)
                                                    <option value="{{ $val }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <small class="text-danger error-text component_type_error"></small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="is_statutory" name="is_statutory" value="1">
                                                <label class="form-check-label" for="is_statutory">Statutory component</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3" id="statutory_type_wrap" style="display:none;">
                                            <label for="statutory_type">Statutory Type</label>
                                            <select class="form-control" name="statutory_type" id="statutory_type">
                                                @foreach ($statutoryTypes as $val => $label)
                                                    <option value="{{ $val }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="is_taxable" name="is_taxable" value="1" checked>
                                                <label class="form-check-label" for="is_taxable">Taxable</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h6>Calculation</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="calculation_method">Calculation Method *</label>
                                            <select class="form-control" name="calculation_method" id="calculation_method" required>
                                                <option value="fixed_amount">Fixed Amount</option>
                                                <option value="percentage">Percentage</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3" id="default_amount_wrap">
                                            <label for="default_amount">Default Amount (₹)</label>
                                            <input type="number" step="0.01" class="form-control" name="default_amount" id="default_amount" value="0">
                                        </div>
                                        <div class="col-md-6 mb-3" id="percentage_value_wrap" style="display:none;">
                                            <label for="percentage_value">Percentage (%)</label>
                                            <input type="number" step="0.001" min="0" max="100" class="form-control" name="percentage_value" id="percentage_value">
                                        </div>

                                        <div class="col-md-6 mb-3" id="calculation_base_type_wrap" style="display:none;">
                                            <label for="calculation_base_type">Calculate % Of *</label>
                                            <select class="form-control" name="calculation_base_type" id="calculation_base_type">
                                                <option value="none">N/A (fixed amount only)</option>
                                                <option value="fixed_base">A fixed base (Basic / Gross / CTC)</option>
                                                <option value="component">Another component</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3" id="calculation_base_wrap" style="display:none;">
                                            <label for="calculation_base">Base</label>
                                            <select class="form-control" name="calculation_base" id="calculation_base">
                                                @foreach ($baseTypes as $val => $label)
                                                    <option value="{{ $val }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3" id="calculation_base_component_wrap" style="display:none;">
                                            <label for="calculation_base_component_id">Base Component</label>
                                            <select class="form-control" name="calculation_base_component_id" id="calculation_base_component_id">
                                                <option value="">-- Select Component --</option>
                                                @foreach ($baseComponents as $bc)
                                                    <option value="{{ $bc->id }}">{{ $bc->name }} (priority {{ $bc->priority }})</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="priority">Priority *</label>
                                            <input type="number" class="form-control" name="priority" id="priority" value="100" min="1" max="999" required>
                                            <small class="text-muted d-block" style="font-size:9px;">Lower runs first. Must be higher than any component this one is based on.</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="proration_rule">Proration Rule *</label>
                                            <select class="form-control" name="proration_rule" id="proration_rule" required>
                                                @foreach ($prorationRules as $val => $label)
                                                    <option value="{{ $val }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h6>Wage Ceiling (optional — e.g. PF/ESI statutory caps)</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="has_wage_ceiling" name="has_wage_ceiling" value="1">
                                                <label class="form-check-label" for="has_wage_ceiling">Has a wage ceiling</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3 ceiling-field" style="display:none;">
                                            <label for="ceiling_amount">Ceiling Amount (₹)</label>
                                            <input type="number" step="0.01" class="form-control" name="ceiling_amount" id="ceiling_amount">
                                        </div>
                                        <div class="col-md-6 mb-3 ceiling-field" style="display:none;">
                                            <label for="ceiling_apply_rule">Ceiling Rule</label>
                                            <select class="form-control" name="ceiling_apply_rule" id="ceiling_apply_rule">
                                                @foreach ($ceilingRules as $val => $label)
                                                    <option value="{{ $val }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-6">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="feather-save me-2"></i>Save Component
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <a href="#" class="btn btn-modal-cancel float-end" data-bs-dismiss="offcanvas">
                                            <i class="feather-x me-2"></i>Cancel
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
    </x-ui.drawer>

    {{-- ==================== Edit Component Drawer ==================== --}}
    <x-ui.drawer id="editComponentModal" title="Edit Payroll Component" width="480px">
                    <div class="card m-0">
                        <div class="card-body">
                            <div id="edit_system_default_notice" class="alert alert-info d-none" style="font-size:11.5px;">
                                <i class="feather-info me-1"></i> This is a system-default component. Its code cannot be changed, but you can adjust its calculation rules.
                            </div>
                            <form id="editComponentForm">
                                @csrf
                                @method('PUT')
                                <div id="editComponentFormError" class="alert alert-danger d-none"></div>
                                <input type="hidden" name="id" id="edit_id">

                                <div class="form-section">
                                    <h6>Basics</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="edit_name">Component Name *</label>
                                            <input type="text" class="form-control" name="name" id="edit_name" required>
                                            <small class="text-danger error-text edit_name_error"></small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="edit_code">Code *</label>
                                            <input type="text" class="form-control" name="code" id="edit_code" required>
                                            <small class="text-danger error-text edit_code_error"></small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="edit_component_type">Type *</label>
                                            <select class="form-control" name="component_type" id="edit_component_type" required>
                                                @foreach ($componentTypes as $val => $label)
                                                    <option value="{{ $val }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="edit_is_statutory" name="is_statutory" value="1">
                                                <label class="form-check-label" for="edit_is_statutory">Statutory component</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3" id="edit_statutory_type_wrap">
                                            <label for="edit_statutory_type">Statutory Type</label>
                                            <select class="form-control" name="statutory_type" id="edit_statutory_type">
                                                @foreach ($statutoryTypes as $val => $label)
                                                    <option value="{{ $val }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="edit_is_taxable" name="is_taxable" value="1">
                                                <label class="form-check-label" for="edit_is_taxable">Taxable</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h6>Calculation</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="edit_calculation_method">Calculation Method *</label>
                                            <select class="form-control" name="calculation_method" id="edit_calculation_method" required>
                                                <option value="fixed_amount">Fixed Amount</option>
                                                <option value="percentage">Percentage</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3" id="edit_default_amount_wrap">
                                            <label for="edit_default_amount">Default Amount (₹)</label>
                                            <input type="number" step="0.01" class="form-control" name="default_amount" id="edit_default_amount">
                                        </div>
                                        <div class="col-md-6 mb-3" id="edit_percentage_value_wrap">
                                            <label for="edit_percentage_value">Percentage (%)</label>
                                            <input type="number" step="0.001" min="0" max="100" class="form-control" name="percentage_value" id="edit_percentage_value">
                                        </div>

                                        <div class="col-md-6 mb-3" id="edit_calculation_base_type_wrap">
                                            <label for="edit_calculation_base_type">Calculate % Of *</label>
                                            <select class="form-control" name="calculation_base_type" id="edit_calculation_base_type">
                                                <option value="none">N/A (fixed amount only)</option>
                                                <option value="fixed_base">A fixed base (Basic / Gross / CTC)</option>
                                                <option value="component">Another component</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3" id="edit_calculation_base_wrap">
                                            <label for="edit_calculation_base">Base</label>
                                            <select class="form-control" name="calculation_base" id="edit_calculation_base">
                                                @foreach ($baseTypes as $val => $label)
                                                    <option value="{{ $val }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3" id="edit_calculation_base_component_wrap">
                                            <label for="edit_calculation_base_component_id">Base Component</label>
                                            <select class="form-control" name="calculation_base_component_id" id="edit_calculation_base_component_id">
                                                <option value="">-- Select Component --</option>
                                                @foreach ($baseComponents as $bc)
                                                    <option value="{{ $bc->id }}">{{ $bc->name }} (priority {{ $bc->priority }})</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="edit_priority">Priority *</label>
                                            <input type="number" class="form-control" name="priority" id="edit_priority" min="1" max="999" required>
                                            <small class="text-muted d-block" style="font-size:9px;">Lower runs first. Must be higher than any component this one is based on.</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="edit_proration_rule">Proration Rule *</label>
                                            <select class="form-control" name="proration_rule" id="edit_proration_rule" required>
                                                @foreach ($prorationRules as $val => $label)
                                                    <option value="{{ $val }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h6>Wage Ceiling (optional — e.g. PF/ESI statutory caps)</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="edit_has_wage_ceiling" name="has_wage_ceiling" value="1">
                                                <label class="form-check-label" for="edit_has_wage_ceiling">Has a wage ceiling</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3 ceiling-field">
                                            <label for="edit_ceiling_amount">Ceiling Amount (₹)</label>
                                            <input type="number" step="0.01" class="form-control" name="ceiling_amount" id="edit_ceiling_amount">
                                        </div>
                                        <div class="col-md-6 mb-3 ceiling-field">
                                            <label for="edit_ceiling_apply_rule">Ceiling Rule</label>
                                            <select class="form-control" name="ceiling_apply_rule" id="edit_ceiling_apply_rule">
                                                @foreach ($ceilingRules as $val => $label)
                                                    <option value="{{ $val }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-6">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="feather-save me-2"></i>Update Component
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <a href="#" class="btn btn-modal-cancel float-end" data-bs-dismiss="offcanvas">
                                            <i class="feather-x me-2"></i>Cancel
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
    </x-ui.drawer>
@endsection

@section('script-area')
    <script>
        $(document).ready(function() {
            // ---- status toggle (unchanged behavior) ----
            $('.toggle-status').on('change', function() {
                const id = $(this).data('id');
                const checkbox = $(this);

                $.ajax({
                    url: '{{ url("payroll-components") }}/' + id + '/status',
                    type: 'PATCH',
                    data: {
                        _token: '{{ csrf_token() }}',
                        status: checkbox.is(':checked') ? 1 : 0
                    },
                    success: function(response) {
                        if (!response.success) {
                            checkbox.prop('checked', !checkbox.is(':checked'));
                        }
                    },
                    error: function() {
                        checkbox.prop('checked', !checkbox.is(':checked'));
                        alert('Failed to update status.');
                    }
                });
            });

            // ---- shared show/hide logic for both modals ----
            function wireComponentToggles(modalId, prefix) {
                const $modal = $(modalId);

                function toggleCalcFields() {
                    const method = $modal.find('#' + prefix + 'calculation_method').val();
                    $modal.find('#' + prefix + 'default_amount_wrap').toggle(method === 'fixed_amount');
                    $modal.find('#' + prefix + 'percentage_value_wrap').toggle(method === 'percentage');
                    $modal.find('#' + prefix + 'calculation_base_type_wrap').toggle(method === 'percentage');
                    if (method !== 'percentage') {
                        $modal.find('#' + prefix + 'calculation_base_wrap, #' + prefix + 'calculation_base_component_wrap').hide();
                    } else {
                        toggleBaseFields();
                    }
                }

                function toggleBaseFields() {
                    const baseType = $modal.find('#' + prefix + 'calculation_base_type').val();
                    $modal.find('#' + prefix + 'calculation_base_wrap').toggle(baseType === 'fixed_base');
                    $modal.find('#' + prefix + 'calculation_base_component_wrap').toggle(baseType === 'component');
                }

                $modal.find('#' + prefix + 'calculation_method').off('change').on('change', toggleCalcFields);
                $modal.find('#' + prefix + 'calculation_base_type').off('change').on('change', toggleBaseFields);
                $modal.find('#' + prefix + 'is_statutory').off('change').on('change', function() {
                    $modal.find('#' + prefix + 'statutory_type_wrap').toggle($(this).is(':checked'));
                });
                $modal.find('#' + prefix + 'has_wage_ceiling').off('change').on('change', function() {
                    $modal.find('.ceiling-field').toggle($(this).is(':checked'));
                });

                toggleCalcFields();
                $modal.find('#' + prefix + 'statutory_type_wrap').toggle($modal.find('#' + prefix + 'is_statutory').is(':checked'));
                $modal.find('.ceiling-field').toggle($modal.find('#' + prefix + 'has_wage_ceiling').is(':checked'));
            }

            wireComponentToggles('#addComponentModal', '');
            wireComponentToggles('#editComponentModal', 'edit_');

            // ---- Add Component submission ----
            $('#addComponentForm').on('submit', function(e) {
                e.preventDefault();
                $('.error-text').text('');
                $('#addComponentFormError').addClass('d-none').text('');

                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        if (response.success) {
                            bootstrap.Offcanvas.getInstance(document.getElementById('addComponentModal'))?.hide();
                            toastr.success(response.message);
                            setTimeout(() => location.reload(), 1000);
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422 && xhr.responseJSON.errors) {
                            $.each(xhr.responseJSON.errors, function(key, value) {
                                $('.' + key + '_error').text(value[0]);
                            });
                            toastr.error('Please fix the validation errors');
                        } else {
                            $('#addComponentFormError').removeClass('d-none').text(xhr.responseJSON?.message || 'Something went wrong.');
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });
            });

            // ---- Open Edit modal with data from the row ----
            $(document).on('click', '.edit-component', function(e) {
                const d = $(this).data();

                $('#edit_id').val(d.id);
                $('#edit_name').val(d.name);
                $('#edit_code').val(d.code);
                $('#edit_component_type').val(d.component_type);
                $('#edit_is_statutory').prop('checked', String(d.is_statutory) === '1');
                $('#edit_statutory_type').val(d.statutory_type);
                $('#edit_is_taxable').prop('checked', String(d.is_taxable) === '1');
                $('#edit_calculation_method').val(d.calculation_method);
                $('#edit_default_amount').val(d.default_amount);
                $('#edit_percentage_value').val(d.percentage_value);
                $('#edit_calculation_base_type').val(d.calculation_base_type);
                $('#edit_calculation_base').val(d.calculation_base);
                $('#edit_priority').val(d.priority);
                $('#edit_proration_rule').val(d.proration_rule);
                $('#edit_has_wage_ceiling').prop('checked', String(d.has_wage_ceiling) === '1');
                $('#edit_ceiling_amount').val(d.ceiling_amount);
                $('#edit_ceiling_apply_rule').val(d.ceiling_apply_rule);

                // A component can't be based on itself — disable its own option.
                $('#edit_calculation_base_component_id option').prop('disabled', false);
                $('#edit_calculation_base_component_id').val(d.calculation_base_component_id || '');
                $('#edit_calculation_base_component_id option[value="' + d.id + '"]').prop('disabled', true);

                // System-default components can't have their code changed.
                const isSystemDefault = String(d.is_system_default) === '1';
                $('#edit_code').prop('readonly', isSystemDefault);
                $('#edit_system_default_notice').toggleClass('d-none', !isSystemDefault);

                $('.error-text').text('');
                $('#editComponentFormError').addClass('d-none').text('');

                wireComponentToggles('#editComponentModal', 'edit_');
            });

            // ---- Edit Component submission ----
            $('#editComponentForm').on('submit', function(e) {
                e.preventDefault();
                $('.error-text').text('');
                $('#editComponentFormError').addClass('d-none').text('');

                const id = $('#edit_id').val();

                $.ajax({
                    url: '{{ url("payroll-components") }}/' + id,
                    type: 'PUT',
                    data: $(this).serialize(),
                    success: function(response) {
                        if (response.success) {
                            bootstrap.Offcanvas.getInstance(document.getElementById('editComponentModal'))?.hide();
                            toastr.success(response.message);
                            setTimeout(() => location.reload(), 1000);
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422 && xhr.responseJSON.errors) {
                            $.each(xhr.responseJSON.errors, function(key, value) {
                                $('.edit_' + key + '_error').text(value[0]);
                            });
                            toastr.error('Please fix the validation errors');
                        } else {
                            $('#editComponentFormError').removeClass('d-none').text(xhr.responseJSON?.message || 'Something went wrong.');
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });
            });

            $('#addComponentModal').on('hidden.bs.offcanvas', function() {
                $('#addComponentForm')[0].reset();
                $('.error-text').text('');
                $('#addComponentFormError').addClass('d-none').text('');
            });

            $('#editComponentModal').on('hidden.bs.offcanvas', function() {
                $('.error-text').text('');
                $('#editComponentFormError').addClass('d-none').text('');
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
