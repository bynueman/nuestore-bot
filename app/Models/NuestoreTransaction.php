<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NuestoreTransaction extends Model
{
    protected $table = 'nuestore_transactions';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'provider_order_id',
        'target_link',
        'service_id',
        'duitku_ref',
        'amount_paid',
        'modal_cost',
        'pg_fee_estimated',
        'profit_estimated',
        'profit_actual',
        'retry_count',
        'last_retried_at',
        'retry_error_log',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(NuestoreUser::class, 'user_id');
    }
}