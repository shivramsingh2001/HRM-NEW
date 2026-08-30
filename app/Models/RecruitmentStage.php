<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class RecruitmentStage extends Model
{
    use TenantTrait;

    protected $table = 'recruitment_stages';

    protected $fillable = [
        'tenant_id',
        'stage_name',
        'stage_order',
        'stage_type',
        'is_active',
        'description'
    ];

    protected $casts = [
        'stage_order' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Stage type constants
    const TYPE_SCREENING = 'screening';
    const TYPE_TECHNICAL = 'technical';
    const TYPE_HR = 'hr';
    const TYPE_MANAGERIAL = 'managerial';
    const TYPE_ASSIGNMENT = 'assignment';
    const TYPE_FINAL = 'final';

    public static $stageTypes = [
        self::TYPE_SCREENING => 'Screening',
        self::TYPE_TECHNICAL => 'Technical',
        self::TYPE_HR => 'HR',
        self::TYPE_MANAGERIAL => 'Managerial',
        self::TYPE_ASSIGNMENT => 'Assignment',
        self::TYPE_FINAL => 'Final'
    ];

    // Relationships
    public function interviews()
    {
        return $this->hasMany(Interview::class);
    }

    // Accessors
    public function getStageTypeLabelAttribute()
    {
        return self::$stageTypes[$this->stage_type] ?? ucfirst($this->stage_type);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('stage_order');
    }
}
