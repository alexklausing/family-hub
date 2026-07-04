<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$calendars = App\Models\Calendar::where('provider', 'apple')->get();
foreach ($calendars as $c) {
    echo "ID: {$c->id}, Name: {$c->name}\n";
    print_r($c->credentials);
}
