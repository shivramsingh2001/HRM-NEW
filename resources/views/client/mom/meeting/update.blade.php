{{-- resources/views/client/mom/meeting/update.blade.php --}}
@extends('client.layout.master')

@php
    $isEditable = $meeting->status === 'scheduled';
@endphp

@section('content-area')
    <x-ui.page-header title="Edit Meeting" :parent="['label' => 'Meetings', 'route' => 'meetings.index']">
        <x-slot:actions>
            <x-ui.status-badge :status="$meeting->status" />
        </x-slot:actions>
    </x-ui.page-header>
@endsection

@section('create-modal')
    <x-ui.drawer id="meetingDrawer" title="Edit Meeting" width="600px">
        @include('client.mom.meeting._form', [
            'meeting' => $meeting,
            'allUsers' => $allUsers,
            'isEditable' => $isEditable,
            'formAction' => route('meetings.update', $meeting->id),
            'formMethod' => 'PUT',
        ])
    </x-ui.drawer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const drawerEl = document.getElementById('meetingDrawer');
            const drawer = new bootstrap.Offcanvas(drawerEl);
            drawer.show();

            drawerEl.addEventListener('hidden.bs.offcanvas', function() {
                window.location.href = "{{ route('meetings.show', $meeting->id) }}";
            });

            setTimeout(() => {
                document.querySelectorAll('.alert .btn-close').forEach(btn => btn.click());
            }, 5000);
        });
    </script>
@endsection
