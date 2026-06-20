<?php
$calendar = App\Models\Calendar::where('provider', 'apple')->first();
$service = app(App\Services\Calendar\AppleCalendarService::class);
$events = $service->getEvents($calendar->credentials['email'], $calendar->credentials['password'], $calendar->credentials['path']);
echo "Fetched " . count($events) . " events.\n";
