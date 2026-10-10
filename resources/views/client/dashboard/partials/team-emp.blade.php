{{-- Employee cell for the Manager Dashboard team cards — same look as the Admin Dashboard's
     "Most Regularization Requests" column: photo (or initials), bold name, employee id below.
     Expects $name, $employeeId, $image (stored profile_image path or null). --}}
<div class="d-flex align-items-center gap-2 min-w-0">
    <div class="tbl-avatar bg-soft-primary">
        @if ($image)
            <img src="{{ file_url($image, 'profile_photo') }}" alt="">
        @else
            {{ strtoupper(substr($name ?? 'NA', 0, 2)) }}
        @endif
    </div>
    <div class="min-w-0">
        <span class="d-block fw-bold text-truncate">{{ $name ?? 'N/A' }}</span>
        <span class="stat-sub">{{ $employeeId ?? '' }}</span>
    </div>
</div>
