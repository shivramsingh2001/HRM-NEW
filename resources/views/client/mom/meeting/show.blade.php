{{-- resources/views/client/mom/meeting/show.blade.php
     Full-page fallback for direct navigation/deep links/bookmarks. The
     primary UI path (from meeting/index.blade.php) fetches this same
     content via AJAX into a drawer — see _show_content.blade.php and
     MeetingController::show()'s $request->ajax() branch. --}}
@extends('client.layout.master')

@section('content-area')
    <x-ui.page-header title="Meeting Details" :parent="['label' => 'Meetings', 'route' => 'meetings.index']" />

    <div class="main-content" style="padding: 20px !important;">
        @include('client.mom.meeting._show_content', ['meeting' => $meeting])
    </div>
@endsection
