<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Deliberately NOT using TenantTrait: rows with tenant_id = NULL are a
 * shared default catalog visible to every tenant (see scopeForTenant()),
 * which TenantTrait's strict `where('tenant_id', $tenant->id)` global scope
 * would hide. Tenant-specific custom tasks are still supported by setting
 * tenant_id explicitly when creating one.
 */
class OnboardingTask extends Model
{
    protected $table = 'onboarding_tasks';

    protected $fillable = [
        'tenant_id',
        'task_name',
        'task_category',
        'task_order',
        'is_mandatory',
        'assigned_to_role',
        'default_days_to_complete',
        'description',
        'is_active',
    ];

    protected $casts = [
        'task_order' => 'integer',
        'is_mandatory' => 'boolean',
        'default_days_to_complete' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Category constants
    const CATEGORY_DOCUMENT = 'document';
    const CATEGORY_ACCOUNT_CREATION = 'account_creation';
    const CATEGORY_ASSET = 'asset';
    const CATEGORY_TRAINING = 'training';
    const CATEGORY_COMPLIANCE = 'compliance';
    const CATEGORY_ORIENTATION = 'orientation';
    const CATEGORY_OTHER = 'other';

    public static $categories = [
        self::CATEGORY_DOCUMENT => 'Document',
        self::CATEGORY_ACCOUNT_CREATION => 'Account Creation',
        self::CATEGORY_ASSET => 'Asset',
        self::CATEGORY_TRAINING => 'Training',
        self::CATEGORY_COMPLIANCE => 'Compliance',
        self::CATEGORY_ORIENTATION => 'Orientation',
        self::CATEGORY_OTHER => 'Other',
    ];

    // Relationships
    public function taskItems()
    {
        return $this->hasMany(OnboardingTaskItem::class);
    }

    // Accessors
    public function getCategoryLabelAttribute()
    {
        return self::$categories[$this->task_category] ?? ucfirst($this->task_category);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('task_order');
    }

    /**
     * Onboarding tasks with tenant_id = NULL are the shared/global default
     * catalog (seeded once, visible to every tenant) alongside any
     * tenant-specific rows a tenant later adds of its own.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where(function ($q) use ($tenantId) {
            $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
        });
    }
}
