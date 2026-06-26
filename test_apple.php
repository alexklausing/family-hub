<?php

use App\Services\Calendar\AppleCalendarService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$service = app(AppleCalendarService::class);
$calendars = $service->getCalendars(config('services.apple.email'), config('services.apple.password'));
print_r($calendars);
