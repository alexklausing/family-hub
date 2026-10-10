<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class MobileDevice extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'name',
        'paired_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'paired_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
