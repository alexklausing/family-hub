<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Label extends Model
{
    protected $fillable = ['name', 'reward'];

    public function chores()
    {
        return $this->hasMany(Chore::class);
    }
}
