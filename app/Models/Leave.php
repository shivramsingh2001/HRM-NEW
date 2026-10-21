<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantTrait;

/**
 * @property int $user_id
 * @property int|null $leave_type
 * @property mixed $start_date
 * @property mixed $end_date
 */
class Leave extends Model
{
    use TenantTrait;
    
    protected $guarded = [];
    // protected $casts = [
    //     'start_date' => 'date',
    //     'end_date'   => 'date',
    // ];


    /**
     * My Leaves search box: leave ID (LV-000001), reason or leave type name.
     *
     * @param  Builder<Leave>  $query
     * @return Builder<Leave>
     */
    public function scopeSearchText(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';

        return $query->where(function ($q) use ($like) {
            $q->where('leave_id', 'LIKE', $like)
                ->orWhere('reason', 'LIKE', $like)
                ->orWhereHas('leaveType', fn ($t) => $t->where('name', 'LIKE', $like));
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    /**
     * Who approved / cancelled it (status_update_by).
     *
     * @return BelongsTo<User, $this>
     */
    public function statusUpdater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_update_by');
    }

    /** @return BelongsTo<LeaveType, $this> */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type');
    }
}
