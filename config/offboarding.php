<?php

/**
 * Offboarding module policy matrix. Code-owned (not tenant-editable) —
 * mirrors how config/rbac.php's system_roles matrix works. Keeps the six
 * reasons on one request model/table without forking controllers: who may
 * create a request for each reason, whether the tenant notice-period
 * minimum applies, and whether an exit interview is required.
 */
return [

    'reason_rules' => [
        'resignation' => [
            'creatable_by' => ['employee', 'hr', 'admin'],
            'requires_notice' => true,
            'requires_exit_interview' => true,
        ],
        'retirement' => [
            'creatable_by' => ['employee', 'hr', 'admin'],
            'requires_notice' => true,
            'requires_exit_interview' => true,
        ],
        'contract_end' => [
            'creatable_by' => ['hr', 'admin'],
            'requires_notice' => true,
            'requires_exit_interview' => true,
        ],
        'mutual_agreement' => [
            'creatable_by' => ['hr', 'admin'],
            'requires_notice' => true,
            'requires_exit_interview' => true,
        ],
        'termination' => [
            'creatable_by' => ['hr', 'admin'],
            'requires_notice' => false,
            'requires_exit_interview' => false,
        ],
        'other' => [
            'creatable_by' => ['hr', 'admin'],
            'requires_notice' => true,
            'requires_exit_interview' => true,
        ],
    ],

    // Used when tenants.notice_period is null.
    'default_notice_period_days' => 30,

    // Divisor used to derive a per-day rate from monthly salary for pending-salary
    // and leave-encashment settlement lines (26 working days/month is a common
    // Indian payroll convention; matches this app's Asia/Kolkata default timezone).
    'settlement_per_day_divisor' => 26,
];
