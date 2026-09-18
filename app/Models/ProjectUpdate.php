<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * A human-authored, structured progress report on a project: what's done,
 * what's pending, issues, next actions, and free-text notes. Optionally
 * carries a manually-reported progress percentage, which — when present —
 * becomes the project's authoritative progress_percentage (see
 * ProjectController::storeUpdate() and TaskProgressObserver's
 * progress_manual_override guard).
 */
class ProjectUpdate extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id',
        'project_id',
        'user_id',
        'reported_progress_percentage',
        'completed_work',
        'pending_work',
        'issues',
        'next_actions',
        'notes',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
