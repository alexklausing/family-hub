<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['uuid', 'hash', 'title', 'ingredients', 'directions', 'image_url', 'category'])]
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
