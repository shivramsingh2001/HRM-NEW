<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * "Create … request" for an employee (Loan, Overtime, Expense, Leave,
 * Regularization admin pages). Admin / HR only (route `role:admin,hr`); the
 * request is saved already approved by the caller — the same writes the
 * module's own approval makes — and logged: `created_by` on the row,
 * an audit_logs entry `{module}.created_on_behalf` (shows on Employee 360 →
 * Activity), and the employee gets the usual "approved" notification.
 */
trait RaisesOnBehalf
{
    /** The employee picked in the form: active, same company. */
    protected function onBehalfEmployee(Request $request): User
    {
        $actor = Auth::user();
        abort_unless(in_array($actor->role, ['admin', 'hr'], true), 403, 'Only admin or HR can raise a request for an employee.');

        $id = $request->input('user_id');
        $employee = ctype_digit((string) $id)
            ? User::withoutGlobalScopes()->where('tenant_id', $actor->tenant_id)->where('status', 1)->find((int) $id)
            : null;

        if (! $employee) {
            throw ValidationException::withMessages(['user_id' => 'Select an active employee.']);
        }

        return $employee;
    }

    protected function logOnBehalf(string $action, string $entityType, int $entityId, User $employee, array $details = []): void
    {
        app(AuditLogger::class)->record('tenant_user', Auth::id(), (int) $employee->tenant_id, $action, $entityType, $entityId, [], [
            'on_behalf_of' => $employee->id,
            'employee_name' => $employee->name,
            'raised_by' => Auth::user()->name,
            'raised_by_role' => Auth::user()->role,
        ] + $details);
    }

    /** Employees for the form's picker: active, same company. */
    protected function onBehalfEmployees()
    {
        return User::withoutGlobalScopes()->where('tenant_id', Auth::user()->tenant_id)->where('status', 1)
            ->orderBy('name')->get(['id', 'name', 'employee_id']);
    }
}
