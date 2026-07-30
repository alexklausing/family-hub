<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
var_dump(config('app.timezone'));
$parsed = \Carbon\Carbon::parse("2025-08-05T08:15:00-0400");
var_dump($parsed->format('c'));
$utc = $parsed->setTimezone('UTC');
var_dump($utc->format('c'));
