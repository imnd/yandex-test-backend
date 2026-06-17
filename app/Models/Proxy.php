<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proxy extends Model
{
    protected $fillable = [
        'server',
        'is_active',
        'fails_count',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'fails_count' => 'integer',
        'last_used_at' => 'datetime',
    ];
}
