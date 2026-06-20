<?php
echo "Calendars: " . App\Models\Calendar::count() . "\n";
echo "Events: " . App\Models\CalendarEventCache::count() . "\n";
foreach (App\Models\Calendar::all() as $cal) {
    echo $cal->name . " (" . $cal->provider . "): " . $cal->events()->count() . " events\n";
}
