<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'is_default', 'order', 'default_calendar_id', 'visible_calendars'])]
class Profile extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'order' => 'integer',
            'visible_calendars' => 'array',
        ];
    }

    public function tabs(): HasMany
    {
        return $this->hasMany(Tab::class)->orderBy('order');
    }

    public function defaultCalendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class, 'default_calendar_id');
    }
}
