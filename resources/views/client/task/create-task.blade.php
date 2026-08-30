@extends('client.layout.master')

@section('style')
    <style>
        .error-message {
            color: #dc3545;
            font-size: 0.875em;
            margin-top: 5px;
            display: block;
        }

        .has-error {
            border-color: #dc3545 !important;
        }

        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .alert-warning {
            color: #856404;
            background-color: #fff3cd;
            border-color: #ffeaa7;
        }

        .self-assigned-info {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
            display: block;
        }

        .assigned-to-wrapper {
            transition: all 0.3s ease;
        }

        .assigned-to-wrapper.hide-assigned {
            display: none !important;
        }

        .assigned-to-wrapper.show-assigned {
            display: flex !important;
        }
    </style>
@endsection
@php
    $user = Auth::user();
    $role = $user->role;
@endphp

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Tasks Management</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ url()->previous() }}">Tasks</a></li>
                <li class="breadcrumb-item">Create Task</li>
            </ul>
        </div>
    </div>

    <div class="main-content" style="padding: 30px !important;">
        <!-- Flash Messages Display -->
        <div class="row">
            <div class="col-lg-12">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if (session('warning'))
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        {{ session('warning') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <form action="{{ route('task.store') }}" method="POST" enctype="multipart/form-data" id="taskForm">
                    @csrf
                    <div class="card stretch stretch-full">
                        <div class="card-header">
                            <div class="d-flex align-items-center justify-content-between">
                                <h5 class="fw-bold mb-0 me-4">
                                    <span class="d-block mb-2">Create Task :</span>
                                    <span class="fs-12 fw-normal text-muted text-truncate-1-line">* marked fields must be
                                        filled !</span>
                                </h5>
                            </div>
                        </div>
                        <div class="card-body lead-status">

                            <!-- Self Assigned Field -->
                            <div class="row mb-4 align-items-center">
                                <div class="col-lg-4">
                                    <label for="self_assigned" class="fw-semibold">Assignment Type *: </label>
                                </div>
                                <div class="col-lg-8">
                                    <div class="input-group">
                                        <select class="form-control select2 @error('self_assigned') has-error @enderror"
                                            name="self_assigned" id="self_assigned" required>
                                            <option value="" disabled selected>Select Assignment Type</option>
                                            @if (!in_array($role, ['admin']))
                                                <option value="1" {{ old('self_assigned') == '1' ? 'selected' : '' }}>
                                                    Self Assigned (Task for myself)</option>
                                            @endif
                                            @if (!in_array($role, ['employee']))
                                                <option value="0" {{ old('self_assigned') == '0' ? 'selected' : '' }}>
                                                    Assign to Someone Else</option>
                                                <option value="2" {{ old('self_assigned') == '2' ? 'selected' : '' }}>
                                                    Assign to Group (Multiple Members)
                                                </option>
                                            @endif

                                        </select>
                                    </div>
                                    <small class="self-assigned-info">
                                        <i class="feather-info"></i>
                                        Self-assigned tasks will be assigned to you and will go to your reporting head for
                                        approval.
                                    </small>
                                    @error('self_assigned')
                                        <span class="error-message">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Assigned To Field - Hidden by default for self assigned -->
                            <div class="row mb-4 align-items-center" id="assignedToWrapper" style="display:none;">
                                <div class="col-lg-4">
                                    <label for="assigned_to" class="fw-semibold">Assigned To *: </label>
                                </div>
                                <div class="col-lg-8">
                                    <div class="input-group">
                                        <select name="assigned_to"
                                            class="form-select form-control select2 @error('assigned_to') has-error @enderror"
                                            id="assigned_to">
                                            <option value="" disabled selected>Select User</option>
                                            @foreach ($users as $user)
                                                <option value="{{ $user->id }}"
                                                    {{ old('assigned_to') == $user->id ? 'selected' : '' }}>
                                                    {{ $user->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <small class="self-assigned-info">
                                        <i class="feather-users"></i> Select the user you want to assign this task to.
                                    </small>
                                    @error('assigned_to')
                                        <span class="error-message">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Group Task Fields - Hidden by default -->
                            <div class="row mb-4 align-items-center group-fields-wrapper hide-assigned"
                                id="groupFieldsWrapper" style="display:none;">
                                <div class="col-lg-4">
                                    <label class="fw-semibold">Group Members *: </label>
                                </div>
                                <div class="col-lg-8">
                                    <select name="group_members[]" id="group_members"
                                        class="form-select form-control select2" multiple>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}"
                                                {{ collect(old('group_members'))->contains($user->id) ? 'selected' : '' }}>
                                                {{ $user->name }} ({{ $user->employee_id ?? '' }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="self-assigned-info">
                                        <i class="feather-users"></i> Pick at least 2 members for this group task.
                                    </small>
                                </div>
                            </div>

                            <div class="row mb-4 align-items-center group-fields-wrapper hide-assigned"
                                id="groupRuleWrapper" style="display:none;">
                                <div class="col-lg-4">
                                    <label class="fw-semibold">Completion Rule *: </label>
                                </div>
                                <div class="col-lg-8">
                                    <select name="group_completion_rule" id="group_completion_rule"
                                        class="form-control select2">
                                        <option value="" disabled selected>How is this task considered complete?
                                        </option>
                                        <option value="all_must_complete"
                                            {{ old('group_completion_rule') == 'all_must_complete' ? 'selected' : '' }}>
                                            All members must complete
                                        </option>
                                        <option value="any_one"
                                            {{ old('group_completion_rule') == 'any_one' ? 'selected' : '' }}>
                                            Any one member's completion is enough
                                        </option>
                                        <option value="percentage"
                                            {{ old('group_completion_rule') == 'percentage' ? 'selected' : '' }}>
                                            Percentage threshold (e.g. 75% members done)
                                        </option>
                                        <option value="lead_decides"
                                            {{ old('group_completion_rule') == 'lead_decides' ? 'selected' : '' }}>
                                            Group lead decides
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-4 align-items-center group-fields-wrapper hide-assigned"
                                id="thresholdWrapper" style="display:none;">
                                <div class="col-lg-4">
                                    <label class="fw-semibold">Threshold % *: </label>
                                </div>
                                <div class="col-lg-8">
                                    <input type="number" name="completion_threshold" id="completion_threshold"
                                        class="form-control" min="1" max="100"
                                        value="{{ old('completion_threshold', 75) }}" placeholder="e.g. 75">
                                </div>
                            </div>

                            <div class="row mb-4 align-items-center group-fields-wrapper hide-assigned"
                                id="groupLeadWrapper" style="display:none;">
                                <div class="col-lg-4">
                                    <label class="fw-semibold">Group Lead (optional): </label>
                                </div>
                                <div class="col-lg-8">
                                    <select name="group_lead_id" id="group_lead_id" class="form-control select2">
                                        <option value="">No lead</option>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}"
                                                {{ old('group_lead_id') == $user->id ? 'selected' : '' }}>
                                                {{ $user->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- Project ID Field -->
                            <div class="row mb-4 align-items-center">
                                <div class="col-lg-4">
                                    <label for="project_id" class="fw-semibold">Project : </label>
                                </div>
                                <div class="col-lg-8">
                                    <div class="input-group">
                                        <select class="form-control select2 @error('project_id') has-error @enderror"
                                            name="project_id" id="project_id">
                                            <option value="" disabled selected>Select Project</option>
                                            @foreach ($projects as $project)
                                                <option value="{{ $project->id }}"
                                                    {{ old('project_id') == $project->id ? 'selected' : '' }}>
                                                    {{ $project->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    @error('project_id')
                                        <span class="error-message">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Priority Field -->
                            <div class="row mb-4 align-items-center">
                                <div class="col-lg-4">
                                    <label for="priority" class="fw-semibold">Priority *: </label>
                                </div>
                                <div class="col-lg-8">
                                    <div class="input-group">
                                        <select class="form-control select2 @error('priority') has-error @enderror"
                                            name="priority" id="priority" required>
                                            <option value="" disabled selected>Select Priority</option>
                                            <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>Low
                                                Priority</option>
                                            <option value="medium" {{ old('priority') == 'medium' ? 'selected' : '' }}>
                                                Medium Priority</option>
                                            <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>High
                                                Priority</option>
                                            <option value="critical"
                                                {{ old('priority') == 'critical' ? 'selected' : '' }}>
                                                Critical Priority</option>
                                        </select>
                                    </div>
                                    @error('priority')
                                        <span class="error-message">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Title Field -->
                            <div class="row mb-4 align-items-center">
                                <div class="col-lg-4">
                                    <label for="title" class="fw-semibold">Task Title *: </label>
                                </div>
                                <div class="col-lg-8">
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-user"></i></div>
                                        <input type="text" class="form-control @error('title') has-error @enderror"
                                            id="title" name="title" placeholder="Task Title"
                                            value="{{ old('title') }}" required>
                                    </div>
                                    @error('title')
                                        <span class="error-message">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Deadline Date Field -->
                            <div class="row mb-4 align-items-center">
                                <div class="col-lg-4">
                                    <label for="deadline_date" class="fw-semibold">Task Date *: </label>
                                </div>
                                <div class="col-lg-8">
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-clock"></i></div>
                                        <input type="date" class="form-control @error('task_date') has-error @enderror"
                                            id="task_date" name="task_date" value="{{ old('task_date') }}"
                                            min="{{ date('Y-m-d') }}" required>
                                    </div>
                                    @error('task_date')
                                        <span class="error-message">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Deadline Date Field -->
                            <div class="row mb-4 align-items-center">
                                <div class="col-lg-4">
                                    <label for="deadline_date" class="fw-semibold">Task Deadline *: </label>
                                </div>
                                <div class="col-lg-8">
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-clock"></i></div>
                                        <input type="date"
                                            class="form-control @error('deadline_date') has-error @enderror"
                                            id="deadline_date" name="deadline_date" value="{{ old('deadline_date') }}"
                                            min="{{ date('Y-m-d') }}" required>
                                    </div>
                                    @error('deadline_date')
                                        <span class="error-message">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Description Field -->
                            <div class="row mb-4 align-items-center">
                                <div class="col-lg-4">
                                    <label for="description" class="fw-semibold">Task Description *: </label>
                                </div>
                                <div class="col-lg-8">
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-type"></i></div>
                                        <textarea class="form-control @error('description') has-error @enderror" id="description" name="description"
                                            cols="30" rows="5" placeholder="Task Description" required>{{ old('description') }}</textarea>
                                    </div>
                                    @error('description')
                                        <span class="error-message">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- File Attachment Field -->
                            <div class="row mb-4 align-items-center">
                                <div class="col-lg-4">
                                    <label for="file" class="fw-semibold">Attachments: </label>
                                </div>
                                <div class="col-lg-8">
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-airplay"></i></div>
                                        <input type="file" class="form-control @error('file') has-error @enderror"
                                            id="file" name="file"
                                            accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx">
                                    </div>
                                    @error('file')
                                        <span class="error-message">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Voice Input Field -->
                            <div class="row mb-4 align-items-center">
                                <div class="col-lg-4">
                                    <label for="voice_file" class="fw-semibold">Voice Input: </label>
                                </div>
                                <div class="col-lg-8">
                                    <div class="input-group">
                                        <div>
                                            <button class="btn btn-xl btn-light-brand me-4 mb-4 mb-sm-0" type="button"
                                                id="recordButton">
                                                🎤 Start Recording
                                            </button>
                                        </div>
                                        <input type="file"
                                            class="form-control @error('voice_file') has-error @enderror" id="voice_file"
                                            name="voice_file" accept=".mp3,.wav,.m4a,.webm" style="display: none;">
                                        <audio id="audioPlayer" controls style="display: none;">
                                            Your browser does not support the audio element.
                                        </audio>
                                    </div>
                                    @error('voice_file')
                                        <span class="error-message">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary" style="float: left;">Create Task</button>
                            <button type="button" class="btn btn-danger" style="float: right;"
                                onclick="window.history.back()">Cancel</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {

            const taskDateInput = document.getElementById('task_date');
            const deadlineDateInput = document.getElementById('deadline_date');

            function updateDeadlineMin() {
                const taskDate = taskDateInput.value;
                if (taskDate) {
                    // Set minimum date for deadline to be the same as task date
                    deadlineDateInput.min = taskDate;

                    // If current deadline is less than task date, update it
                    if (deadlineDateInput.value && deadlineDateInput.value < taskDate) {
                        deadlineDateInput.value = taskDate;
                    }
                }
            }

            function validateDeadline() {
                const taskDate = taskDateInput.value;
                const deadlineDate = deadlineDateInput.value;

                if (taskDate && deadlineDate && deadlineDate < taskDate) {
                    deadlineDateInput.setCustomValidity('Deadline date cannot be earlier than task start date');
                    deadlineDateInput.classList.add('has-error');
                    return false;
                } else {
                    deadlineDateInput.setCustomValidity('');
                    deadlineDateInput.classList.remove('has-error');
                    return true;
                }
            }

            // Add event listeners for dates
            if (taskDateInput) {
                taskDateInput.addEventListener('change', function() {
                    updateDeadlineMin();
                    validateDeadline();
                });
            }

            if (deadlineDateInput) {
                deadlineDateInput.addEventListener('change', validateDeadline);
            }

            // Initialize on page load
            updateDeadlineMin();

            let mediaRecorder;
            let audioChunks = [];
            let isRecording = false;

            const recordBtn = document.getElementById("recordButton");
            const audioPlayer = document.getElementById("audioPlayer");
            const voiceFileInput = document.getElementById("voice_file");

            // Handle Self Assigned toggle
            const selfAssignedSelect = document.getElementById('self_assigned');
            const assignedToWrapper = document.getElementById('assignedToWrapper');
            const assignedToSelect = document.getElementById('assigned_to');

            function toggleAssignedFields() {

                const value = $('#self_assigned').val();

                // Hide all sections first
                $('#assignedToWrapper').hide();
                $('.group-fields-wrapper').hide();

                // Remove required
                $('#assigned_to').prop('required', false);
                $('#group_members').prop('required', false);
                $('#group_completion_rule').prop('required', false);

                // Assign to Someone Else
                if (value === '0') {

                    $('#assignedToWrapper').css('display', 'flex');
                    $('#assigned_to').prop('required', true);
                }

                // Assign to Group
                if (value === '2') {

                    $('.group-fields-wrapper').css('display', 'flex');

                    $('#group_members').prop('required', true);
                    $('#group_completion_rule').prop('required', true);

                    // Threshold show/hide
                    if ($('#group_completion_rule').val() === 'percentage') {
                        $('#thresholdWrapper').css('display', 'flex');
                    } else {
                        $('#thresholdWrapper').hide();
                    }
                }
            }

            // Main dropdown change
            $('#self_assigned').on('change', function() {
                toggleAssignedFields();
            });

            // Completion rule change
            $('#group_completion_rule').on('change', function() {

                if ($(this).val() === 'percentage') {
                    $('#thresholdWrapper').css('display', 'flex');
                } else {
                    $('#thresholdWrapper').hide();
                }
            });

        
            // Run on page load
            toggleAssignedToField();
            // Show threshold only when rule = percentage (with Select2 support)
            const groupCompletionRule = document.getElementById('group_completion_rule');
            if (groupCompletionRule) {
                // Native change event
                groupCompletionRule.addEventListener('change', function() {
                    const thresholdWrapper = document.getElementById('thresholdWrapper');
                    if (this.value === 'percentage') {
                        if (thresholdWrapper) thresholdWrapper.style.display = 'flex';
                    } else {
                        if (thresholdWrapper) thresholdWrapper.style.display = 'none';
                    }
                });

                // For Select2 compatibility
                if (typeof $ !== 'undefined' && $(groupCompletionRule).hasClass('select2')) {
                    $(groupCompletionRule).on('select2:select', function(e) {
                        const thresholdWrapper = document.getElementById('thresholdWrapper');
                        if (e.params.data.id === 'percentage') {
                            if (thresholdWrapper) thresholdWrapper.style.display = 'flex';
                        } else {
                            if (thresholdWrapper) thresholdWrapper.style.display = 'none';
                        }
                    });
                }
            }

            // Add multiple event listeners for the select element
            if (selfAssignedSelect) {
                // Native change event
                selfAssignedSelect.addEventListener('change', toggleAssignedToField);

                // For select2, we need to trigger on select2:select event
                if (typeof $ !== 'undefined' && $(selfAssignedSelect).hasClass('select2')) {
                    $(selfAssignedSelect).on('select2:select', function(e) {
                        toggleAssignedToField();
                    });

                    // Also trigger on select2:unselect
                    $(selfAssignedSelect).on('select2:unselect', function(e) {
                        toggleAssignedToField();
                    });
                }

                // Also add click event as fallback
                selfAssignedSelect.addEventListener('click', function() {
                    setTimeout(toggleAssignedToField, 100);
                });

                // Initial toggle
                setTimeout(toggleAssignedToField, 200);
            }

            // Recording functionality
            if (recordBtn) {
                recordBtn.onclick = async () => {
                    if (!isRecording) {
                        try {
                            const stream = await navigator.mediaDevices.getUserMedia({
                                audio: true
                            });

                            mediaRecorder = new MediaRecorder(stream);
                            audioChunks = [];

                            mediaRecorder.ondataavailable = e => {
                                audioChunks.push(e.data);
                            };

                            mediaRecorder.onstop = () => {
                                const audioBlob = new Blob(audioChunks, {
                                    type: "audio/webm"
                                });

                                // Convert to file
                                const audioFile = new File([audioBlob], "voice_recording_" + Date
                                    .now() + ".webm", {
                                        type: "audio/webm"
                                    });

                                // Create a data transfer object
                                const dataTransfer = new DataTransfer();
                                dataTransfer.items.add(audioFile);
                                voiceFileInput.files = dataTransfer.files;

                                // Show audio player
                                const audioUrl = URL.createObjectURL(audioBlob);
                                audioPlayer.src = audioUrl;
                                audioPlayer.style.display = 'block';

                                // Change button text back
                                recordBtn.textContent = "🎤 Start Recording";
                                recordBtn.classList.remove('btn-danger');
                                recordBtn.classList.add('btn-light-brand');
                                isRecording = false;
                            };

                            mediaRecorder.start();
                            recordBtn.textContent = "⏹ Stop Recording";
                            recordBtn.classList.remove('btn-light-brand');
                            recordBtn.classList.add('btn-danger');
                            isRecording = true;
                        } catch (error) {
                            alert("Error accessing microphone: " + error.message);
                        }
                    } else {
                        if (mediaRecorder) {
                            mediaRecorder.stop();
                        }
                    }
                };
            }

            // Form validation
            const taskForm = document.getElementById('taskForm');
            if (taskForm) {
                taskForm.addEventListener('submit', function(e) {
                    let isValid = true;
                    const selfAssignedValue = document.getElementById('self_assigned')?.value;
                    const isSelfAssigned = selfAssignedValue === '1';
                    const isGroupTask = selfAssignedValue === '2';

                    // Check required fields
                    const requiredFields = this.querySelectorAll('[required]');

                    requiredFields.forEach(field => {
                        // Skip assigned_to if self_assigned is true
                        if (isSelfAssigned && field.id === 'assigned_to') {
                            return;
                        }

                        // Special handling for group_members
                        if (isGroupTask && field.id === 'group_members') {
                            const selectElement = field;
                            const selectedOptions = selectElement.selectedOptions;
                            if (!selectedOptions || selectedOptions.length < 2) {
                                isValid = false;
                                field.classList.add('has-error');
                                return;
                            }
                        }

                        // Special handling for completion_threshold when rule is percentage
                        if (field.id === 'completion_threshold') {
                            const ruleSelect = document.getElementById('group_completion_rule');
                            if (ruleSelect && ruleSelect.value === 'percentage') {
                                if (!field.value.trim() || parseInt(field.value) < 1 || parseInt(
                                        field.value) > 100) {
                                    isValid = false;
                                    field.classList.add('has-error');
                                    return;
                                }
                            }
                        }

                        // Regular required field check
                        if (!field.value || (field.value.trim && !field.value.trim())) {
                            isValid = false;
                            field.classList.add('has-error');
                        } else {
                            field.classList.remove('has-error');
                        }
                    });

                    if (!isValid) {
                        e.preventDefault();
                        let errorMessage = 'Please fill all required fields marked with *';

                        if (isGroupTask) {
                            const ruleSelect = document.getElementById('group_completion_rule');
                            if (ruleSelect && ruleSelect.value === 'percentage') {
                                errorMessage =
                                    'Please fill all required fields. For percentage rule, threshold must be between 1-100%.';
                            }
                        }

                        alert(errorMessage);
                    }
                });
            }

            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    const closeBtn = alert.querySelector('.btn-close');
                    if (closeBtn) {
                        closeBtn.click();
                    }
                });
            }, 5000);
        });
    </script>
@endsection
