<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BonusReward extends Model
{
    use HasFactory;

    protected $fillable = [
        'profile',
        'chore_id',
        'label_id',
        'required_days',
        'reward_value',
        'expires_in_days',
        'requires_approval',
    ];

    protected $casts = [
        'required_days' => 'array',
        'requires_approval' => 'boolean',
        'expires_in_days' => 'integer',
    ];

    public function chore()
    {
        return $this->belongsTo(Chore::class);
    }

    public function label()
    {
        return $this->belongsTo(Label::class);
    }

    public function grants()
    {
        return $this->hasMany(BonusRewardGrant::class);
    }
}
