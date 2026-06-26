<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Exists: " . (Illuminate\Support\Facades\Storage::exists('words_of_the_day.json') ? "Yes" : "No") . "\n";
