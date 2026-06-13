<?php

namespace App\Services\Calendar;

use Carbon\Carbon;
use Spatie\GoogleCalendar\Event;

class GoogleCalendarService
{
    protected array $calendarIds;

    public function __construct()
    {
        // Support multiple comma-separated IDs in the .env
        $ids = config('services.google.calendar_id');
        $this->calendarIds = $ids ? explode(',', $ids) : [];
    }

    /**
     * Fetch events from all configured Google Calendars.
     */
    public function getEvents(?Carbon $start = null, ?Carbon $end = null): array
    {
        $allEvents = [];

        foreach ($this->calendarIds as $calendarId) {
            try {
                $events = Event::get($start, $end, [], trim($calendarId));

                foreach ($events as $event) {
                    $allEvents[] = [
                        'id' => $event->id,
                        'title' => $event->name,
                        'start' => $event->startDateTime ? $event->startDateTime->toIso8601String() : $event->startDate.'T00:00:00Z',
                        'end' => $event->endDateTime ? $event->endDateTime->toIso8601String() : $event->endDate.'T23:59:59Z',
                        'provider' => 'google',
                        'calendar_id' => $calendarId,
                    ];
                }
            } catch (\Exception $e) {
                \Log::error("Google Calendar Sync Error for ID {$calendarId}: ".$e->getMessage());
            }
        }

        return $allEvents;
    }
}
