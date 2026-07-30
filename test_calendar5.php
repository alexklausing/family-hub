<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$str = "2025-12-11T13:30:00+0000";
$carbon = \Carbon\Carbon::parse($str);
var_dump($carbon->format('c'));

$event = new \App\Models\CalendarEventCache();
$event->start = $carbon;
var_dump($event->start->format('c'));
