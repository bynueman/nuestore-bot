<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NuestoreUser extends Model
{
    protected $table = 'nuestore_users';

    protected $fillable = [
        'telegram_id',
        'username',
    ];

    public function transactions()
    {
        return $this->hasMany(NuestoreTransaction::class, 'user_id');
    }
}