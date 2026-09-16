<?php

/**
 * Leave Management module — global defaults that aren't per-leave-type.
 * Per-type policy (carry-forward, notice period, max consecutive days,
 * encashment, document threshold) lives on `leave_types` columns instead,
 * since those naturally vary by type — see the Phase 1 migration adding
 * them. This file only holds constants shared across every type/tenant.
 */
return [
    // Yearly leave credit cycles anchor to this month/day (1-12, 1-31).
    // Was hardcoded as Carbon::create($year, 4, 1) in LeaveCreditController.
    'fiscal_year_start_month' => env('LEAVE_FISCAL_YEAR_START_MONTH', 4),
    'fiscal_year_start_day' => env('LEAVE_FISCAL_YEAR_START_DAY', 1),

    // Valid values for leaves.start_session / end_session.
    'sessions' => ['session1', 'session2', 'fullday'],

    // Valid values for leaves.status.
    'statuses' => ['pending', 'approved', 'cancelled'],

    // Valid values for leave_transactions.transaction_type.
    'transaction_types' => ['add', 'sub'],

    // Valid values for leave_transactions.leave_detail.
    'leave_details' => ['paid', 'unpaid', 'mixed'],

    // A manual balance debit (LeaveCreditController::manualDebit) may not
    // push a user's balance below this floor. 0 = no negative balance
    // allowed; a tenant that wants to permit going into the red can raise it
    // via env, since there's no per-tenant DB override for this yet.
    'max_negative_balance' => (float) env('LEAVE_MAX_NEGATIVE_BALANCE', 0),
];
