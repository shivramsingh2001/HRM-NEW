<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * A payment voucher: one payment run covering one or many expenses, with the
 * mode / date / reference entered once. Every payment belongs to a batch (a
 * single-expense payment is a one-line batch). Never deleted — a mistaken
 * voucher is VOIDED (which reverses each line's ledger effect).
 */
class ExpensePaymentBatch extends Model
{
    use TenantTrait;

    public const STATUS_POSTED = 'posted';
    public const STATUS_VOIDED = 'voided';

    protected $table = 'expense_payment_batches';

    protected $fillable = [
        'tenant_id', 'voucher_number', 'payment_date', 'payment_mode', 'reference_number', 'bank_name',
        'remarks', 'total_amount', 'line_count', 'status', 'idempotency_key', 'created_by',
        'voided_by', 'voided_at', 'void_reason',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'total_amount' => 'decimal:2',
        'line_count' => 'integer',
        'voided_at' => 'datetime',
    ];

    /** Every line, including voided ones (audit view). */
    public function payments()
    {
        return $this->hasMany(ExpensePayment::class, 'batch_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voider()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }
}
