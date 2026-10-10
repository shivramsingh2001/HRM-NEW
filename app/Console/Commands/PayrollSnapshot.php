<?php

namespace App\Console\Commands;

use App\Http\Controllers\Payroll\MonthlyPayrollController;
use App\Models\MonthlyPayroll;
use App\Models\PayrollComponent;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Expense\ExpenseReimbursementPayrollService;
use App\Services\Payroll\LoanDeductionService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Golden-master snapshot of payroll results — the safety net for refactoring
 * the payroll code (code-quality plan, Phase 0). READ-ONLY: every employee is
 * run inside a transaction that is always rolled back.
 *
 * For every employee of the chosen companies and months it records, per engine
 * (legacy fixed-column and dynamic):
 *  - the generated payslip (every monthly_payrolls column except ids /
 *    timestamps), its payroll_components lines and the loan instalments it
 *    collected;
 *  - the Edit Payroll screen's computed numbers (loan / advance due, pending
 *    overtime);
 *  - a save through Edit Payroll (legacy: the payslip's own values with HRA
 *    +100 and TDS 50; dynamic: one present day less) and the dynamic live
 *    preview — result, redirect target and flash message.
 *
 *   php artisan payroll:snapshot --label=before
 *   … refactor …
 *   php artisan payroll:snapshot --label=after
 *   php artisan payroll:snapshot-compare before after     (no output = identical)
 */
class PayrollSnapshot extends Command
{
    protected $signature = 'payroll:snapshot
        {--label=snapshot : File name under storage/app/payroll-snapshots}
        {--tenants=7,8,10 : Company ids}
        {--months= : Payroll months Y-m, comma separated (default: the 3 latest months with payslips)}
        {--limit=50 : Max employees per company and engine}';

    protected $description = 'Read-only golden-master snapshot of payroll generation + edit results (both engines)';

