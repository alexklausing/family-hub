<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tab = \App\Models\Tab::where('type', 'family')->first();
if ($tab) {
    $manager = app(\App\Services\Calendar\CalendarManager::class);
    $manager->getEventsForTab($tab, now()->subDays(30), now()->addDays(30));
}

$event = \App\Models\CalendarEventCache::first();
echo json_encode($event);
