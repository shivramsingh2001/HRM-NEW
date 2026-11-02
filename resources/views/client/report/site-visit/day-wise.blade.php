@extends('client.layout.master')

@section('content-area')
    <x-ui.page-header title="Day-wise Site Visits" :parent="['label' => 'Reports', 'route' => 'report.attendance.index']">
        <x-slot:actions>
            <a href="{{ route('report.site-visit.day-wise.export', request()->query()) }}" class="btn btn-light btn-sm"><i class="feather-download me-1"></i> Export</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 20px !important;">
        @include('client.report.partials.report-subnav', ['group' => 'site_visit'])

        <x-ui.filter-card title="Filter Report" :clear-url="request()->hasAny(['date', 'user_id', 'site_id']) ? route('report.site-visit.day-wise.index') : null">
            <form method="GET" class="d-flex flex-wrap gap-2 filter-row">
                <input type="date" name="date" class="form-control form-control-sm" style="width:auto;" value="{{ $filters['date'] }}" max="{{ now()->toDateString() }}" onchange="this.form.submit()">
                <select name="user_id" class="form-control form-control-sm" style="width:auto;" onchange="this.form.submit()">
                    <option value="">All Employees</option>
                    @foreach ($employees as $e)
                        <option value="{{ $e->id }}" @selected(($filters['user_id'] ?? null) == $e->id)>{{ $e->name }}{{ $e->employee_id ? " ({$e->employee_id})" : '' }}</option>
                    @endforeach
                </select>
                <select name="site_id" class="form-control form-control-sm" style="width:auto;" onchange="this.form.submit()">
                    <option value="">All Sites</option>
                    @foreach ($sites as $s)
                        <option value="{{ $s->id }}" @selected(($filters['site_id'] ?? null) == $s->id)>{{ $s->site_code }} · {{ $s->site_name }}</option>
                    @endforeach
                </select>
                <a href="{{ route('report.site-visit.day-wise.index') }}" class="btn btn-light btn-sm" title="Reset filters" aria-label="Reset filters"><i class="feather-refresh-cw"></i></a>
            </form>
        </x-ui.filter-card>

        <div class="card">
            <div class="card-body p-0">
                <x-ui.data-table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Site</th>
                            <th>Time</th>
                            <th>Contact</th>
                            <th>Remarks</th>
                            <th>File</th>
                            <th>Map</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($visits as $v)
                            @php $fileUrl = file_url($v->file_path, \App\Services\Site\SiteVisitService::FILE_MODULE); @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $v->user->name ?? '—' }}</div>
                                    <div class="text-muted small">{{ $v->user->employee_id ?? '' }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $v->site->site_name ?? '—' }}</div>
                                    <div class="text-muted small">{{ $v->site->site_code ?? '' }}{{ $v->site?->district ? ' · ' . $v->site->district : '' }}</div>
                                </td>
                                <td class="text-nowrap">{{ $v->visited_at->format('h:i A') }}@if ($v->edited_at)<div class="text-muted small">edited</div>@endif</td>
                                <td>
                                    {{ $v->contact_person_name ?: '—' }}
                                    @if ($v->contact_mobile)<div class="text-muted small">{{ $v->contact_mobile }}</div>@endif
                                </td>
                                <td style="max-width:320px; white-space:pre-line;">{{ $v->remarks }}</td>
                                <td>@if ($fileUrl)<a href="{{ $fileUrl }}" target="_blank" rel="noopener"><i class="feather-paperclip"></i> View</a>@else — @endif</td>
                                <td>
                                    @if ($v->latitude !== null && $v->longitude !== null)
                                        <a href="https://www.google.com/maps?q={{ $v->latitude }},{{ $v->longitude }}" target="_blank" rel="noopener"><i class="feather-map-pin"></i> Map</a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-ui.empty-state icon="map-pin" title="No visits on {{ \Carbon\Carbon::parse($filters['date'])->format('d M Y') }}" /></td></tr>
                        @endforelse
                    </tbody>
                </x-ui.data-table>
            </div>
        </div>

        <x-ui.pagination-footer :paginator="$visits" label="visits" />
    </div>
@endsection
