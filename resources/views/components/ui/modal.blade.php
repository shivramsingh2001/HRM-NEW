{{--
    <x-ui.modal id="addDepartmentModal" title="Add Department">
        <form method="POST" action="{{ route('department.store') }}">
            @csrf
            ... fields ...
            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                <button type="button" class="btn btn-modal-cancel btn-sm" data-bs-dismiss="modal">Cancel</button>
            </div>
        </form>
    </x-ui.modal>

    With a separate footer (buttons outside a scrolling body, e.g. a form
    with dynamic content above the actions):
    <x-ui.modal id="approvalModal" title="Process Request" bodyOnly>
        <x-slot:footer>
            <button class="btn btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" type="submit">Submit</button>
        </x-slot:footer>
        ... body fields ...
    </x-ui.modal>

    For SMALL forms only (per the design plan's rule: modals for small forms,
    drawers for large forms) — formalizes the .compact-modal pattern already
    proven across ~18 pages instead of each page re-declaring/overriding it.
--}}
@props(['id', 'title' => '', 'size' => 'sm', 'bodyOnly' => false])

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered compact-modal {{ $bodyOnly ? 'compact-modal-plain' : '' }} {{ $size === 'md' ? 'modal-md' : 'modal-sm' }}">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fs-14 fw-semibold mb-0" id="{{ $id }}Label">{{ $title }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            @if($bodyOnly)
                <div class="modal-body">
                    {{ $slot }}
                </div>
                @isset($footer)
                    <div class="modal-footer">{{ $footer }}</div>
                @endisset
            @else
                <div class="modal-body">
                    <div class="card mb-0">
                        <div class="card-body">
                            {{ $slot }}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
