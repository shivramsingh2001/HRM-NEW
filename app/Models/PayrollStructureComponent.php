<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * Which components a PayrollStructure includes, with optional per-structure
 * overrides of the catalog's default calculation method/value.
 */
class PayrollStructureComponent extends Model
{
    use TenantTrait;

    protected $guarded = ['id'];

    protected $casts = [
        'override_amount' => 'decimal:2',
        'override_percentage' => 'decimal:3',
        'is_mandatory' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function structure()
    {
        return $this->belongsTo(PayrollStructure::class, 'payroll_structure_id');
    }

    public function component()
    {
        return $this->belongsTo(PayrollComponentMaster::class, 'payroll_component_master_id');
    }
}
