{{--
    <x-ui.drawer id="addEmployeeDrawer" title="Add Employee">
        <form method="POST" action="{{ route('user.store') }}">
            @csrf
            ... fields ...
            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary btn-sm">Create</button>
                <button type="button" class="btn btn-modal-cancel btn-sm" data-bs-dismiss="offcanvas">Cancel</button>
            </div>
        </form>
    </x-ui.drawer>

    Trigger from anywhere on the page:
        <button data-bs-toggle="offcanvas" data-bs-target="#addEmployeeDrawer">Add Employee</button>

    For LARGE forms only (per the design plan's rule: modals for small forms,
    drawers for large forms) — generalizes the Add-Task offcanvas pattern that
    was previously duplicated byte-for-byte across two files. Place inside
    @section('create-modal') (the layout's existing slot for this — see
    master.blade.php's @yield('create-modal')), not inside the page content.
--}}
@props(['id', 'title' => '', 'width' => '640px'])

<div class="offcanvas offcanvas-end ui-drawer" tabindex="-1" id="{{ $id }}" aria-labelledby="{{ $id }}Label" style="width: {{ $width }};">
    <div class="offcanvas-header">
        <h5 id="{{ $id }}Label">{{ $title }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        {{ $slot }}
    </div>
</div>
