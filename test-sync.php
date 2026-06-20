<?php

use App\Models\Calendar;
use App\Services\Calendar\AppleCalendarService;

$calendar = Calendar::where('provider', 'apple')->first();
$service = app(AppleCalendarService::class);
$events = $service->getEvents($calendar->credentials['email'], $calendar->credentials['password'], $calendar->credentials['path']);
echo 'Fetched '.count($events)." events.\n";
