<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Countdown extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'target_date',
        'icon',
    ];

    protected $casts = [
        'target_date' => 'date',
    ];
}
