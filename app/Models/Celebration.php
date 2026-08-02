<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Celebration extends Model
{
    use HasFactory;

    protected $fillable = [
        'message',
        'background',
        'font',
        'font_color',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Celebration $celebration) {
            if ($celebration->is_active) {
                static::where('id', '!=', $celebration->id)->update(['is_active' => false]);
            }
        });
    }
}
