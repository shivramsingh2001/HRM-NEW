{{--
    <x-ui.status-badge :status="$leave->status" />
    <x-ui.status-badge status="approved" label="Fully Approved" />

    One component, one status->color mapping (see .status-badge[data-status]
    in theme-custom.css), used by every module instead of each one inventing
    its own badge classes/colors (which previously disagreed — e.g. "approved"
    was blue in Loans but green everywhere else).
--}}
@props(['status', 'label' => null])

<span class="status-badge" data-status="{{ strtolower((string) $status) }}">
    {{ $label ?? ucfirst(str_replace('_', ' ', (string) $status)) }}
</span>
