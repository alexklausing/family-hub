<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['uuid', 'hash', 'title', 'ingredients', 'directions', 'image_url', 'category', 'prep_time', 'cook_time', 'total_time', 'rating'])]
class Recipe extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'ingredients' => 'string',
            'directions' => 'string',
        ];
    }
}
