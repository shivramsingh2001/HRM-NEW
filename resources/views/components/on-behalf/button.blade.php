{{--
    "Create … request" for an employee — admin / HR only. Pair with
    <x-on-behalf.modal module="..." /> in the page's create-modal section.
    module: loan | overtime | expense | leave | regularization
--}}
@props(['module', 'label' => null])

@php
    $labels = [
        'loan' => 'Create loan request',
        'overtime' => 'Add overtime',
        'expense' => 'Add expense',
        'leave' => 'Apply leave for employee',
        'regularization' => 'Add regularization',
    ];
@endphp

@if (in_array(auth()->user()->role ?? null, ['admin', 'hr'], true))
    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#onBehalf{{ ucfirst($module) }}Modal"
        title="Raise it for an employee — saved as approved and logged">
        <i class="feather-plus me-2"></i><span>{{ $label ?? $labels[$module] }}</span>
    </button>
@endif
