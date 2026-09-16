{{-- resources/views/client/mom/mom/create.blade.php
     Full-page fallback for direct navigation/deep links/bookmarks. The
     primary UI path (from meeting/index.blade.php) fetches this same
     content via AJAX into a drawer — see _mom_form_content.blade.php and
     MeetingMinuteController::create()'s $request->ajax() branch. --}}
@extends('client.layout.master')

@section('style')
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
@endsection

@section('content-area')
    <x-ui.page-header title="Minutes of Meeting" :parent="['label' => 'Meetings', 'route' => 'meetings.index']" />

    <div class="main-content" style="padding: 20px !important;">
        @include('client.mom.mom._mom_form_content', [
            'meeting' => $meeting,
            'allUsers' => $allUsers,
            'projects' => $projects,
            'existingTasks' => $existingTasks,
        ])
    </div>
@endsection

@section('script-area')
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
@endsection
