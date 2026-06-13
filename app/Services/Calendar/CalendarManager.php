<?php

namespace App\Services\Calendar;

use App\Models\Calendar;
use App\Models\CalendarEventCache;
use App\Models\Tab;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CalendarManager
{
    public function __construct(
        protected AppleCalendarService $apple,
        protected GoogleCalendarService $google,
        protected Office365CalendarService $office,
        protected ICalSubscriptionService $ical
    ) {}

    /**
     * Get events for a specific tab, syncing if necessary.
     */
    public function getEventsForTab(Tab $tab, ?Carbon $start = null, ?Carbon $end = null): Collection
    {
        $calendars = $tab->calendars;

        foreach ($calendars as $calendar) {
            if ($calendar->needsSync()) {
                $this->syncCalendar($calendar, $start, $end);
            }
        }

        return CalendarEventCache::with('calendar')
            ->whereIn('calendar_id', $calendars->pluck('id'))
            ->where(function ($query) use ($start, $end) {
                if ($start) {
                    $query->where('end', '>=', $start);
                }
                if ($end) {
                    $query->where('start', '<=', $end);
                }
            })
            ->orderBy('start', 'asc')
            ->get();
    }

    /**
     * Sync a specific calendar from its provider.
     */
    public function syncCalendar(Calendar $calendar, ?Carbon $start = null, ?Carbon $end = null): void
    {
        try {
            $events = match ($calendar->provider) {
                'apple' => $this->apple->getEvents(), // range support to be added
                'google' => $this->google->getEvents($start, $end),
                'office365' => $this->office->getEvents(), // range support to be added
                'ical' => $this->ical->getEvents($calendar->credentials['url'] ?? null),
                default => [],
            };

            // Clear old cache for this calendar within this range if applicable,
            // or just update/upsert. For now, we'll upsert by external_id.
            foreach ($events as $eventData) {
                CalendarEventCache::updateOrCreate(
                    [
                        'calendar_id' => $calendar->id,
                        'external_id' => $eventData['id'],
                    ],
                    [
                        'title' => $eventData['title'],
                        'start' => Carbon::parse($eventData['start']),
                        'end' => Carbon::parse($eventData['end']),
                        'all_day' => $eventData['all_day'] ?? false,
                        'data' => $eventData,
                    ]
                );
            }

            $calendar->update(['last_synced_at' => now()]);

        } catch (\Exception $e) {
            Log::error("Sync failed for calendar {$calendar->id} ({$calendar->provider}): ".$e->getMessage());
        }
    }
}
