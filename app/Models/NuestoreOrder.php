<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NuestoreOrder extends Model
{
    protected $table    = 'nuestore_orders';
    protected $keyType  = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'customer_id',
        'platform',
        'category',
        'service_id',
        'service_name',
        'target_link',
        'quantity',
        'base_price',
        'unique_code',
        'total_amount',
        'modal_cost',
        'profit_estimated',
        'proof_file_id',
        'admin_message_id',
        'rejected_reason',
        'provider_order_id',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function customer()
    {
        return $this->belongsTo(NuestoreCustomer::class, 'customer_id');
    }

    /**
     * Cek apakah order sudah expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast() && $this->status === 'PENDING_PAYMENT';
    }

    /**
     * Format total_amount dengan kode unik untuk ditampilkan ke user.
     */
    public function formattedTotal(): string
    {
        return 'Rp ' . number_format($this->total_amount, 0, ',', '.');
    }
}
