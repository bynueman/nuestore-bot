<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NuestoreSetting extends Model
{
    protected $table = 'nuestore_settings';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }
}