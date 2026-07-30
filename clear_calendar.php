<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
\App\Models\CalendarEventCache::truncate();
\App\Models\Calendar::query()->update(['last_synced_at' => null]);
echo "Cleared calendar cache and sync timestamps\n";
