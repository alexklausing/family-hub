<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BonusRewardGrant extends Model
{
    use HasFactory;

    protected $fillable = [
        'bonus_reward_id',
        'profile',
        'week_start_date',
    ];

    protected $casts = [
        'week_start_date' => 'date',
    ];

    public function bonusReward()
    {
        return $this->belongsTo(BonusReward::class);
    }
}