    public function handle(): int
    {
        $tenants = array_filter(array_map('intval', explode(',', (string) $this->option('tenants'))));
        $months = $this->option('months')
            ? array_filter(explode(',', (string) $this->option('months')))
            : DB::table('monthly_payrolls')->distinct()->orderByDesc('payroll_month')->limit(3)->pluck('payroll_month')->all();
        $limit = (int) $this->option('limit');

        $out = ['months' => $months, 'tenants' => $tenants, 'results' => []];
        $count = 0;

        foreach ($tenants as $tenantId) {
            $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
            $admin = User::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('role', 'admin')->where('status', 1)->orderBy('id')->first();
            if (! $tenant || ! $admin) {
                $this->warn("Company {$tenantId}: no company or no active admin — skipped.");

                continue;
            }
            $this->bindContext($tenant, $admin);

            $engines = [
                'legacy' => User::where('tenant_id', $tenantId)->where('status', 1)
                    ->whereHas('userPayrolls', fn ($q) => $q->where('is_current', true))->orderBy('id')->limit($limit)->get(),
                'dynamic' => User::where('tenant_id', $tenantId)->where('status', 1)
                    ->whereHas('currentDynamicPayrollStructure')->orderBy('id')->limit($limit)->get(),
            ];

            foreach ($months as $month) {
                foreach ($engines as $engine => $employees) {
                    foreach ($employees as $employee) {
                        $out['results']["{$tenantId}|{$month}|{$engine}|{$employee->id}"] = $this->runOne($engine, $employee, $month, $tenant, $admin);
                        $count++;
                    }
                }
            }
        }

        $dir = storage_path('app/payroll-snapshots');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $file = $dir.DIRECTORY_SEPARATOR.$this->option('label').'.json';
        file_put_contents($file, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->info("Snapshot of {$count} employee-month-engine runs written to {$file}");

        return self::SUCCESS;
    }

    private function runOne(string $engine, User $employee, string $month, Tenant $tenant, User $admin): array
    {
        $this->bindContext($tenant, $admin);
        $controller = app(MonthlyPayrollController::class);
        $record = [];

        DB::beginTransaction();
        try {
            // Same clean-up store()'s force_reprocess does, so every run starts from no payslip.
            foreach (MonthlyPayroll::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('user_id', $employee->id)->where('payroll_month', $month)->get() as $old) {
                app(LoanDeductionService::class)->revokeForPayroll($old->id, $old->tenant_id);
                app(ExpenseReimbursementPayrollService::class)->revokeForPayroll($old->id, $old->tenant_id);
                PayrollComponent::where('monthly_payroll_id', $old->id)->delete();
                $old->delete();
            }

            $date = \Carbon\Carbon::createFromFormat('Y-m', $month);
            // What Process Payroll runs for one employee (was MonthlyPayrollController's private
            // processEmployeeMonthlyPayroll / …Dynamic until the Phase 1 refactor).
            $runner = app(\App\Services\Payroll\PayrollRunService::class);
            $slip = $engine === 'legacy'
                ? $runner->processLegacy($employee, (int) $tenant->id, $month,
                    $date->copy()->startOfMonth()->format('Y-m-d'), $date->copy()->endOfMonth()->format('Y-m-d'), true, true)
                : $runner->processDynamic($employee, (int) $tenant->id, $month, null, true);
            $record['generated'] = $this->capture($slip);

            // Edit Payroll screen numbers.
            $view = $controller->edit($slip->id);
            if ($view instanceof View) {
                $record['edit'] = Arr::only($view->getData(), [
                    'loanDueTotal', 'advanceDueTotal', 'pendingOvertimeHours', 'pendingOvertimeRate', 'pendingOvertimeAmount',
                    'overtimeRateMultiplier', 'overtimeFixedRate', 'isDynamic',
                ]);
            } else {
                $record['edit'] = $this->describeResponse($view, $slip->id);
            }

            if ($engine === 'dynamic') {
                $previewData = [
                    'present_days' => max(0, (float) $slip->present_days - 1), 'paid_leaves' => $slip->paid_leaves,
                    'week_offs' => $slip->week_offs, 'holidays' => $slip->holidays, 'loan_deduction_enabled' => 1,
                ];
                $preview = $controller->recalculatePreview($this->request('POST', $previewData), $slip->id);
                $record['preview'] = json_decode($preview->getContent(), true);
                $resp = $controller->update($this->request('PUT', $previewData + ['remarks' => 'snapshot edit']), $slip->id);
            } else {
                $resp = $controller->update($this->request('PUT', $this->legacyEditPayload($slip)), $slip->id);
            }
            $record['update_response'] = $this->describeResponse($resp, $slip->id);
            $record['updated'] = $this->capture($slip->fresh());
        } catch (\Throwable $e) {
            $record['error'] = get_class($e).': '.$e->getMessage();
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }

        return $record;
    }

    /** The payslip's own values, HRA +100 and TDS 50 — what an HR edit typically looks like. */
    private function legacyEditPayload(MonthlyPayroll $s): array
    {
        return [
            'payroll_month' => $s->payroll_month, 'present_days' => $s->present_days, 'paid_leaves' => $s->paid_leaves,
            'overtime_hours' => $s->overtime_hours, 'basic_salary' => $s->basic_salary, 'hra' => (float) $s->hra + 100,
            'conveyence' => $s->conveyence, 'medical_allowance' => $s->medical_allowance, 'children_allowance' => $s->children_allowance,
            'post_allowance' => $s->post_allowance, 'leave_travel_allowance' => $s->leave_travel_allowance,
            'monthly_incentive' => $s->monthly_incentive, 'special_allowance' => $s->special_allowance,
            'overtime_amount' => $s->overtime_amount, 'shift_allowance_amount' => $s->shift_allowance_amount,
            'provident_fund' => $s->provident_fund, 'esi' => $s->esi, 'professional_tax' => $s->professional_tax, 'tds' => 50,
            'loan_deduction' => $s->loan_deduction, 'loan_deduction_enabled' => 1,
            'salary_advance_deduction' => $s->salary_advance_deduction, 'salary_advance_deduction_enabled' => 1,
            'other_deductions' => $s->other_deductions, 'confirm_negative_net_payable' => 1, 'remarks' => 'snapshot edit',
        ];
    }

    private function capture(?MonthlyPayroll $slip): ?array
    {
        if (! $slip) {
            return null;
        }
        $slip = MonthlyPayroll::withoutGlobalScopes()->find($slip->id);
        $row = Arr::except($slip->getAttributes(), ['id', 'created_at', 'updated_at', 'processing_date', 'processed_by', 'payroll_run_id']);
        ksort($row);

        return [
            'row' => $row,
            'components' => DB::table('payroll_components')->where('monthly_payroll_id', $slip->id)->orderBy('id')->get()
                ->map(fn ($c) => Arr::except((array) $c, ['id', 'monthly_payroll_id', 'created_at', 'updated_at']))->all(),
            'loan_allocations' => DB::table('loan_repayment_allocations')->where('monthly_payroll_id', $slip->id)->orderBy('id')
                ->get(['loan_repayment_id', 'amount'])->map(fn ($a) => (array) $a)->all(),
        ];
    }

    private function describeResponse($resp, int $slipId): array
    {
        $session = app('session.store');
        $out = ['type' => is_object($resp) ? class_basename($resp) : gettype($resp)];
        if ($resp instanceof \Illuminate\Http\RedirectResponse) {
            // The payslip id is an auto-increment that differs per run.
            $out['target'] = preg_replace('#/'.$slipId.'(?=/|$)#', '/{id}', $resp->getTargetUrl());
        }
        $out['success'] = $session->get('success');
        $out['error'] = $session->get('error');
        $out['errors'] = $session->has('errors') ? $session->get('errors')->all() : [];

        return $out;
    }

    private function request(string $method, array $data): Request
    {
        $req = Request::create('/payroll-snapshot', $method, $data);
        $session = app('session.store');
        $session->flush();
        $req->setLaravelSession($session);
        app()->instance('request', $req);
        app('url')->setRequest($req);

        return $req;
    }

    private function bindContext(Tenant $tenant, User $admin): void
    {
        app()->instance('current_tenant', $tenant);
        auth()->guard('web')->setUser($admin);
        auth()->shouldUse('web');
        $this->request('GET', []);
    }
}
