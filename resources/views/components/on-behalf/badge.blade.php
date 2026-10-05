{{--
    "Raised by <admin>" next to a request an admin / HR raised for the
    employee (row.created_by set and different from the owner). Names are
    looked up once per page.
--}}
@props(['by' => null, 'owner' => null])

@if ($by && (int) $by !== (int) $owner)
    @php
        static $names = [];
        $names[$by] ??= \App\Models\User::withoutGlobalScopes()->whereKey($by)->value('name') ?? 'Admin';
    @endphp
    <span class="badge bg-soft-primary text-primary ms-1" style="font-size:10px;font-weight:500"
          title="Raised by {{ $names[$by] }} on behalf of the employee — approved on creation">
        <i class="feather-user-check me-1"></i>By {{ $names[$by] }}
    </span>
@endif
