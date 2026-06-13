<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['uuid', 'recipe_uuid', 'name', 'ingredient', 'quantity', 'aisle', 'purchased', 'order_flag', 'data'])]
class ShoppingListItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'purchased' => 'boolean',
            'order_flag' => 'integer',
            'data' => 'array',
        ];
    }
}
