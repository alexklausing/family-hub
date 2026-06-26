<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChoreCompletion extends Model
{
    protected $fillable = [
        'chore_id',
        'date',
        'status',
        'awarded_value',
    ];

    public function chore()
    {
        return $this->belongsTo(Chore::class);
    }
}
