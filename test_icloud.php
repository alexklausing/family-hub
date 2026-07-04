<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$c = App\Models\Calendar::where('provider', 'apple')->first();
if(!$c) { echo "No apple calendar\n"; exit; }

$creds = $c->credentials;
$service = app(App\Services\Calendar\AppleCalendarService::class);
$calendars = $service->getCalendars($creds['email'], $creds['password']);

print_r($calendars);
