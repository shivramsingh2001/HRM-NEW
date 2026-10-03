{{--
    The list footer used on every page (design reference: Team page):
    "Showing 1 to 15 of 42 members" on the left, page boxes on the right.
    Keeps the current filters in the page links (appends the query string).

    <x-ui.pagination-footer :paginator="$teamData" label="members" />

    Props
      paginator  a LengthAwarePaginator; the footer shows whenever there is at least one row
                 (page boxes only when there is more than one page)
      label      what is being counted ("members", "requests" …; default "records")
      always     false = show the footer only when there is more than one page
      view       pagination view (default pagination::bootstrap-4 — the boxes styled in theme-custom.css)
--}}
@props(['paginator', 'label' => 'records', 'always' => true, 'view' => 'pagination::bootstrap-4'])

@if ($paginator && $paginator->total() > 0 && ($always || $paginator->hasPages()))
    <div {{ $attributes->merge(['class' => 'card-footer']) }}>
        <div class="pagination-wrapper">
            <div class="pagination-info">
                @if ($paginator->total() > 0)
                    Showing <strong>{{ $paginator->firstItem() }}</strong> to <strong>{{ $paginator->lastItem() }}</strong>
                    of <strong>{{ $paginator->total() }}</strong> {{ $label }}
                @else
                    No {{ $label }}
                @endif
            </div>
            @if ($paginator->hasPages())
                <div>{{ $paginator->appends(request()->query())->links($view) }}</div>
            @endif
        </div>
    </div>
@endif
