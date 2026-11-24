<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One visit an employee/manager records at a Site. `visit_date` is the day
 * it was saved — set by the server, never taken from the client.
 */
class SiteVisit extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id', 'user_id', 'site_id', 'visit_date', 'visited_at',
        'contact_person_name', 'contact_mobile', 'remarks', 'current_stock', 'date_of_stock_received', 'file_path',
        'latitude', 'longitude', 'edited_at',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'visited_at' => 'datetime',
        'edited_at' => 'datetime',
        'current_stock' => 'decimal:2',
        'date_of_stock_received' => 'date',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    /** Current stock for display: "120", "12.5" or "—" when not filled. */
    public function stockText(): string
    {
        $stock = $this->getAttribute('current_stock');

        return $stock === null ? '—' : rtrim(rtrim(number_format((float) $stock, 2, '.', ''), '0'), '.');
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
