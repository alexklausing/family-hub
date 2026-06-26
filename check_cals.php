<?php

use App\Models\Profile;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$profiles = Profile::with('tabs.calendars')->get();
foreach ($profiles as $p) {
    echo "Profile: {$p->name}\n";
    foreach ($p->tabs as $t) {
        foreach ($t->calendars as $c) {
            echo "  - Calendar: {$c->name} (ID: {$c->id})\n";
        }
    }
}
