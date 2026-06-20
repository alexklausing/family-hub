<?php

use App\Models\Calendar;
use App\Models\CalendarEventCache;

echo 'Calendars: '.Calendar::count()."\n";
echo 'Events: '.CalendarEventCache::count()."\n";
foreach (Calendar::all() as $cal) {
    echo $cal->name.' ('.$cal->provider.'): '.$cal->events()->count()." events\n";
}
