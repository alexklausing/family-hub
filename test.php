<?php

use App\Models\CalendarEventCache;

$events = CalendarEventCache::with('calendar')->get();
$counts = [];
foreach ($events as $event) {
    $provider = $event->calendar->provider ?? 'none';
    $counts[$provider] = ($counts[$provider] ?? 0) + 1;
}
echo json_encode($counts)."\n";
