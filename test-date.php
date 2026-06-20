<?php

use App\Models\CalendarEventCache;

$events = CalendarEventCache::where('calendar_id', 7)->get();
foreach ($events as $e) {
    echo $e->start.' to '.$e->end."\n";
}
