{{-- resources/views/client/mom/meeting/create.blade.php --}}
@extends('client.layout.master')

@section('content-area')
    <x-ui.page-header title="Schedule Meeting" :parent="['label' => 'Meetings', 'route' => 'meetings.index']" />
@endsection

@section('create-modal')
    <x-ui.drawer id="meetingDrawer" title="Schedule Meeting" width="600px">
        @include('client.mom.meeting._form', [
            'meeting' => null,
            'allUsers' => $allUsers,
            'isEditable' => true,
            'formAction' => route('meetings.store'),
            'formMethod' => 'POST',
        ])
    </x-ui.drawer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const drawerEl = document.getElementById('meetingDrawer');
            const drawer = new bootstrap.Offcanvas(drawerEl);
            drawer.show();

            // This is a dedicated page (not a same-page trigger), so closing
            // the drawer means leaving the page — back to the meeting list.
            drawerEl.addEventListener('hidden.bs.offcanvas', function() {
                window.location.href = "{{ route('meetings.index') }}";
            });

            setTimeout(() => {
                document.querySelectorAll('.alert .btn-close').forEach(btn => btn.click());
            }, 5000);
        });
    </script>
@endsection
