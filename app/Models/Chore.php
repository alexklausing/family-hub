<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chore extends Model
{
    protected $fillable = [
        'title',
        'profile',
        'time',
        'days',
        'is_active',
        'order',
        'reward',
        'is_bankable',
        'label_id',
    ];

    protected $casts = [
        'days' => 'array',
        'is_active' => 'boolean',
    ];

    public function completions()
    {
        return $this->hasMany(ChoreCompletion::class);
    }

    public function label()
    {
        return $this->belongsTo(Label::class);
    }

    public function bonusReward()
    {
        return $this->hasOne(BonusReward::class);
    }
}
