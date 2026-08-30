<?php

namespace App\Http\Controllers\Mom;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\Project;
use App\Models\User;
use App\Models\Task;
use App\Models\TaskAssign;
use App\Services\TaskNotificationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MeetingMinuteController extends Controller
{
    protected $notificationService;

    public function __construct(TaskNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function create(Request $request, $id)
    {
        $allUsers = User::where('status', '1')->get();
        $projects = Project::whereIn('status', ['ongoing', 'pending'])->get();

        $existingTasks = collect();
        $meeting = Meeting::with(['participants.user'])->find($id);

        if ($meeting) {
            // Get existing tasks for this meeting from the tasks table
            $existingTasks = Task::with(['project', 'assignments.assignedTo'])
                ->where('meeting_id', $meeting->id)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return view('client.mom.mom.create', compact('allUsers', 'projects', 'meeting', 'existingTasks'));
    }

    /**
     * Store meeting minutes and tasks
     */
    public function store(Request $request)
    {
        $authUser = Auth::user();

        // Validation rules
        $validator = Validator::make($request->all(), [
            'meeting_id' => 'nullable|exists:meetings,id',
            'mom_content' => 'required|string',
            'meeting_title' => 'nullable|string|max:255',
            'meeting_date' => 'nullable|date',
            'attendees' => 'nullable|array',
            'attendees.*' => 'exists:users,id',
            'tasks' => 'nullable|array',
            'tasks.*.title' => 'required_with:tasks|string|max:255',
            'tasks.*.assigned_to' => 'required_with:tasks|exists:users,id',
            'tasks.*.deadline' => 'required_with:tasks|date|after_or_equal:today',
            'tasks.*.severity' => 'nullable|in:low,medium,high,critical',
            'tasks.*.project' => 'nullable|exists:projects,id',
            'tasks.*.description' => 'nullable|string',
            'tasks.*.voice_data' => 'nullable|string',
            'tasks.*.attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:5120',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();

        try {
            $meeting = Meeting::find($request->meeting_id);
            if ($meeting) {
                $meeting->update([
                    'mom_content' => $request->mom_content,
                    'status' => 'completed'
                ]);
            }
            // Process tasks if any
            if ($request->has('tasks') && is_array($request->tasks)) {
                foreach ($request->tasks as $taskData) {
                    // Skip if task title is empty
                    if (empty($taskData['title'])) {
                        continue;
                    }

                    // Handle file attachment
                    $attachmentPath = null;
                    if (isset($taskData['attachment']) && $taskData['attachment'] instanceof \Illuminate\Http\UploadedFile) {
                        $file = $taskData['attachment'];
                        $extension = strtolower($file->getClientOriginalExtension());
                        $filename = time() . '_' . uniqid() . '.' . $extension;
                        $destinationPath = public_path('uploads/task/document');
                        if (!file_exists($destinationPath)) {
                            mkdir($destinationPath, 0755, true);
                        }
                        $file->move($destinationPath, $filename);
                        $attachmentPath = 'uploads/task/document/' . $filename;
                    }

                    // Handle voice data (base64 from recording)
                    $voiceFilePath = null;
                    if (isset($taskData['voice_data']) && !empty($taskData['voice_data'])) {
                        $voiceData = $taskData['voice_data'];
                        // Check if it's base64 data
                        if (strpos($voiceData, 'data:audio/') === 0) {
                            $voiceData = explode(',', $voiceData)[1] ?? '';
                            $voiceBinary = base64_decode($voiceData);
                            $voiceFileName = time() . '_' . uniqid() . '.wav';
                            $destinationPath = public_path('uploads/task/voice');
                            if (!file_exists($destinationPath)) {
                                mkdir($destinationPath, 0755, true);
                            }
                            file_put_contents($destinationPath . '/' . $voiceFileName, $voiceBinary);
                            $voiceFilePath = 'uploads/task/voice/' . $voiceFileName;
                        }
                    }

                    // Generate unique task code using the same method as TaskController
                    $taskCode = $this->generateUniqueTaskCode();

                    // Create task in tasks table with meeting_id
                    $task = Task::create([
                        'task_code' => $taskCode,
                        'title' => $taskData['title'],
                        'meeting_id' => $meeting->id, // Link task to meeting
                        'project_id' => $taskData['project'] ?? null,
                        'description' => $taskData['description'] ?? $taskData['title'],
                        'task_date' => date('Y-m-d'),
                        'deadline_date' => $taskData['deadline'],
                        'priority' => $taskData['severity'] ?? 'medium',
                        'status' => 'pending',
                        'file' => $attachmentPath,
                        'voice_file' => $voiceFilePath,
                    ]);

                    // Assign task to user
                    $assignedBy = $authUser->id;
                    $assignedTo = $taskData['assigned_to'];

                    TaskAssign::create([
                        'task_id' => $task->id,
                        'assigned_by' => $assignedBy,
                        'assigned_to' => $assignedTo,
                        'status' => 'assigned',
                    ]);

                    // Send notification to assigned user
                    try {
                        $assignedUser = User::find($assignedTo);
                        if ($assignedUser && $this->notificationService) {
                            $this->notificationService->notifyTaskAssigned($task, $assignedUser, $authUser);
                        }
                    } catch (Exception $e) {
                        Log::error('Failed to send task notification: ' . $e->getMessage());
                    }
                }
            }

            DB::commit();

            return redirect()->route('meetings.show', $meeting->id)
                ->with('success', 'Meeting minutes and ' . ($request->has('tasks') ? count($request->tasks) : 0) . ' task(s) created successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to save meeting minutes: ' . $e->getMessage())
                ->withInput();
        }
    }
    private function generateUniqueTaskCode()
    {
        return DB::transaction(function () {
            // Get the last task code using Task model
            $lastTask = Task::where('task_code', 'like', 'TASK-%')
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(task_code, 6) AS UNSIGNED) DESC')
                ->first();

            if (!$lastTask) {
                $nextNumber = 1;
            } else {
                // Extract numeric part from TASK-00001
                $lastNumber = (int) substr($lastTask->task_code, 5);
                $nextNumber = $lastNumber + 1;

                // Reset if exceeds 99999
                if ($nextNumber > 99999) {
                    $nextNumber = 1;
                }
            }

            return 'TASK-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
        });
    }

}
