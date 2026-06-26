<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Label extends Model
{
    protected $fillable = ['name', 'reward', 'is_bankable'];

    protected $casts = [
        'is_bankable' => 'boolean',
    ];

    public function chores()
    {
        return $this->hasMany(Chore::class);
    }

    public function bonusReward()
    {
        return $this->hasOne(BonusReward::class);
    }
}
