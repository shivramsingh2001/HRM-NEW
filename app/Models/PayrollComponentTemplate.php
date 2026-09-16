<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Platform-curated component defaults offered to every tenant. Not
 * tenant-scoped — cloned into each tenant's own PayrollComponentMaster row
 * at onboarding/backfill time, then never referenced again directly.
 */
class PayrollComponentTemplate extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_statutory' => 'boolean',
        'percentage_value' => 'decimal:3',
        'default_amount' => 'decimal:2',
        'priority' => 'integer',
        'is_taxable' => 'boolean',
        'has_wage_ceiling' => 'boolean',
        'ceiling_amount' => 'decimal:2',
        'eligibility_rules' => 'array',
        'affects_gross' => 'boolean',
        'affects_ctc' => 'boolean',
        'affects_net' => 'boolean',
        'display_order' => 'integer',
        'is_offered_to_new_tenants' => 'boolean',
    ];
}
