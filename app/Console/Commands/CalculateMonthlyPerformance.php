<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\EmployeeKpiScore;
use App\Services\Performance\PerformanceCalculationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CalculateMonthlyPerformance extends Command
{
    protected $signature = 'performance:calculate 
                            {--month= : Month in Y-m format (default: previous month)} 
                            {--user= : Specific user ID}
                            {--force : Force recalculation even if already calculated}';
    
    protected $description = 'Calculate monthly performance scores for all employees based on attendance, tasks, and regularization rules';
    
    protected $calculationService;
    
    public function __construct(PerformanceCalculationService $calculationService)
    {
        parent::__construct();
        $this->calculationService = $calculationService;
    }
    
    public function handle()
    {
        $month = $this->option('month') ?: now()->subMonth()->format('Y-m');
        $userId = $this->option('user');
        $force = $this->option('force');
        
        $this->info('📊 Starting Monthly Performance Calculation');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("Period: {$month}");
        $this->info("Force recalc: " . ($force ? 'Yes' : 'No'));
        $this->line('');
        
        $reportingMonth = $month . '-01';
        
        if ($userId) {
            $user = User::find($userId);
            if (!$user) {
                $this->error("❌ User not found!");
                return 1;
            }
            $this->calculateForUser($user, $month, $reportingMonth, $force);
        } else {
            $users = User::where('status', '1')
                ->whereIn('role', ['employee','manager'])
                ->get();
            
            if ($users->isEmpty()) {
                $this->warn("No employees found to calculate performance.");
                return 0;
            }
            
            $this->info("Found " . $users->count() . " employees to process");
            $bar = $this->output->createProgressBar($users->count());
            $bar->start();
            
            $successCount = 0;
            $failureCount = 0;
            $failures = [];
            
            foreach ($users as $user) {
                try {
                    $this->calculateForUser($user, $month, $reportingMonth, $force);
                    $successCount++;
                } catch (\Exception $e) {
                    $failureCount++;
                    $failures[] = [
                        'user' => $user->name,
                        'email' => $user->email,
                        'error' => $e->getMessage()
                    ];
                    Log::error("Performance calculation failed for user {$user->id}: " . $e->getMessage());
                }
                $bar->advance();
            }
            
            $bar->finish();
            $this->newLine(2);
            
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info("📈 Calculation Summary");
            $this->line("✅ Successful: {$successCount}");
            $this->line("❌ Failed: {$failureCount}");
            
            if ($failureCount > 0) {
                $this->newLine();
                $this->warn("Failed calculations:");
                foreach ($failures as $failure) {
                    $this->error("  - {$failure['user']} ({$failure['email']}): {$failure['error']}");
                }
            }
        }
        
        $this->newLine();
        $this->info("✅ Performance calculation completed!");
        
        return 0;
    }
    
    private function calculateForUser(User $user, string $month, string $reportingMonth, bool $force)
    {
        // Check if already calculated
        if (!$force) {
            $existing = EmployeeKpiScore::where('user_id', $user->id)
                ->where('reporting_month', $reportingMonth)
                ->first();
            
            if ($existing && $existing->calculated_at) {
                $this->line("\n⏭️  Skipping {$user->name} - already calculated on {$existing->calculated_at}");
                return;
            }
        }
        
        $this->line("\n🔄 Calculating for: {$user->name} ({$user->email})");
        
        // Calculate scores
        $scores = $this->calculationService->calculateForUser($user, $month);
        
        // Display scores
        $this->table(
            ['Metric', 'Score', 'Grade'],
            [
                ['Attendance Score', "{$scores['attendance_score']}%", ''],
                ['Task Completion', "{$scores['task_completion_score']}%", ''],
                ['Deadline Met', "{$scores['deadline_met_score']}%", ''],
                ['Regularization', "{$scores['regularization_score']}%", ''],
                ['Manager Rating', $scores['manager_rating_score'] ? "{$scores['manager_rating_score']}%" : 'N/A', ''],
                ['━━━━━━━━━━━━━━', '━━━━━━━━━━━━━━', '━━━━━━'],
                ['Overall Score', "{$scores['overall_score']}%", $scores['grade']],
            ]
        );
        
        // Display breakdown
        $this->line("   📋 Details:");
        $this->line("   ├─ Tasks: {$scores['task_completion_score']}% completion, {$scores['deadline_met_score']}% on-time");
        $this->line("   ├─ Attendance: {$scores['present_days']} present, {$scores['absent_days']} absent, {$scores['late_days']} late");
        $this->line("   ├─ Leaves: {$scores['paid_leaves']} paid, {$scores['unpaid_leaves']} unpaid");
        $this->line("   └─ Regularization: {$scores['regularization_count']} requests ({$scores['approved_regularization_count']} approved, {$scores['rejected_regularization_count']} rejected) - Score: {$scores['regularization_score']}%");
        
        // Save to database
        EmployeeKpiScore::updateOrCreate(
            [
                'user_id' => $user->id,
                'reporting_month' => $reportingMonth,
            ],
            [
                'tenant_id' => $user->tenant_id,
                
                // Core scores
                'attendance_score' => $scores['attendance_score'],
                'task_completion_score' => $scores['task_completion_score'],
                'deadline_met_score' => $scores['deadline_met_score'],
                'regularization_score' => $scores['regularization_score'],
                'manager_rating_score' => $scores['manager_rating_score'],
                
                // Overall
                'overall_score' => $scores['overall_score'],
                'grade' => $scores['grade'],
                
                // Attendance breakdown
                'present_days' => $scores['present_days'],
                'absent_days' => $scores['absent_days'],
                'half_days' => $scores['half_days'],
                'late_days' => $scores['late_days'],
                'early_departure_days' => $scores['early_departure_days'],
                'paid_leaves' => $scores['paid_leaves'],
                'unpaid_leaves' => $scores['unpaid_leaves'],
                'holidays' => $scores['holidays'],
                'weekoffs' => $scores['weekoffs'],
                
                // Task metrics
                'assigned_tasks' => $scores['assigned_tasks'],
                'completed_tasks' => $scores['completed_tasks'],
                'on_time_completed_tasks' => $scores['on_time_completed_tasks'],
                
                // Regularization metrics
                'regularization_count' => $scores['regularization_count'],
                'approved_regularization_count' => $scores['approved_regularization_count'],
                'rejected_regularization_count' => $scores['rejected_regularization_count'],
                'pending_regularization_count' => $scores['pending_regularization_count'],
                
                // Late tracking
                'late_count' => $scores['late_count'],
                'total_late_minutes' => $scores['total_late_minutes'],
                'late_penalty' => $scores['late_penalty'],
                
                // Overtime
                'overtime_hours' => $scores['overtime_hours'],
                'payroll_cost' => $scores['payroll_cost'],
                
                // Manager rating details
                'manager_rating_raw' => $scores['manager_rating_raw'],
                'manager_feedback' => $scores['manager_feedback'],
                'manager_rated_by' => $scores['manager_rated_by'],
                'manager_rated_at' => $scores['manager_rated_at'],
                
                // JSON details
                'attendance_details' => $scores['attendance_details'],
                'leave_details' => $scores['leave_details'],
                'task_details' => $scores['task_details'],
                
                // Status
                'status' => 'calculated',
                'calculated_at' => now(),
            ]
        );
        
        $this->line("   ✅ Saved successfully - Regularization Score: {$scores['regularization_score']}%");
    }
}