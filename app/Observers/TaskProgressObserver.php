<?php

namespace App\Observers;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\Log;

/**
 * Keeps projects.progress_percentage in sync with its tasks' completion
 * state, so project list/detail pages don't need to recompute this from
 * live COUNT queries on every render (see Project::getProgressPercentageAttribute,
 * which remains as a fallback for any project this observer hasn't touched).
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

            $total = $project->tasks()->count();
            $completed = $project->tasks()->whereIn('status', ['completed', 'approved'])->count();
            $percentage = $total === 0 ? 0 : (int) round(($completed / $total) * 100);

            if ($project->progress_percentage !== $percentage) {
                $project->progress_percentage = $percentage;
                $project->saveQuietly();
            }
        } catch (\Throwable $e) {
            Log::error('TaskProgressObserver recalculation failed for project ' . $projectId . ': ' . $e->getMessage());
        }
    }
}
