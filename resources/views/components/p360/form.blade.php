{{--
    Employee 360 action form (client/user/profile/forms/*). Loaded into the
    shared #p360Modal and posted by AJAX (see user-detail.blade.php):

    <x-p360.form :action="route('leave-credit.manual.store')" reload="leave" submit="Credit leave">
        ... fields ...
    </x-p360.form>

    reload    — tab keys to refresh after success (comma separated), or "page"
                for the server-rendered tabs. The Overview KPIs always refresh.
    urlField  — name of a field whose value replaces __ID__ in the action URL
                (when the endpoint carries the chosen record in its path).
--}}
@props(['action', 'reload' => '', 'submit' => 'Save', 'submitClass' => 'btn-primary', 'method' => 'POST', 'urlField' => null])

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" data-p360-form data-reload="{{ $reload }}"
    @if ($urlField) data-url-field="{{ $urlField }}" @endif {{ $attributes }}>
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif
    <div class="alert alert-danger p360-form-errors d-none"></div>

    {{ $slot }}

    <div class="d-flex gap-2 justify-content-end mt-3">
        <button type="button" class="btn btn-light-brand btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn {{ $submitClass }} btn-sm">{{ $submit }}</button>
    </div>
</form>
