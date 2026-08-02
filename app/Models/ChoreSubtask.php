<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChoreSubtask extends Model
{
    protected $fillable = [
        'chore_id',
        'title',
        'order',
    ];

    public function chore()
    {
        return $this->belongsTo(Chore::class);
    }
}
