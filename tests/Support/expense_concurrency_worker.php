<?php

/**
 * One contender in an Expense concurrency test (see ExpenseConcurrencyTest).
 * Boots the application in its own OS process, waits until an agreed wall-clock
 * instant so all contenders hit the database at (nearly) the same moment, then
 * performs ONE operation and prints a single JSON line.
 *
 * usage: php expense_concurrency_worker.php '<json payload>'
 */

$payload = json_decode($argv[1] ?? '{}', true);

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

app()->instance('current_tenant', App\Models\Tenant::find($payload['tenant_id']));
$actor = App\Models\User::withoutGlobalScopes()->find($payload['actor_id']);

// Barrier: everyone waits for the same instant.
while (microtime(true) < $payload['start_at']) {
    usleep(500);
}
$firedAt = microtime(true);

try {
    if ($payload['op'] === 'approve') {
        app(App\Services\Expense\ExpenseService::class)->decide((int) $payload['expense_id'], $actor, 'approved', null);
        $out = ['ok' => true];
    } elseif ($payload['op'] === 'send_payroll') {
        $r = app(App\Services\Expense\ExpenseReimbursementPayrollService::class)->sendToPayroll($actor, $payload['ids'], $payload['month'], fn () => true);
        $out = ['ok' => true, 'sent' => $r['sent']];
    } elseif ($payload['op'] === 'batch') {
        $result = app(App\Services\Expense\ExpensePaymentService::class)->payBatch($actor, $payload['lines'], [
            'payment_date' => date('Y-m-d'), 'payment_mode' => 'bank_transfer', 'reference_number' => 'PAR',
        ], $payload['key'] ?? null, fn () => true);
        $out = ['ok' => true, 'duplicate' => $result['duplicate'], 'voucher' => $result['batch']->voucher_number, 'batch_id' => $result['batch']->id];
    } elseif ($payload['op'] === 'void_batch') {
        $result = app(App\Services\Expense\ExpensePaymentService::class)->voidBatch($actor, (int) $payload['batch_id'], 'parallel void', fn () => true);
        $out = ['ok' => true, 'voided' => $result['voided']];
    } else {
        $result = app(App\Services\Expense\ExpensePaymentService::class)->record($actor, 'advance', [
            'payment_date' => date('Y-m-d'),
            'amount' => $payload['amount'],
            'payment_mode' => 'cash',
            'expense_id' => $payload['expense_id'],
        ], $payload['key'] ?? null, fn () => true);
        $out = ['ok' => true, 'duplicate' => $result['duplicate']];
    }
} catch (App\Exceptions\ExpenseException $e) {
    $out = ['ok' => false, 'message' => $e->getMessage(), 'status' => $e->httpStatus()];
} catch (Throwable $e) {
    $out = ['ok' => false, 'fatal' => get_class($e) . ': ' . $e->getMessage()];
}

$out['fired_at'] = $firedAt;
echo "\n@@RESULT@@" . json_encode($out) . "\n";
