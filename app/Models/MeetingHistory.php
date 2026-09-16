<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\TenantTrait;

class MeetingHistory extends Model
{
    use TenantTrait, HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'meeting_id',
        'action_by',
        'action_type',
        'description',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function actionBy()
    {
        return $this->belongsTo(User::class, 'action_by');
    }

    /**
     * Convenience writer used by every mutating meeting action (create,
     * update, cancel, reschedule, destroy, attendance, MOM finalize) so the
     * audit trail is one line to add at each call site instead of a
     * hand-rolled create() every time.
     */
    public static function record(Meeting $meeting, string $actionType, ?string $description = null, ?array $old = null, ?array $new = null): self
    {
        return static::create([
            'meeting_id' => $meeting->id,
            'action_by' => auth()->id(),
            'action_type' => $actionType,
            'description' => $description,
            'old_values' => $old,
            'new_values' => $new,
            'created_at' => now(),
        ]);
    }
}
