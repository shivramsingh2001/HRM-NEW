{{--
    MOM authoring form — shared by the full-page fallback (mom/create.blade.php)
    and the AJAX-loaded "Minutes of Meeting" drawer on
    meeting/index.blade.php (MeetingMinuteController::create() returns this
    partial alone when $request->ajax()). Bundles its own <script> so it
    keeps working (Summernote/Select2 init, dynamic task rows, voice
    recording, submit validation) regardless of which context injected it —
    jQuery/Select2 are already global (loaded by client.layout.foot on every
    page); Summernote is added specifically to meeting/index.blade.php's
    script-area since it isn't loaded globally.

    Expects: $meeting, $allUsers, $projects, $existingTasks.
--}}
<div class="mom-authoring-container">
    <div class="card-modern card">
        <div class="card-header-modern">
            <h5><i class="feather-clipboard me-2"></i> Minutes of Meeting</h5>
            <p>Document meeting minutes and create actionable tasks</p>
        </div>

        <form action="{{ route('meetings.mom.store') }}" method="POST" enctype="multipart/form-data" id="momForm">
            @csrf
            <input type="hidden" name="meeting_id" value="{{ $meeting->id ?? '' }}">

            <div class="card-body" style="padding: 30px;">
                <div class="form-group-modern">
                    <label class="form-label-modern">
                        <i class="feather-file-text"></i> Meeting Minutes <span class="required-star">*</span>
                    </label>
                    <textarea name="mom_content" id="mom_content" class="summernote @error('mom_content') is-invalid @enderror">{{ old('mom_content', $meeting->mom_content ?? '') }}</textarea>
                    @error('mom_content')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Document the key discussion points, decisions, and action items</small>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4 mt-4">
                    <div>
                        <h5 class="mb-1"><i class="feather-check-square me-2" style="color: var(--icon-color, #0D6EFD);"></i> Action Items & Tasks</h5>
                        <p class="text-muted mb-0" style="font-size: 13px;">Create and assign tasks to team members</p>
                    </div>
                    <button type="button" class="btn-add-task btn" onclick="addTaskRow()">
                        <i class="feather-plus-circle me-2"></i> Add New Task
                    </button>
                </div>

                <div id="tasksContainer">
                    @if (isset($existingTasks) && $existingTasks->count() > 0)
                        @foreach ($existingTasks as $index => $task)
                            <div class="task-card" id="row_{{ $index }}" data-task-id="{{ $task->id }}">
                                <div class="task-header">
                                    <div>
                                        <span class="task-number">{{ $loop->iteration }}</span>
                                        <span class="ms-3 fw-semibold">Task Details</span>
                                        @if ($task->task_code)
                                            <small class="ms-2 text-muted">({{ $task->task_code }})</small>
                                        @endif
                                    </div>
                                    <button type="button" class="btn-remove-task" onclick="removeTaskRow({{ $index }})">
                                        <i class="feather-trash-2 me-1"></i> Remove
                                    </button>
                                </div>
                                <div class="task-body">
                                    <input type="hidden" name="tasks[{{ $index }}][id]" value="{{ $task->id }}">

                                    <div class="full-width">
                                        <label class="form-label-modern">
                                            <i class="feather-type"></i> Task Title <span class="required-star">*</span>
                                        </label>
                                        <input type="text" name="tasks[{{ $index }}][title]" class="form-control-modern" placeholder="Enter task title"
                                            value="{{ old("tasks.$index.title", $task->title) }}">
                                    </div>

                                    <div class="full-width" style="margin-top: 15px;">
                                        <label class="form-label-modern"><i class="feather-align-left"></i> Description</label>
                                        <textarea name="tasks[{{ $index }}][description]" class="form-control-modern" rows="2"
                                            placeholder="Enter task description">{{ old("tasks.$index.description", $task->description) }}</textarea>
                                    </div>

                                    <div class="row-grid-4">
                                        <div>
                                            <label class="form-label-modern"><i class="feather-user-check"></i> Assigned To <span class="required-star">*</span></label>
                                            <select name="tasks[{{ $index }}][assigned_to]" class="assigned-to-select select2" style="width: 100%;">
                                                <option value="">Select User</option>
                                                @foreach ($allUsers as $user)
                                                    <option value="{{ $user->id }}"
                                                        {{ old("tasks.$index.assigned_to", $task->assignments->first()->assignedTo->id ?? '') == $user->id ? 'selected' : '' }}>
                                                        {{ $user->name }} ({{ $user->employee_id ?? $user->email }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label-modern"><i class="feather-calendar"></i> Deadline <span class="required-star">*</span></label>
                                            <input type="date" name="tasks[{{ $index }}][deadline]" class="form-control-modern deadline-date"
                                                value="{{ old("tasks.$index.deadline", $task->deadline_date ? \Carbon\Carbon::parse($task->deadline_date)->format('Y-m-d') : '') }}">
                                        </div>
                                        <div>
                                            <label class="form-label-modern"><i class="feather-trending-up"></i> Priority Level</label>
                                            <select name="tasks[{{ $index }}][severity]" class="form-select-modern">
                                                <option value="low" {{ old("tasks.$index.severity", $task->priority) == 'low' ? 'selected' : '' }}>Low</option>
                                                <option value="medium" {{ old("tasks.$index.severity", $task->priority) == 'medium' ? 'selected' : '' }}>Medium</option>
                                                <option value="high" {{ old("tasks.$index.severity", $task->priority) == 'high' ? 'selected' : '' }}>High</option>
                                                <option value="critical" {{ old("tasks.$index.severity", $task->priority) == 'critical' ? 'selected' : '' }}>Critical</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label-modern"><i class="feather-git-branch"></i> Project</label>
                                            <select name="tasks[{{ $index }}][project]" class="form-select-modern">
                                                <option value="">Select Project</option>
                                                @foreach ($projects ?? [] as $project)
                                                    <option value="{{ $project->id }}" {{ old("tasks.$index.project", $task->project_id) == $project->id ? 'selected' : '' }}>
                                                        {{ $project->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row-grid-2">
                                        <div>
                                            <label class="form-label-modern"><i class="feather-mic"></i> Voice Note</label>
                                            <div class="voice-controls">
                                                <button type="button" class="btn-record-modern" onclick="startRecording(this, {{ $index }})">
                                                    <i class="feather-mic"></i> Record New
                                                </button>
                                                <button type="button" class="btn-stop-modern" onclick="stopRecording(this, {{ $index }})" style="display:none;">
                                                    <i class="feather-stop-circle"></i> Stop
                                                </button>
                                                <input type="hidden" name="tasks[{{ $index }}][voice_data]" class="voice-data">
                                            </div>
                                            <div class="audio-preview-modern" id="audio_preview_{{ $index }}">
                                                @if ($task->voice_file)
                                                    <audio controls style="width: 100%; margin-top: 10px;">
                                                        <source src="{{ file_url($task->voice_file, 'task_voice') }}" type="audio/wav">
                                                        Your browser does not support the audio element.
                                                    </audio>
                                                    <small class="text-muted">Existing voice note</small>
                                                @endif
                                            </div>
                                        </div>
                                        <div>
                                            <label class="form-label-modern"><i class="feather-paperclip"></i> Attachment</label>
                                            <input type="file" name="tasks[{{ $index }}][attachment]" class="form-control-modern" accept=".pdf,.doc,.docx,.jpg,.png" style="padding: 7px;">
                                            <div class="file-preview-modern" id="file_preview_{{ $index }}">
                                                @if ($task->file)
                                                    <div class="existing-file">
                                                        <i class="feather-paperclip"></i>
                                                        <a href="{{ file_url($task->file, 'task_document') }}" target="_blank">View existing file</a>
                                                        <small class="text-muted">(Upload a new file to replace)</small>
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

                <div class="alert alert-info" style="border-radius: 10px; margin-top: 20px;">
                    <i class="feather-info me-2"></i>
                    <strong>Note:</strong> Each task will be assigned to the selected team member and will appear in their dashboard. Voice notes and attachments will be accessible to the assignee.
                </div>
            </div>

            <div class="submit-section">
                <button type="submit" class="btn-submit">
                    <i class="feather-save me-2"></i> Save Meeting Minutes & Tasks
                </button>
                <button type="reset" class="btn-cancel">
                    <i class="feather-x me-2"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    (function() {
        let rowCounter = {{ isset($existingTasks) ? $existingTasks->count() : 0 }};
        let mediaRecorder;
        let audioChunks = [];
        let currentRecordingRow = null;
        let deletedTasksList = [];

        $(document).ready(function() {
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

            $('.assigned-to-select').select2({
                placeholder: "Select team member",
                allowClear: true,
                width: '100%'
            });

            const today = new Date().toISOString().split('T')[0];
            $('.deadline-date').attr('min', today);

            $(document).on('change', 'input[type="file"]', function(e) {
                const nameAttr = $(this).attr('name');
                if (nameAttr) {
                    const match = nameAttr.match(/\d+/);
                    if (match) {
                        const file = e.target.files[0];
                        const previewDiv = $(this).siblings('.file-preview-modern');
                        if (file) {
                            const fileSize = (file.size / 1024).toFixed(2);
                            previewDiv.html(`<i class="feather-check-circle text-success me-1"></i>${file.name} (${fileSize} KB)`).show();
                        } else {
                            previewDiv.html('').hide();
                        }
                    }
                }
            });
        });

        window.addTaskRow = function() {
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
                        <i class="feather-trash-2 me-1"></i> Remove
                    </button>
                </div>
                <div class="task-body">
                    <div class="full-width">
                        <label class="form-label-modern">
                            <i class="feather-type"></i> Task Title <span class="required-star">*</span>
                        </label>
                        <input type="text" name="tasks[${newRowId}][title]" class="form-control-modern"
                            placeholder="Enter task title" value="">
                    </div>
                    <div class="full-width" style="margin-top: 15px;">
                        <label class="form-label-modern">
                            <i class="feather-align-left"></i> Description
                        </label>
                        <textarea name="tasks[${newRowId}][description]" class="form-control-modern" rows="2"
                            placeholder="Enter task description"></textarea>
                    </div>
                    <div class="row-grid-4">
                        <div>
                            <label class="form-label-modern">
                                <i class="feather-user-check"></i> Assigned To <span class="required-star">*</span>
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
                                <i class="feather-calendar"></i> Deadline <span class="required-star">*</span>
                            </label>
                            <input type="date" name="tasks[${newRowId}][deadline]" class="form-control-modern deadline-date">
                        </div>
                        <div>
                            <label class="form-label-modern">
                                <i class="feather-trending-up"></i> Priority Level
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
                                <i class="feather-git-branch"></i> Project
                            </label>
                            <select name="tasks[${newRowId}][project]" class="form-select-modern">
                                <option value="">Select Project</option>
                                @foreach ($projects ?? [] as $project)
                                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row-grid-2">
                        <div>
                            <label class="form-label-modern">
                                <i class="feather-mic"></i> Voice Note
                            </label>
                            <div class="voice-controls">
                                <button type="button" class="btn-record-modern" onclick="startRecording(this, ${newRowId})">
                                    <i class="feather-mic"></i> Record
                                </button>
                                <button type="button" class="btn-stop-modern" onclick="stopRecording(this, ${newRowId})" style="display:none;">
                                    <i class="feather-stop-circle"></i> Stop
                                </button>
                                <input type="hidden" name="tasks[${newRowId}][voice_data]" class="voice-data">
                            </div>
                            <div class="audio-preview-modern" id="audio_preview_${newRowId}"></div>
                        </div>
                        <div>
                            <label class="form-label-modern">
                                <i class="feather-paperclip"></i> Attachment
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

            $(`#row_${newRowId} .assigned-to-select`).select2({
                placeholder: "Select team member",
                allowClear: true,
                width: '100%'
            });

            const today = new Date().toISOString().split('T')[0];
            $(`#row_${newRowId} .deadline-date`).attr('min', today);

            rowCounter++;
            updateRowNumbers();
        };

        window.removeTaskRow = function(rowId) {
            const taskCard = $(`#row_${rowId}`);
            const taskId = taskCard.data('task-id');

            if (taskId) {
                if (!deletedTasksList.includes(taskId)) {
                    deletedTasksList.push(taskId);
                }
            }

            taskCard.remove();
            updateRowNumbers();
        };

        function updateRowNumbers() {
            $('#tasksContainer .task-card').each(function(index, row) {
                $(row).find('.task-number').text(index + 1);

                const currentId = $(row).attr('id');
                const newIndex = index;
                $(row).attr('id', `row_${newIndex}`);

                $(row).find('input, select, textarea, button, div').each(function() {
                    const name = $(this).attr('name');
                    if (name && name.includes('tasks[')) {
                        const newName = name.replace(/tasks\[\d+\]/, `tasks[${newIndex}]`);
                        $(this).attr('name', newName);
                    }

                    const id = $(this).attr('id');
                    if (id && (id.includes('audio_preview_') || id.includes('file_preview_'))) {
                        const newId = id.replace(/\d+$/, newIndex);
                        $(this).attr('id', newId);
                    }

                    const onclick = $(this).attr('onclick');
                    if (onclick) {
                        let newOnclick = onclick;
                        if (onclick.includes('startRecording')) {
                            newOnclick = onclick.replace(/startRecording\(this, \d+\)/, `startRecording(this, ${newIndex})`);
                        }
                        if (onclick.includes('stopRecording')) {
                            newOnclick = newOnclick.replace(/stopRecording\(this, \d+\)/, `stopRecording(this, ${newIndex})`);
                        }
                        if (onclick.includes('removeTaskRow')) {
                            newOnclick = newOnclick.replace(/removeTaskRow\(\d+\)/, `removeTaskRow(${newIndex})`);
                        }
                        if (newOnclick !== onclick) {
                            $(this).attr('onclick', newOnclick);
                        }
                    }
                });
            });

            rowCounter = $('#tasksContainer .task-card').length;
        }

        window.startRecording = async function(button, rowId) {
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
                    const audio = document.createElement('audio');
                    audio.controls = true;
                    audio.style.width = '100%';
                    audio.style.borderRadius = '8px';

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
        };

        window.stopRecording = function(button, rowId) {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
            }
        };

        $('#momForm').on('submit', function(e) {
            const momContent = $('#mom_content').summernote('code');
            if (!momContent || momContent === '<p><br></p>') {
                alert('Please enter meeting minutes content');
                e.preventDefault();
                return false;
            }

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
    })();
</script>
