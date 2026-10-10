<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class MobileDevice extends Model
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
