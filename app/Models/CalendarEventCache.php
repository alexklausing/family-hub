<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['calendar_id', 'external_id', 'title', 'start', 'end', 'all_day', 'data'])]
class CalendarEventCache extends Model
{
    use HasFactory;

    protected $table = 'calendar_events_cache';

    protected function casts(): array
    {
        return [
            'start' => 'datetime',
            'end' => 'datetime',
            'all_day' => 'boolean',
            'data' => 'array',
        ];
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }
}
