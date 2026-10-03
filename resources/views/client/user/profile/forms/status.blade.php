{{-- Employee 360 — activate / deactivate the employee (employee.toggle-status) --}}
@php $active = (int) $user->status === 1; @endphp
<x-p360.form :action="route('employee.toggle-status')" reload="page"
    :submit="$active ? 'Deactivate' : 'Activate'" :submitClass="$active ? 'btn-danger' : 'btn-primary'">
    <input type="hidden" name="id" value="{{ $user->id }}">
    <input type="hidden" name="status" value="{{ $active ? 0 : 1 }}">
    @if ($active)
        <p class="mb-0">Deactivate <strong>{{ $user->name }}</strong>? An inactive employee cannot sign in to the web panel or the mobile app. You can activate them again at any time.</p>
    @else
        <p class="mb-0">Activate <strong>{{ $user->name }}</strong>? They will be able to sign in again.</p>
    @endif
</x-p360.form>
