<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\ProjectNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Notifies a project's manager + active team members once a day while its
 * deadline is within the next 7 days, and again once daily while it's
 * overdue. Runs once daily (see routes/console.php), mirrors
 * TaskDeadlineReminders' structure.
 *
 * NOTE ON TENANCY: runs via the scheduler with no authenticated user/tenant
 * context, so Project's TenantTrait global scope simply no-ops (see
 * TenantTrait::bootTenantTrait) — Project::deadlineApproaching()/overdue()
 * naturally span every tenant here, which is what we want.
 *
 * Dedup is persisted on projects.deadline_reminder_sent_at — a project is
 * only reminded once per day, same discipline as TaskDeadlineReminders.
 */
class CheckProjectDeadlines extends Command
{
    protected $signature = 'projects:check-deadlines {--force : Ignore the once-per-day dedup guard}';

    protected $description = 'Notify project managers/teams when a deadline is approaching or overdue';

    public function handle(ProjectNotificationService $notifier)
    {
        $today = Carbon::today();
        $force = (bool) $this->option('force');

        $this->info('Checking project deadlines for ' . $today->toDateString());

        $approaching = Project::allTenants()->deadlineApproaching();
        $overdue = Project::allTenants()->overdue();

        if (!$force) {
            $notReminded = function ($q) use ($today) {
                $q->whereNull('deadline_reminder_sent_at')
                    ->orWhereDate('deadline_reminder_sent_at', '<', $today);
            };
            $approaching->where($notReminded);
            $overdue->where($notReminded);
        }

        $approachingProjects = $approaching->get();
        $overdueProjects = $overdue->get();

        $this->info('Found ' . $approachingProjects->count() . ' approaching, ' . $overdueProjects->count() . ' overdue.');

        $reminded = 0;

        foreach ($approachingProjects as $project) {
            try {
                if ($notifier->notifyDeadlineApproaching($project)) {
                    DB::table('projects')->where('id', $project->id)->update(['deadline_reminder_sent_at' => now()]);
                    $reminded++;
                }
            } catch (\Throwable $e) {
                Log::error('projects:check-deadlines — approaching notify failed', [
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ($overdueProjects as $project) {
            try {
                if ($notifier->notifyOverdue($project)) {
                    DB::table('projects')->where('id', $project->id)->update(['deadline_reminder_sent_at' => now()]);
                    $reminded++;
                }
            } catch (\Throwable $e) {
                Log::error('projects:check-deadlines — overdue notify failed', [
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Reminded {$reminded} project(s).");

        return self::SUCCESS;
    }
}
