<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$calendars = \App\Models\Calendar::all();
$manager = app(\App\Services\Calendar\CalendarManager::class);

foreach ($calendars as $calendar) {
    $manager->syncCalendar($calendar);
}

$event = \App\Models\CalendarEventCache::first();
echo json_encode($event);
