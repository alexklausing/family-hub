<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['provider', 'external_id', 'color', 'tab_id', 'credentials', 'refresh_rate', 'last_synced_at'])]
class Calendar extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'credentials' => 'array',
            'refresh_rate' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public function tab(): BelongsTo
    {
        return $this->belongsTo(Tab::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CalendarEventCache::class);
    }

    public function needsSync(): bool
    {
        if (! $this->last_synced_at) {
            return true;
        }

        return $this->last_synced_at->addMinutes($this->refresh_rate)->isPast();
    }
}
