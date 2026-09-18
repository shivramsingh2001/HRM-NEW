<?php

namespace App\Observers;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\Log;

/**
 * Keeps projects.progress_percentage in sync with its tasks' completion
 * state, so project list/detail pages don't need to recompute this from
 * live COUNT queries on every render. This is the single source of truth
 * for task-derived progress (Project::getProgressPercentageAttribute is now
 * a plain passthrough to the stored column, not a second implementation).
 * Skipped entirely when a PM has manually overridden progress via a Project
 * Update — see the progress_manual_override guard below.
 */
class TaskProgressObserver
{
    public function saved(Task $task): void
    {
        $this->recalculate($task->project_id);

        // A task can be moved between projects; if project_id just changed,
        // the old project's progress needs recalculating too.
        $original = $task->getOriginal('project_id');
        if ($original && $original != $task->project_id) {
            $this->recalculate($original);
        }
    }

    public function deleted(Task $task): void
    {
        $this->recalculate($task->project_id);
    }

    private function recalculate(?int $projectId): void
    {
        if (!$projectId) {
            return;
        }

        try {
            $project = Project::find($projectId);
            if (!$project) {
                return;
            }

            // A PM has manually reported a progress percentage via a Project
            // Update — preserve it instead of overwriting with the
            // task-derived figure. Cleared via ProjectController::resetProgress().
            if ($project->progress_manual_override) {
                return;
            }

            $this->applyTaskDerivedProgress($project);
        } catch (\Throwable $e) {
            Log::error('TaskProgressObserver recalculation failed for project ' . $projectId . ': ' . $e->getMessage());
        }
    }

    /**
     * Force a task-derived recalculation regardless of progress_manual_override
     * — used by ProjectController::resetProgress() right after it clears the
     * flag, so the UI reflects the reset immediately instead of waiting for
     * the next task change.
     */
    public function applyTaskDerivedProgress(Project $project): void
    {
        $total = $project->tasks()->count();
        $completed = $project->tasks()->whereIn('status', ['completed', 'approved'])->count();
        $percentage = $total === 0 ? 0 : (int) round(($completed / $total) * 100);

        if ($project->progress_percentage !== $percentage) {
            $project->progress_percentage = $percentage;
            $project->saveQuietly();
        }
    }
}
