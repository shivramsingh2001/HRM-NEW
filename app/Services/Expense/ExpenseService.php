<?php

namespace App\Services\Expense;

use App\Exceptions\ExpenseException;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\ExpenseStatusHistory;
use App\Models\ExpenseType;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * The expense lifecycle — submit / update / receipts / delete / withdraw / approve / reject — as ONE
 * implementation shared by the web panel and the mobile API.
 *
 * Concurrency: every state change locks the expense row FOR UPDATE and re-checks status AFTER the
 * lock (a double approval can't double-deduct; an edit can't interleave with an approval). Balance
 * changes are delegated to ExpenseLedgerService (locks the employee's balance row) and budget checks
 * to ExpenseBudgetService (locks the applicable budget rows).
 *
 * Authorization (does the actor's scope cover this employee?) is deliberately NOT done here for
 * approvals — the calling controller does it with scopeCoversOwner(). Owner-only actions (edit,
 * delete, withdraw, receipts) are enforced here. Notifications are sent by the controller AFTER the
 * transaction commits.
 *
 * Receipts: files stored during a transaction that later rolls back are deleted again; a replaced or
 * removed receipt is only deleted after the commit. Soft-deleted claims KEEP their receipts (audit);
 * `php artisan expense:purge-deleted` removes them after a retention period.
 */
class ExpenseService
{
    public function __construct(
        private AuditLogger $audit,
        private ExpenseLedgerService $ledger,
        private ExpenseAttachmentService $attachments,
        private ExpensePolicyService $policy,
        private ExpenseBudgetService $budgets,
    ) {
    }

    // =================================================================
    //  Submit / edit / receipts
    // =================================================================

    /**
     * Submit a new (pending) expense. The company's per-category rules (limit, receipt, back-dating)
     * are enforced here; a same-person/type/amount/date claim is FLAGGED (possible_duplicate_of), not blocked.
     *
     * @param  array{expense_type:int|string, amount:mixed, date:string, project_id?:mixed, requirement_type:string, description?:?string}  $data
     * @param  UploadedFile|array<int, UploadedFile>|null  $receipts  the first becomes the primary receipt
     */
    public function submit(User $actor, array $data, UploadedFile|array|null $receipts = null, string $source = 'web', ?User $raisedBy = null): Expense
    {
        $files = $this->files($receipts);
        $stored = [];

        try {
            return DB::transaction(function () use ($actor, $data, $files, $source, $raisedBy, &$stored) {
                $this->policy->check($actor, $data, count($files));

                // This employee's monthly expense limit (Employee 360 → Policies).
                if ($limit = app(\App\Services\RequestLimitService::class)->expenseRefusal(
                    (int) $actor->tenant_id, (int) $actor->id, (string) $data['date'], (float) $data['amount'], (string) $data['requirement_type'], null
                )) {
                    throw new ExpenseException($limit, 422);
                }
                $duplicate = $this->policy->findDuplicate($actor, $data);

                foreach ($files as $file) {
                    $stored[] = $this->attachments->store($file, (int) $actor->tenant_id);
                }

                $expense = Expense::create([
                    'tenant_id' => $actor->tenant_id,
                    'user_id' => $actor->id,
                    'expense_type' => $data['expense_type'],
                    'amount' => $data['amount'],
                    'date' => $data['date'],
                    'project_id' => $data['project_id'] ?? null,
                    'requirement_type' => $data['requirement_type'],
                    'description' => $data['description'] ?? null,
                    'file' => $stored[0] ?? null,
                    'possible_duplicate_of' => $duplicate,
                    'status' => Expense::STATUS_PENDING,
                    'created_by' => $raisedBy?->id,
                ]);

                foreach (array_slice($files, 1, null, true) as $i => $file) {
                    $this->attachments->recordAttachment($expense, $stored[$i], $file, ($raisedBy ?? $actor)->id);
                }

                ExpenseStatusHistory::create([
                    'expense_id' => $expense->id,
                    'status' => Expense::STATUS_PENDING,
                    'changed_by' => ($raisedBy ?? $actor)->id,
                    'remarks' => ($raisedBy ? "Expense raised by {$raisedBy->name} on behalf of {$actor->name}" : 'Expense submitted')
                        . ($duplicate ? ' (possible duplicate of an earlier claim)' : ''),
                ]);

                $this->audit->record('tenant_user', ($raisedBy ?? $actor)->id, (int) $actor->tenant_id,
                    $raisedBy ? 'expenses.created_on_behalf' : 'expenses.submitted', 'Expense', (int) $expense->id, [], [
                    'amount' => (string) $expense->amount,
                    'requirement_type' => $expense->requirement_type,
                    'receipts' => count($files),
                    'possible_duplicate_of' => $duplicate,
                    'source' => $source,
                ] + ($raisedBy ? ['on_behalf_of' => $actor->id, 'employee_name' => $actor->name, 'raised_by' => $raisedBy->name, 'raised_by_role' => $raisedBy->role] : []));

                return $expense;
            });
        } catch (\Throwable $e) {
            foreach ($stored as $path) {
                $this->attachments->delete($path); // the row never committed — don't orphan the files
            }

            throw $e;
        }
    }

    /**
     * Admin / HR raises an expense for an employee (Expenses → "Add expense") and
     * approves it in the same step: submit() with the employee as owner (their
     * category rules, receipts and monthly limit apply) and $actor as the raiser,
     * then decide() as $actor — the same ledger / budget / history writes as a
     * normal approval. All or nothing: if the approval fails (e.g. a settlement
     * larger than the advance balance without $coverShortfall) nothing is saved.
     *
     * @param  UploadedFile|array<int, UploadedFile>|null  $receipts
     * @return array{expense: Expense, message: string, warnings: string[], child: ?Expense}
     */
    public function submitOnBehalf(User $actor, User $employee, array $data, UploadedFile|array|null $receipts = null, bool $coverShortfall = false): array
    {
        $paths = [];

        try {
            return DB::transaction(function () use ($actor, $employee, $data, $receipts, $coverShortfall, &$paths) {
                $expense = $this->submit($employee, $data, $receipts, 'on_behalf', $actor);
                $paths = array_values(array_filter(array_merge(
                    [$expense->file],
                    ExpenseAttachment::where('expense_id', $expense->id)->pluck('file_path')->all()
                )));

                return $this->decide((int) $expense->id, $actor, Expense::STATUS_APPROVED,
                    $data['approval_remarks'] ?? "Raised and approved by {$actor->name}", $coverShortfall);
            }, 1);
        } catch (\Throwable $e) {
            foreach ($paths as $path) {
                $this->attachments->delete($path); // rolled back — don't orphan the receipts
            }

            throw $e;
        }
    }

    /**
     * Edit an expense — owner only, and only while it is still pending. New files are ADDED to the
     * existing receipts (use removeReceipt() to drop one).
     *
     * @param  UploadedFile|array<int, UploadedFile>|null  $newReceipts
     */
    public function update(User $actor, int $expenseId, array $data, UploadedFile|array|null $newReceipts = null): Expense
    {
        $files = $this->files($newReceipts);
        $stored = [];

        try {
            return DB::transaction(function () use ($actor, $expenseId, $data, $files, &$stored) {
                $expense = $this->lockOwnPending($actor, $expenseId, 'updated');

                $existing = ($expense->file ? 1 : 0) + ExpenseAttachment::where('expense_id', $expense->id)->count();
                if ($existing + count($files) > ExpenseAttachmentService::MAX_FILES) {
                    throw new ExpenseException('A claim can have at most ' . ExpenseAttachmentService::MAX_FILES . ' receipts.', 422);
                }

                $this->policy->check($actor, $data, $existing + count($files));

                // This employee's monthly expense limit (Employee 360 → Policies).
                if ($limit = app(\App\Services\RequestLimitService::class)->expenseRefusal(
                    (int) $actor->tenant_id, (int) $actor->id, (string) $data['date'], (float) $data['amount'], (string) $data['requirement_type'], (int) $expense->id
                )) {
                    throw new ExpenseException($limit, 422);
                }
                $duplicate = $this->policy->findDuplicate($actor, $data, (int) $expense->id);

                foreach ($files as $file) {
                    $stored[] = $this->attachments->store($file, (int) $actor->tenant_id);
                }
                foreach ($files as $i => $file) {
                    if (! $expense->file) {
                        $expense->file = $stored[$i];            // no primary yet: the first new file becomes it
                    } else {
                        $this->attachments->recordAttachment($expense, $stored[$i], $file, $actor->id);
                    }
                }

                $expense->expense_type = $data['expense_type'];
                $expense->amount = $data['amount'];
                $expense->date = $data['date'];
                $expense->project_id = $data['project_id'] ?? null;
                $expense->requirement_type = $data['requirement_type'];
                $expense->description = $data['description'] ?? null;
                $expense->possible_duplicate_of = $duplicate;
                $expense->save();

                $this->audit->record('tenant_user', $actor->id, (int) $expense->tenant_id, 'expenses.updated',
                    'Expense', (int) $expense->id, [], ['amount' => (string) $expense->amount, 'receipts_added' => count($files)]);

                return $expense;
            });
        } catch (\Throwable $e) {
            foreach ($stored as $path) {
                $this->attachments->delete($path);
            }

            throw $e;
        }
    }

    /**
     * Remove one receipt from a still-pending claim. `$attachmentId = null` removes the primary receipt
     * (the next extra one, if any, is promoted). Refused if the category requires a receipt at this amount.
     */
    public function removeReceipt(User $actor, int $expenseId, ?int $attachmentId): Expense
    {
        $fileToDelete = null;

        $expense = DB::transaction(function () use ($actor, $expenseId, $attachmentId, &$fileToDelete) {
            $expense = $this->lockOwnPending($actor, $expenseId, 'changed');

            $extras = ExpenseAttachment::where('expense_id', $expense->id)->orderBy('id')->get();
            $before = ($expense->file ? 1 : 0) + $extras->count();

            // A category may insist on a receipt: refuse to remove the last one.
            $this->policy->check($actor, [
                'expense_type' => $expense->expense_type, 'amount' => $expense->amount,
                'date' => $expense->date, 'requirement_type' => $expense->requirement_type,
            ], max(0, $before - 1));

            if ($attachmentId === null) {
                if (! $expense->file) {
                    throw new ExpenseException('This claim has no primary receipt to remove.', 404);
                }

                $promote = $extras->first();
                $fileToDelete = $expense->file;

                if ($promote) {
                    $expense->file = $promote->file_path;   // its FILE is now the primary; only the row goes
                    $promote->delete();
                } else {
                    $expense->file = null;
                }
                $expense->save();
            } else {
                $attachment = $extras->firstWhere('id', $attachmentId);
                if (! $attachment) {
                    throw new ExpenseException('Receipt not found on this claim.', 404);
                }

                $fileToDelete = $attachment->file_path;
                $attachment->delete();
            }

            $this->audit->record('tenant_user', $actor->id, (int) $expense->tenant_id, 'expenses.receipt_removed', 'Expense', (int) $expense->id, [], ['attachment_id' => $attachmentId]);

            return $expense;
        });

        $this->attachments->delete($fileToDelete); // only after the commit

        return $expense;
    }

    /**
     * Delete a claim nobody has acted on yet — owner only, and only while still pending. It is SOFT
     * deleted (the row and its receipts stay for audit; it disappears from every list).
     */
    public function delete(User $actor, int $expenseId): void
    {
        DB::transaction(function () use ($actor, $expenseId) {
            $expense = $this->lockOwnPending($actor, $expenseId, 'deleted');

            $this->audit->record('tenant_user', $actor->id, (int) $expense->tenant_id, 'expenses.deleted',
                'Expense', (int) $expense->id, ['amount' => (string) $expense->amount, 'status' => $expense->status], []);

            $expense->delete();
        });
    }

    /**
     * The employee pulls their OWN claim back. Unlike delete, this keeps a visible record (status
     * "cancelled" + reason) and also works for an APPROVED advance/reimbursement that has not been
     * paid at all — "I no longer need this advance". It cannot be used once any money moved, for an
     * approved settlement (its balance was already deducted), or for a reimbursement that was split
     * off a settlement.
     */
    public function withdraw(User $actor, int $expenseId, string $reason): Expense
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            throw new ExpenseException('Please give a reason (at least 3 characters).', 422);
        }

        return DB::transaction(function () use ($actor, $expenseId, $reason) {
            $expense = Expense::whereKey($expenseId)->lockForUpdate()->first();

            if (! $expense) {
                throw new ExpenseException('Expense not found.', 404);
            }
            if ((int) $expense->user_id !== (int) $actor->id) {
                throw new ExpenseException('You can only withdraw your own claims.', 403);
            }
            if ($expense->parent_expense_id) {
                throw new ExpenseException('This reimbursement was created from a settlement and cannot be withdrawn on its own.', 422);
            }

            if ($expense->isRoutedThroughPayroll()) {
                throw new ExpenseException('This reimbursement has been sent to payroll. Ask finance to release it before withdrawing.', 409);
            }

            $wasApproved = $expense->status === Expense::STATUS_APPROVED;
            $paid = Money::toCents($expense->payments()->sum('amount'));   // posted payments only

            $eligible = $expense->status === Expense::STATUS_PENDING
                || ($wasApproved && $expense->requirement_type !== Expense::TYPE_SETTLEMENT && $paid === 0);

            if (! $eligible) {
                throw new ExpenseException(
                    'Only pending claims, or approved advances/reimbursements that have not been paid at all, can be withdrawn.', 400
                );
            }

            $expense->update([
                'status' => Expense::STATUS_CANCELLED,
                'withdrawn_at' => now(),
                'withdrawn_reason' => mb_substr($reason, 0, 500),
            ]);

            ExpenseStatusHistory::create([
                'expense_id' => $expense->id,
                'status' => Expense::STATUS_CANCELLED,
                'changed_by' => $actor->id,
                'remarks' => 'Withdrawn by the employee: ' . $reason,
            ]);

            if ($wasApproved) {
                $this->budgets->syncUsage($expense);   // an approved reimbursement stops counting as spend
            }

            $this->audit->record('tenant_user', $actor->id, (int) $expense->tenant_id, 'expenses.withdrawn', 'Expense', (int) $expense->id,
                ['status' => $wasApproved ? Expense::STATUS_APPROVED : Expense::STATUS_PENDING], ['status' => Expense::STATUS_CANCELLED, 'reason' => $reason]);

            return $expense;
        });
    }

    // =================================================================
    //  Direct payment
    // =================================================================

    /**
     * "Direct payment": finance hands an employee money that was never requested.
     * Modelled as a real, already-approved advance (so it flows through exactly the same payment +
     * ledger + history path as any other advance), flagged with is_direct_payment; the project is left
     * empty. Must be called inside the caller's transaction (it is followed by the payment).
     *
     * @throws ExpenseException when the tenant has no active expense type to file it under
     */
    public function createAdvanceOnBehalf(User $actor, User $employee, int $cents, string $date, ?int $expenseTypeId = null, ?string $remarks = null): Expense
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('createAdvanceOnBehalf must run inside the payment transaction.');
        }

        $typeQuery = ExpenseType::withoutGlobalScopes()->where('tenant_id', $employee->tenant_id)->where('status', 1);
        $typeId = $expenseTypeId
            ? (clone $typeQuery)->whereKey($expenseTypeId)->value('id')
            : (clone $typeQuery)->orderBy('id')->value('id');

        if (! $typeId) {
            throw new ExpenseException('This company has no active expense type to file a direct payment under. Create one under Expense Types first.', 422);
        }

        $expense = Expense::create([
            'tenant_id' => $employee->tenant_id,
            'user_id' => $employee->id,
            'expense_type' => $typeId,
            'project_id' => null,
            'requirement_type' => Expense::TYPE_ADVANCE,
            'amount' => Money::fromCents($cents),
            'date' => $date,
            'description' => 'Direct payment: ' . ($remarks ?: 'No remarks'),
            'is_direct_payment' => true,
            'status' => Expense::STATUS_APPROVED,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'approval_remarks' => 'Created and approved by the payer as a direct payment',
        ]);

        ExpenseStatusHistory::create([
            'expense_id' => $expense->id,
            'status' => Expense::STATUS_APPROVED,
            'changed_by' => $actor->id,
            'remarks' => 'Advance created on behalf of the employee (direct payment)',
        ]);

        $this->audit->record('tenant_user', $actor->id, (int) $employee->tenant_id, 'expenses.direct_advance_created',
            'Expense', (int) $expense->id, [], ['amount' => (string) $expense->amount, 'employee_id' => $employee->id]);

        return $expense;
    }

    // =================================================================
    //  Approve / reject
    // =================================================================

    /**
     * Approve / reject a pending expense.
     *
     * @param  'approved'|'cancelled'  $status
     * @param  bool  $coverShortfall  for a SETTLEMENT larger than the employee's advance balance: take what the
     *                                advance covers and turn the rest into a payable reimbursement instead of
     *                                refusing. Opt-in per approval — the default behaviour is unchanged.
     * @return array{expense: Expense, message: string, warnings: string[], child: ?Expense}
     *
     * @throws InsufficientBalanceException  settlement exceeds the balance and $coverShortfall is false
     * @throws ExpenseException              any other business-rule failure (transaction rolled back)
     */
    public function decide(int $expenseId, User $actor, string $status, ?string $remarks, bool $coverShortfall = false): array
    {
        return DB::transaction(function () use ($expenseId, $actor, $status, $remarks, $coverShortfall) {
            $expense = Expense::whereKey($expenseId)->lockForUpdate()->first();

            if (! $expense) {
                throw new ExpenseException('Expense not found.', 404);
            }
            if ($expense->status !== Expense::STATUS_PENDING) {
                throw new ExpenseException('This expense has already been processed.', 400);
            }
            if (Money::toCents($expense->amount) <= 0) {
                throw new ExpenseException('This expense has an invalid amount and cannot be approved.', 422);
            }

            $warnings = [];
            $child = null;

            if ($status === Expense::STATUS_CANCELLED) {
                $expense->update([
                    'status' => Expense::STATUS_CANCELLED,
                    'rejected_by' => $actor->id,
                    'rejected_at' => now(),
                    'rejection_reason' => $remarks,
                ]);
                $message = 'Expense rejected successfully';
            } else {
                [$message, $warnings, $child] = $this->approve($expense, $actor, $remarks, $coverShortfall);
            }

            ExpenseStatusHistory::create([
                'expense_id' => $expense->id,
                'status' => $status,
                'changed_by' => $actor->id,
                'remarks' => $remarks,
            ]);

            $this->audit->record(
                'tenant_user', $actor->id, (int) $expense->tenant_id,
                $status === Expense::STATUS_CANCELLED ? 'expenses.rejected' : 'expenses.approved',
                'Expense', (int) $expense->id,
                ['status' => Expense::STATUS_PENDING],
                ['status' => $status, 'amount' => (string) $expense->amount, 'requirement_type' => $expense->requirement_type]
                    + ($warnings ? ['budget_warnings' => $warnings] : [])
                    + ($child ? ['shortfall_reimbursement_id' => $child->id] : []),
            );

            if ($warnings) {
                $message .= ' Note: ' . implode(' ', $warnings);
            }

            return ['expense' => $expense, 'message' => $message, 'warnings' => $warnings, 'child' => $child];
        }, 3);
    }

    /** @return array{0: string, 1: string[], 2: ?Expense} [message, budget warnings, shortfall reimbursement] */
    private function approve(Expense $expense, User $actor, ?string $remarks, bool $coverShortfall): array
    {
        $child = null;

        // Budget first (locks the applicable budget rows so concurrent approvals serialise on them).
        $budget = $this->budgets->evaluate($expense, lock: true);
        if ($budget['blocked']) {
            throw new ExpenseException('Cannot approve — ' . implode(' ', $budget['warnings']), 422);
        }

        if ($expense->requirement_type === Expense::TYPE_SETTLEMENT) {
            $requested = Money::toCents($expense->amount);
            $available = $this->ledger->availableCents($expense);

            if ($available >= $requested) {
                $this->ledger->debitSettlement($expense, $remarks);
            } elseif (! $coverShortfall) {
                throw new InsufficientBalanceException($available, $requested);
            } else {
                if ($available > 0) {
                    $this->ledger->debitSettlement($expense, $remarks, $available);
                }
                $child = $this->createShortfallReimbursement($expense, $actor, $requested - $available, $available);
            }
        }

        $expense->update([
            'status' => Expense::STATUS_APPROVED,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'approval_remarks' => $remarks,
        ]);

        $this->budgets->syncUsage($expense);

        $message = match ($expense->requirement_type) {
            Expense::TYPE_SETTLEMENT => $child
                ? 'Settlement approved: ₹' . Money::format(Money::toCents($expense->amount) - Money::toCents($child->amount))
                    . ' deducted from the advance balance and ₹' . Money::format(Money::toCents($child->amount))
                    . " turned into reimbursement {$child->expense_number}, now awaiting payment."
                : 'Settlement approved and balance deducted successfully',
            Expense::TYPE_ADVANCE => 'Advance approved. Please create payment to credit balance.',
            Expense::TYPE_REIMBURSEMENT => 'Reimbursement approved. Please process payment to reimburse the employee.',
            default => 'Expense approved successfully',
        };

        return [$message, $budget['warnings'], $child];
    }

    /**
     * The part of a settlement that the employee's advance did not cover: the employee paid it from
     * their own pocket, so the company owes it back — an already-approved reimbursement, payable
     * through the normal voucher flow, pointing back at the settlement.
     */
    private function createShortfallReimbursement(Expense $settlement, User $actor, int $shortfallCents, int $coveredCents): Expense
    {
        $child = Expense::create([
            'tenant_id' => $settlement->tenant_id,
            'user_id' => $settlement->user_id,
            'expense_type' => $settlement->expense_type,
            'project_id' => $settlement->project_id,
            'requirement_type' => Expense::TYPE_REIMBURSEMENT,
            'amount' => Money::fromCents($shortfallCents),
            'date' => $settlement->date,
            'description' => "Balance of settlement {$settlement->expense_number} beyond the employee's advance "
                . '(₹' . Money::format($coveredCents) . ' of ₹' . Money::format(Money::toCents($settlement->amount)) . ' was covered by the advance)',
            'parent_expense_id' => $settlement->id,
            'status' => Expense::STATUS_APPROVED,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'approval_remarks' => "Created automatically when {$settlement->expense_number} was approved",
        ]);

        ExpenseStatusHistory::create([
            'expense_id' => $child->id,
            'status' => Expense::STATUS_APPROVED,
            'changed_by' => $actor->id,
            'remarks' => "Created from the shortfall of settlement {$settlement->expense_number}",
        ]);

        $this->audit->record('tenant_user', $actor->id, (int) $settlement->tenant_id, 'expenses.shortfall_reimbursement_created', 'Expense', (int) $child->id, [],
            ['settlement_id' => $settlement->id, 'amount' => (string) $child->amount, 'covered_by_advance' => (string) Money::fromCents($coveredCents)]);

        return $child;
    }

    // =================================================================
    //  Helpers
    // =================================================================

    /** Lock a claim and require: it exists, the actor owns it, and it is still pending. */
    private function lockOwnPending(User $actor, int $expenseId, string $verb): Expense
    {
        $expense = Expense::whereKey($expenseId)->lockForUpdate()->first();

        if (! $expense) {
            throw new ExpenseException('Expense not found.', 404);
        }
        if ((int) $expense->user_id !== (int) $actor->id) {
            throw new ExpenseException("You can only edit your own expenses.", 403);
        }
        if ($expense->status !== Expense::STATUS_PENDING) {
            throw new ExpenseException("Only pending expenses can be {$verb}.", 400);
        }

        return $expense;
    }

    /**
     * @param  UploadedFile|array<int, UploadedFile|null>|null  $receipts
     * @return array<int, UploadedFile>
     */
    private function files(UploadedFile|array|null $receipts): array
    {
        $files = array_values(array_filter(is_array($receipts) ? $receipts : [$receipts], fn ($f) => $f instanceof UploadedFile));

        if (count($files) > ExpenseAttachmentService::MAX_FILES) {
            throw new ExpenseException('A claim can have at most ' . ExpenseAttachmentService::MAX_FILES . ' receipts.', 422);
        }

        return $files;
    }
}
