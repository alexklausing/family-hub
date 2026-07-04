<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app(\App\Services\Calendar\AppleCalendarService::class);
$calendars = $service->getCalendars('alex.klausing@icloud.com', 'rpkr-lige-tncy-cdez');

print_r($calendars);
