<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\TaskDeadlineReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reminds assignees when a task's deadline is tomorrow, and again once it's
 * overdue. Runs once daily (see routes/console.php).
 *
 * NOTE ON TENANCY: this runs via the scheduler — there is no authenticated
 * user/tenant context, so the Task/TaskAssign/User Eloquent models' tenant
 * global scope would silently no-op (see TenantTrait::bootTenantTrait).
 * Every query below uses DB::table(...) with an explicit tenant_id filter
 * carried through the join, exactly like CheckMissedCheckIns. Eloquent User
 * is only hydrated at the final ->notify() call, via withoutGlobalScopes().
 *
 * Dedup is persisted on tasks.deadline_reminder_sent_at — a task is only
 * reminded once per day (covers both the "due tomorrow" and "overdue" cases
 * without spamming a daily reminder for every day a task stays overdue is
 * intentional: one nudge the day before, then daily nudges while overdue).
 */
class TaskDeadlineReminders extends Command
{
    protected $signature = 'tasks:deadline-reminders {--force : Ignore the once-per-day dedup guard}';

    protected $description = 'Notify task assignees when a deadline is tomorrow or already passed';

    public function handle()
    {
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();
        $force = (bool) $this->option('force');
        $terminalStatuses = ['completed', 'approved', 'rejected', 'cancelled'];

        $this->info('Checking task deadlines for ' . $today->toDateString());

        $query = DB::table('tasks')
            ->whereNotIn('status', $terminalStatuses)
            ->where(function ($q) use ($today, $tomorrow) {
                $q->whereDate('deadline_date', $tomorrow)
                    ->orWhereDate('deadline_date', '<', $today);
            });

        if (!$force) {
            $query->where(function ($q) use ($today) {
                $q->whereNull('deadline_reminder_sent_at')
                    ->orWhereDate('deadline_reminder_sent_at', '<', $today);
            });
        }

        $tasks = $query->select('id', 'tenant_id', 'task_code', 'title', 'deadline_date')->get();

        $this->info('Found ' . $tasks->count() . ' task(s) needing a reminder.');

        $remindedTasks = 0;
        $notificationsSent = 0;

        foreach ($tasks as $task) {
            if (!$task->tenant_id) {
                Log::warning('tasks:deadline-reminders — skipping task with no tenant_id', ['task_id' => $task->id]);
                continue;
            }

            $isOverdue = Carbon::parse($task->deadline_date)->lt($today);

            $assigneeIds = DB::table('task_assigns')
                ->where('task_id', $task->id)
                ->where('tenant_id', $task->tenant_id)
                // A group member who already finished their part isn't nagged.
                ->where(fn ($q) => $q->whereNull('individual_status')->orWhere('individual_status', '!=', 'completed'))
                ->pluck('assigned_to')
                ->unique();

            if ($assigneeIds->isEmpty()) {
                continue;
            }

            $users = User::withoutGlobalScopes()
                ->where('tenant_id', $task->tenant_id)
                ->where('status', 1)
                ->whereIn('id', $assigneeIds)
                ->get();

            $anySent = false;
            foreach ($users as $user) {
                try {
                    $user->notify(new TaskDeadlineReminderNotification(
                        $task->id,
                        $task->task_code,
                        $task->title,
                        Carbon::parse($task->deadline_date)->format('d M Y'),
                        $isOverdue
                    ));
                    $notificationsSent++;
                    $anySent = true;
                } catch (\Throwable $e) {
                    Log::error('tasks:deadline-reminders — notify failed', [
                        'task_id' => $task->id,
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Only mark as reminded if at least one notification actually
            // sent — same "don't mark success on total failure" discipline
            // as CheckMissedCheckIns::markNotificationSent().
            if ($anySent) {
                DB::table('tasks')->where('id', $task->id)->update(['deadline_reminder_sent_at' => now()]);
                $remindedTasks++;
            }
        }

        $this->info("Reminded {$remindedTasks} task(s), sent {$notificationsSent} notification(s).");

        return self::SUCCESS;
    }
}
