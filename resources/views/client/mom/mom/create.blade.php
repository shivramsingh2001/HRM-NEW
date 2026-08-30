@extends('client.layout.master')

@section('style')
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        /* Modern Card Design */
        .card-modern {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .card-header-modern {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px 25px;
            border-bottom: none;
        }

        .card-header-modern h5 {
            color: white;
            margin: 0;
            font-weight: 600;
        }

        .card-header-modern p {
            color: rgba(255, 255, 255, 0.9);
            margin: 5px 0 0 0;
            font-size: 13px;
        }

        /* Task Card Design */
        .task-card {
            background: #fff;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border: 1px solid #eef2f7;
            transition: all 0.3s ease;
        }

        .task-card:hover {
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            border-color: #d1d9e6;
        }

        .task-header {
            background: #f8fafc;
            padding: 15px 20px;
            border-bottom: 1px solid #eef2f7;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .task-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            width: 35px;
            height: 35px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }

        .task-body {
            padding: 20px;
        }

        .form-label-modern {
            font-weight: 600;
            font-size: 13px;
            color: #4a5568;
            margin-bottom: 8px;
            display: block;
        }

        .form-label-modern i {
            margin-right: 8px;
            color: #667eea;
        }

        .required-star {
            color: #e53e3e;
            margin-left: 4px;
        }

        .form-control-modern,
        .form-select-modern {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 15px;
            font-size: 14px;
            transition: all 0.3s ease;
            width: 100%;
        }

        .form-control-modern:focus,
        .form-select-modern:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }

        /* 4 Column Layout */
        .row-grid-4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        /* 2 Column Layout */
        .row-grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .full-width {
            grid-column: span 4;
        }

        /* Button Styles */
        .btn-add-task {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            margin-bottom: 25px;
        }

        .btn-add-task:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-remove-task {
            background: #fff;
            border: 1px solid #e53e3e;
            color: #e53e3e;
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-remove-task:hover {
            background: #e53e3e;
            color: white;
            transform: translateY(-1px);
        }

        .btn-record-modern {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 13px;
            margin-right: 8px;
        }

        .btn-stop-modern {
            background: #718096;
            border: none;
            color: white;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 13px;
        }

        /* Audio Preview */
        .audio-preview-modern {
            margin-top: 12px;
            /* padding: 10px; */
            background: #f7fafc;
            border-radius: 8px;
        }

        audio {
            width: 100%;
            border-radius: 8px;
            height: 40px !important;
        }

        /* File Preview */
        .file-preview-modern {
            margin-top: 10px;
            padding: 8px 12px;
            background: #edf2f7;
            border-radius: 8px;
            font-size: 12px;
            display: inline-block;
        }

        /* Submit Section */
        .submit-section {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            text-align: start;
        }

        .btn-submit {
            background: linear-gradient(135deg, #5449ea 0%, #767ddf 100%);
            border: none;
            padding: 12px 40px;
            border-radius: 10px;
            font-weight: 600;
            color: white;
            font-size: 16px;
            transition: all 0.3s ease;
        }


        .btn-cancel {
            background: #fff;
            border: 1px solid #cbd5e0;
            padding: 12px 40px;
            border-radius: 10px;
            font-weight: 600;
            color: #4a5568;
            margin-left: 10px;
        }

        .btn-cancel:hover {
            background: #f7fafc;
            border-color: #a0aec0;
        }

        /* Select2 Custom */
        .select2-container--default .select2-selection--single {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            height: 42px;
            padding: 5px;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 32px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }

        /* Summernote Custom */
        .note-editor.note-frame {
            border-radius: 10px;
            border-color: #e2e8f0;
        }

        /* Animation */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .task-card {
            animation: slideIn 0.3s ease;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .row-grid-4 {
                grid-template-columns: repeat(2, 1fr);
            }

            .full-width {
                grid-column: span 2;
            }
        }

        @media (max-width: 768px) {
            .row-grid-4 {
                grid-template-columns: 1fr;
            }

            .row-grid-2 {
                grid-template-columns: 1fr;
            }

            .full-width {
                grid-column: span 1;
            }
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Create Meeting Minutes</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('meetings.index') }}">Meeting</a></li>
                <li class="breadcrumb-item active">Create MOM</li>
            </ul>
        </div>
    </div>

    <div class="main-content" style="padding: 30px !important;">
        <div class="row">
            <div class="col-lg-12">
                <div class="card-modern card">
                    <div class="card-header-modern">
                        <h5><i class="fas fa-clipboard-list me-2"></i> Minutes of Meeting</h5>
                        <p>Document meeting minutes and create actionable tasks</p>
                    </div>

                    <form action="{{ route('meetings.mom.store') }}" method="POST" enctype="multipart/form-data"
                        id="momForm">
                        @csrf
                        <input type="hidden" name="meeting_id" value="{{ $meeting->id ?? '' }}">

                        <div class="card-body" style="padding: 30px;">
                            <!-- MOM Content Section -->
                            <div class="form-group-modern">
                                <label class="form-label-modern">
                                    <i class="fas fa-file-alt"></i> Meeting Minutes <span class="required-star">*</span>
                                </label>
                                <textarea name="mom_content" id="mom_content" class="summernote @error('mom_content') is-invalid @enderror">
    {{ old('mom_content', $meeting->mom_content ?? '') }}
</textarea>
                                @error('mom_content')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Document the key discussion points, decisions, and action
                                    items</small>
                            </div>

                            <!-- Tasks Section Header -->
                            <div class="d-flex justify-content-between align-items-center mb-4 mt-4">
                                <div>
                                    <h5 class="mb-1"><i class="fas fa-tasks me-2" style="color: #667eea;"></i> Action
                                        Items & Tasks</h5>
                                    <p class="text-muted mb-0" style="font-size: 13px;">Create and assign tasks to team
                                        members</p>
                                </div>
                                <button type="button" class="btn-add-task btn" onclick="addTaskRow()">
                                    <i class="fas fa-plus-circle me-2"></i> Add New Task
                                </button>
                            </div>

                            <!-- Tasks Container -->
                            <div id="tasksContainer">
                                <!-- Task Row Template -->
                                @if (isset($existingTasks) && $existingTasks->count() > 0)
                                    {{-- Display existing tasks from database --}}
                                    @foreach ($existingTasks as $index => $task)
                                        <div class="task-card" id="row_{{ $index }}"
                                            data-task-id="{{ $task->id }}">
                                            <div class="task-header">
                                                <div>
                                                    <span class="task-number">{{ $loop->iteration }}</span>
                                                    <span class="ms-3 fw-semibold">Task Details</span>
                                                    @if ($task->task_code)
                                                        <small class="ms-2 text-muted">({{ $task->task_code }})</small>
                                                    @endif
                                                </div>
                                                <button type="button" class="btn-remove-task"
                                                    onclick="removeTaskRow({{ $index }})">
                                                    <i class="fas fa-trash-alt me-1"></i> Remove
                                                </button>
                                            </div>
                                            <div class="task-body">
                                                <input type="hidden" name="tasks[{{ $index }}][id]"
                                                    value="{{ $task->id }}">

                                                <!-- Task Title - Full Width -->
                                                <div class="full-width">
                                                    <label class="form-label-modern">
                                                        <i class="fas fa-heading"></i> Task Title <span
                                                            class="required-star">*</span>
                                                    </label>
                                                    <input type="text" name="tasks[{{ $index }}][title]"
                                                        class="form-control-modern" placeholder="Enter task title"
                                                        value="{{ old("tasks.$index.title", $task->title) }}">
                                                </div>

                                                <!-- Description -->
                                                <div class="full-width" style="margin-top: 15px;">
                                                    <label class="form-label-modern">
                                                        <i class="fas fa-align-left"></i> Description
                                                    </label>
                                                    <textarea name="tasks[{{ $index }}][description]" class="form-control-modern" rows="2"
                                                        placeholder="Enter task description">{{ old("tasks.$index.description", $task->description) }}</textarea>
                                                </div>

                                                <!-- 4 Column Layout -->
                                                <div class="row-grid-4">
                                                    <!-- Assigned To -->
                                                    <div>
                                                        <label class="form-label-modern">
                                                            <i class="fas fa-user-check"></i> Assigned To <span
                                                                class="required-star">*</span>
                                                        </label>
                                                        <select name="tasks[{{ $index }}][assigned_to]"
                                                            class="assigned-to-select select2" style="width: 100%;">
                                                            <option value="">Select User</option>
                                                            @foreach ($allUsers as $user)
                                                                <option value="{{ $user->id }}"
                                                                    {{ old("tasks.$index.assigned_to", $task->assignments->first()->assignedTo->id ?? '') == $user->id ? 'selected' : '' }}>
                                                                    {{ $user->name }}
                                                                    ({{ $user->employee_id ?? $user->email }})
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    <!-- Deadline -->
                                                    <div>
                                                        <label class="form-label-modern">
                                                            <i class="fas fa-calendar-alt"></i> Deadline <span
                                                                class="required-star">*</span>
                                                        </label>
                                                        <input type="date" name="tasks[{{ $index }}][deadline]"
                                                            class="form-control-modern deadline-date"
                                                            value="{{ old("tasks.$index.deadline", $task->deadline_date ? \Carbon\Carbon::parse($task->deadline_date)->format('Y-m-d') : '') }}">
                                                    </div>

                                                    <!-- Severity/Priority -->
                                                    <div>
                                                        <label class="form-label-modern">
                                                            <i class="fas fa-chart-line"></i> Priority Level
                                                        </label>
                                                        <select name="tasks[{{ $index }}][severity]"
                                                            class="form-select-modern">
                                                            <option value="low"
                                                                {{ old("tasks.$index.severity", $task->priority) == 'low' ? 'selected' : '' }}>
                                                                Low</option>
                                                            <option value="medium"
                                                                {{ old("tasks.$index.severity", $task->priority) == 'medium' ? 'selected' : '' }}>
                                                                Medium</option>
                                                            <option value="high"
                                                                {{ old("tasks.$index.severity", $task->priority) == 'high' ? 'selected' : '' }}>
                                                                High</option>
                                                            <option value="critical"
                                                                {{ old("tasks.$index.severity", $task->priority) == 'critical' ? 'selected' : '' }}>
                                                                Critical</option>
                                                        </select>
                                                    </div>

                                                    <!-- Project -->
                                                    <div>
                                                        <label class="form-label-modern">
                                                            <i class="fas fa-project-diagram"></i> Project
                                                        </label>
                                                        <select name="tasks[{{ $index }}][project]"
                                                            class="form-select-modern">
                                                            <option value="">Select Project</option>
                                                            @foreach ($projects ?? [] as $project)
                                                                <option value="{{ $project->id }}"
                                                                    {{ old("tasks.$index.project", $task->project_id) == $project->id ? 'selected' : '' }}>
                                                                    {{ $project->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>

                                                <!-- 2 Column Layout -->
                                                <div class="row-grid-2">
                                                    <!-- Voice Recording -->
                                                    <div>
                                                        <label class="form-label-modern">
                                                            <i class="fas fa-microphone-alt"></i> Voice Note
                                                        </label>
                                                        <div class="voice-controls">
                                                            <button type="button" class="btn-record-modern"
                                                                onclick="startRecording(this, {{ $index }})">
                                                                <i class="fas fa-microphone"></i> Record New
                                                            </button>
                                                            <button type="button" class="btn-stop-modern"
                                                                onclick="stopRecording(this, {{ $index }})"
                                                                style="display:none;">
                                                                <i class="fas fa-stop"></i> Stop
                                                            </button>
                                                            <input type="hidden"
                                                                name="tasks[{{ $index }}][voice_data]"
                                                                class="voice-data">
                                                        </div>
                                                        <div class="audio-preview-modern"
                                                            id="audio_preview_{{ $index }}">
                                                            @if ($task->voice_file)
                                                                <audio controls style="width: 100%; margin-top: 10px;">
                                                                    <source src="{{ asset($task->voice_file) }}"
                                                                        type="audio/wav">
                                                                    Your browser does not support the audio element.
                                                                </audio>
                                                                <small class="text-muted">Existing voice note</small>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <!-- Attachment -->
                                                    <div>
                                                        <label class="form-label-modern">
                                                            <i class="fas fa-paperclip"></i> Attachment
                                                        </label>
                                                        <input type="file"
                                                            name="tasks[{{ $index }}][attachment]"
                                                            class="form-control-modern" accept=".pdf,.doc,.docx,.jpg,.png"
                                                            style="padding: 7px;">
                                                        <div class="file-preview-modern"
                                                            id="file_preview_{{ $index }}">
                                                            @if ($task->file)
                                                                <div class="existing-file">
                                                                    <i class="fas fa-paperclip"></i>
                                                                    <a href="{{ asset($task->file) }}"
                                                                        target="_blank">View existing file</a>
                                                                    <small class="text-muted">(Upload a new file to
                                                                        replace)</small>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                    @php $rowCounter = $existingTasks->count(); @endphp
                                @endif
                            </div>

                            <!-- Info Note -->
                            <div class="alert alert-info" style="border-radius: 10px; margin-top: 20px;">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> Each task will be assigned to the selected team member and will
                                appear in their dashboard. Voice notes and attachments will be accessible to the assignee.
                            </div>
                        </div>

                        <!-- Submit Section -->
                        <div class="submit-section">
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-save me-2"></i> Save Meeting Minutes & Tasks
                            </button>
                            <button type="reset" class="btn-cancel">
                                <i class="fas fa-times me-2"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Initialize rowCounter with the number of existing tasks
        let rowCounter = {{ isset($existingTasks) ? $existingTasks->count() : 0 }};
        let mediaRecorder;
        let audioChunks = [];
        let currentRecordingRow = null;

        $(document).ready(function() {
            // Initialize Summernote editor
            $('#mom_content').summernote({
                height: 250,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link']],
                    ['view', ['codeview', 'help']]
                ],
                placeholder: 'Document the meeting minutes here...'
            });

            // Initialize Select2 for all assigned-to selects
            // function initSelect2() {
            //     $('.assigned-to-select').select2({
            //         placeholder: "Select team member",
            //         allowClear: true,
            //         width: '100%'
            //     });
            // }
            // initSelect2();

            // Set minimum date for deadlines
            const today = new Date().toISOString().split('T')[0];
            $('.deadline-date').attr('min', today);

            // File preview
            $(document).on('change', 'input[type="file"]', function(e) {
                const nameAttr = $(this).attr('name');
                if (nameAttr) {
                    const match = nameAttr.match(/\d+/);
                    if (match) {
                        const rowId = match[0];
                        const file = e.target.files[0];
                        const previewDiv = $(this).siblings('.file-preview-modern');

                        if (file) {
                            const fileSize = (file.size / 1024).toFixed(2);
                            previewDiv.html(`
                            <i class="fas fa-check-circle text-success me-1"></i>
                            ${file.name} (${fileSize} KB)
                        `).show();
                        } else {
                            previewDiv.html('').hide();
                        }
                    }
                }
            });
        });

        // Add new task row
        function addTaskRow() {
            const newRowId = rowCounter;
            const currentTaskCount = $('#tasksContainer .task-card').length;

            const newRow = `
            <div class="task-card" id="row_${newRowId}">
                <div class="task-header">
                    <div>
                        <span class="task-number">${currentTaskCount + 1}</span>
                        <span class="ms-3 fw-semibold">Task Details</span>
                    </div>
                    <button type="button" class="btn-remove-task" onclick="removeTaskRow(${newRowId})">
                        <i class="fas fa-trash-alt me-1"></i> Remove
                    </button>
                </div>
                <div class="task-body">
                    <!-- Task Title - Full Width -->
                    <div class="full-width">
                        <label class="form-label-modern">
                            <i class="fas fa-heading"></i> Task Title <span class="required-star">*</span>
                        </label>
                        <input type="text" name="tasks[${newRowId}][title]" class="form-control-modern" 
                            placeholder="Enter task title" value="">
                    </div>
                    
                    <!-- Description -->
                    <div class="full-width" style="margin-top: 15px;">
                        <label class="form-label-modern">
                            <i class="fas fa-align-left"></i> Description
                        </label>
                        <textarea name="tasks[${newRowId}][description]" class="form-control-modern" rows="2" 
                            placeholder="Enter task description"></textarea>
                    </div>
                    
                    <!-- 4 Column Layout -->
                    <div class="row-grid-4">
                        <div>
                            <label class="form-label-modern">
                                <i class="fas fa-user-check"></i> Assigned To <span class="required-star">*</span>
                            </label>
                            <select name="tasks[${newRowId}][assigned_to]" class="assigned-to-select select2" style="width: 100%;">
                                <option value="">Select User</option>
                                @foreach ($allUsers as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->employee_id ?? $user->email }})</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div>
                            <label class="form-label-modern">
                                <i class="fas fa-calendar-alt"></i> Deadline <span class="required-star">*</span>
                            </label>
                            <input type="date" name="tasks[${newRowId}][deadline]" class="form-control-modern deadline-date">
                        </div>
                        
                        <div>
                            <label class="form-label-modern">
                                <i class="fas fa-chart-line"></i> Priority Level
                            </label>
                            <select name="tasks[${newRowId}][severity]" class="form-select-modern">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="form-label-modern">
                                <i class="fas fa-project-diagram"></i> Project
                            </label>
                            <select name="tasks[${newRowId}][project]" class="form-select-modern">
                                <option value="">Select Project</option>
                                @foreach ($projects ?? [] as $project)
                                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    
                    <!-- 2 Column Layout -->
                    <div class="row-grid-2">
                        <div>
                            <label class="form-label-modern">
                                <i class="fas fa-microphone-alt"></i> Voice Note
                            </label>
                            <div class="voice-controls">
                                <button type="button" class="btn-record-modern" onclick="startRecording(this, ${newRowId})">
                                    <i class="fas fa-microphone"></i> Record
                                </button>
                                <button type="button" class="btn-stop-modern" onclick="stopRecording(this, ${newRowId})" style="display:none;">
                                    <i class="fas fa-stop"></i> Stop
                                </button>
                                <input type="hidden" name="tasks[${newRowId}][voice_data]" class="voice-data">
                            </div>
                            <div class="audio-preview-modern" id="audio_preview_${newRowId}"></div>
                        </div>
                        
                        <div>
                            <label class="form-label-modern">
                                <i class="fas fa-paperclip"></i> Attachment
                            </label>
                            <input type="file" name="tasks[${newRowId}][attachment]" class="form-control-modern" 
                                accept=".pdf,.doc,.docx,.jpg,.png" style="padding: 7px;">
                            <div class="file-preview-modern" id="file_preview_${newRowId}"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;

            $('#tasksContainer').append(newRow);

            // Initialize select2 for new row
            $(`#row_${newRowId} .assigned-to-select`).select2({
                placeholder: "Select team member",
                allowClear: true,
                width: '100%'
            });

            // Set min date
            const today = new Date().toISOString().split('T')[0];
            $(`#row_${newRowId} .deadline-date`).attr('min', today);

            rowCounter++;
            updateRowNumbers();
        }

        // Remove task row
        function removeTaskRow(rowId) {
            // Check if this is an existing task from database
            const taskCard = $(`#row_${rowId}`);
            const taskId = taskCard.data('task-id');

            if (taskId) {
                // Add to deleted tasks list
                if (!deletedTasksList.includes(taskId)) {
                    deletedTasksList.push(taskId);
                    $('#deletedTasks').val(JSON.stringify(deletedTasksList));
                }
            }

            taskCard.remove();
            updateRowNumbers();
        }

        // Update row numbers and fix indices
        function updateRowNumbers() {
            $('#tasksContainer .task-card').each(function(index, row) {
                // Update the displayed task number
                $(row).find('.task-number').text(index + 1);

                // Get current ID
                const currentId = $(row).attr('id');
                const oldIndex = parseInt(currentId.split('_')[1]);
                const newIndex = index;

                // Update the row ID
                $(row).attr('id', `row_${newIndex}`);

                // Update all input names, IDs, and onclick handlers
                $(row).find('input, select, textarea, button, div').each(function() {
                    // Update name attributes
                    const name = $(this).attr('name');
                    if (name && name.includes('tasks[')) {
                        const newName = name.replace(/tasks\[\d+\]/, `tasks[${newIndex}]`);
                        $(this).attr('name', newName);
                    }

                    // Update ID attributes
                    const id = $(this).attr('id');
                    if (id && (id.includes('audio_preview_') || id.includes('file_preview_'))) {
                        const newId = id.replace(/\d+$/, newIndex);
                        $(this).attr('id', newId);
                    }

                    // Update onclick handlers
                    const onclick = $(this).attr('onclick');
                    if (onclick) {
                        let newOnclick = onclick;
                        if (onclick.includes('startRecording')) {
                            newOnclick = onclick.replace(/startRecording\(this, \d+\)/,
                                `startRecording(this, ${newIndex})`);
                        }
                        if (onclick.includes('stopRecording')) {
                            newOnclick = newOnclick.replace(/stopRecording\(this, \d+\)/,
                                `stopRecording(this, ${newIndex})`);
                        }
                        if (onclick.includes('removeTaskRow')) {
                            newOnclick = newOnclick.replace(/removeTaskRow\(\d+\)/,
                                `removeTaskRow(${newIndex})`);
                        }
                        if (newOnclick !== onclick) {
                            $(this).attr('onclick', newOnclick);
                        }
                    }
                });
            });

            // Update rowCounter to match the actual number of tasks
            rowCounter = $('#tasksContainer .task-card').length;
        }

        // Voice recording functions
        async function startRecording(button, rowId) {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    audio: true
                });
                mediaRecorder = new MediaRecorder(stream);
                audioChunks = [];
                currentRecordingRow = rowId;

                mediaRecorder.ondataavailable = event => {
                    audioChunks.push(event.data);
                };

                mediaRecorder.onstop = () => {
                    const audioBlob = new Blob(audioChunks, {
                        type: 'audio/wav'
                    });
                    const audioUrl = URL.createObjectURL(audioBlob);
                    const audio = document.createElement('audio');
                    audio.controls = true;
                    audio.style.width = '100%';
                    audio.style.borderRadius = '8px';

                    // Convert to base64
                    const reader = new FileReader();
                    reader.onloadend = function() {
                        $(`#row_${rowId} .voice-data`).val(reader.result);
                    };
                    reader.readAsDataURL(audioBlob);

                    $(`#audio_preview_${rowId}`).html(audio).show();
                    stream.getTracks().forEach(track => track.stop());

                    $(`#row_${rowId} .btn-record-modern`).show();
                    $(`#row_${rowId} .btn-stop-modern`).hide();
                };

                mediaRecorder.start();
                $(button).hide();
                $(`#row_${rowId} .btn-stop-modern`).show();

            } catch (err) {
                console.error('Error accessing microphone:', err);
                alert('Unable to access microphone. Please check permissions.');
            }
        }

        function stopRecording(button, rowId) {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
            }
        }

        // Track deleted tasks
        let deletedTasksList = [];

        // Form validation
        $('#momForm').on('submit', function(e) {
            // Validate MOM content
            const momContent = $('#mom_content').summernote('code');
            if (!momContent || momContent === '<p><br></p>') {
                alert('Please enter meeting minutes content');
                e.preventDefault();
                return false;
            }

            // Validate tasks
            let taskValidationError = false;
            $('input[name$="[title]"]').each(function() {
                if (!$(this).val()) {
                    taskValidationError = true;
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });

            $('.assigned-to-select').each(function() {
                if (!$(this).val()) {
                    taskValidationError = true;
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });

            $('.deadline-date').each(function() {
                if (!$(this).val()) {
                    taskValidationError = true;
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });

            if (taskValidationError) {
                alert('Please fill all required fields in tasks (Title, Assigned To, Deadline)');
                e.preventDefault();
                return false;
            }

            return true;
        });
    </script>
@endsection
