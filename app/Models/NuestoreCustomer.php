<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NuestoreCustomer extends Model
{
    protected $table = 'nuestore_customers';

    protected $fillable = [
        'telegram_id',
        'username',
        'first_name',
        'is_blacklisted',
        'blacklist_reason',
        'failed_proofs_count',
        'expired_today_count',
        'last_order_at',
    ];

    protected $casts = [
        'is_blacklisted' => 'boolean',
        'last_order_at'  => 'datetime',
    ];

    /**
     * Get or create a customer record from a Telegram user object.
     */
    public static function fromTelegramUser($user): static
    {
        return static::firstOrCreate(
            ['telegram_id' => (string) $user->id],
            [
                'username'   => $user->username ?? null,
                'first_name' => $user->first_name ?? null,
            ]
        );
    }

    /**
     * Cek apakah customer punya order yang sedang pending.
     */
    public function hasPendingOrder(): bool
    {
        return $this->orders()
            ->whereIn('status', ['PENDING_PAYMENT', 'PROOF_SUBMITTED'])
            ->exists();
    }

    /**
     * Ambil order yang sedang pending (1 saja).
     */
    public function pendingOrder(): ?NuestoreOrder
    {
        return $this->orders()
            ->whereIn('status', ['PENDING_PAYMENT', 'PROOF_SUBMITTED'])
            ->latest()
            ->first();
    }

    public function orders()
    {
        return $this->hasMany(NuestoreOrder::class, 'customer_id');
    }
}
