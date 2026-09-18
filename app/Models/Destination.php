<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Destination extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'lat',
        'lon',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'lat' => 'float',
        'lon' => 'float',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    protected function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
