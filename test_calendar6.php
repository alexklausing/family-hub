<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$str = "2025-12-11T13:30:00+0000";
$carbon = \Carbon\Carbon::parse($str)->setTimezone(config('app.timezone'));

// Simulate eloquent save/load
$dbString = $carbon->format('Y-m-d H:i:s');
$loaded = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $dbString, config('app.timezone'));

var_dump($loaded->format('c'));
var_dump($loaded->setTimezone('UTC')->format('c'));
