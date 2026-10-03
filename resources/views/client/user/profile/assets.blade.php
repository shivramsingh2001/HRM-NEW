{{-- Employee 360 — assets (EmployeeProfileController::tabAssets) --}}
@php $form = fn (string $f, array $q = []) => route('employee.profile.form', ['id' => $encId, 'form' => $f] + $q); @endphp

<div class="p360-toolbar">
    <h5 class="section-title mb-0 border-0 pb-0"><i class="feather-box"></i> Assets</h5>
    @if ($can['assets_manage'])
        <a href="#" class="btn btn-sm btn-primary" data-p360-open="{{ $form('asset-assign') }}" data-title="Assign an asset to {{ $user->name }}">
            <i class="feather-plus me-1"></i>Assign asset</a>
    @endif
</div>
@if ($assignments->isEmpty())
    <x-ui.empty-state icon="box" title="No assets" subtitle="No company asset has been assigned to this employee." />
@else
    <div class="table-responsive">
        <table class="p360-table">
            <thead><tr><th>Asset</th><th>Serial no.</th><th>Assigned</th><th>Expected return</th><th>Returned</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @foreach ($assignments as $a)
                    <tr>
                        <td><a href="{{ route('assets.show', encrypt($a->asset_id)) }}">{{ $a->asset_code }} · {{ $a->asset_name }}</a></td>
                        <td>{{ $a->serial_number ?: '—' }}</td>
                        <td>{{ $a->assigned_at ? \Carbon\Carbon::parse($a->assigned_at)->format('d M Y') : '—' }}</td>
                        <td>{{ $a->expected_return_date ? \Carbon\Carbon::parse($a->expected_return_date)->format('d M Y') : '—' }}</td>
                        <td>{{ $a->returned_at ? \Carbon\Carbon::parse($a->returned_at)->format('d M Y') . ($a->return_condition ? ' (' . $a->return_condition . ')' : '') : '—' }}</td>
                        <td><x-ui.status-badge :status="$a->status" /></td>
                        <td class="text-end">
                            @if ($can['assets_manage'] && in_array($a->status, ['accepted', 'pending_acceptance'], true))
                                <a href="#" class="btn btn-sm btn-light-brand" data-title="Return asset"
                                    data-p360-open="{{ $form('asset-return', ['assignment' => $a->id]) }}">Return</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
