<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RewardLedger extends Model
{
    protected $fillable = [
        'profile',
        'type',
        'reward_text',
        'amount',
        'source',
        'chore_completion_id',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function choreCompletion()
    {
        return $this->belongsTo(ChoreCompletion::class);
    }
}
