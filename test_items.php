<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$items = \App\Models\ShoppingListItem::select('name', 'recipe_uuid')->get();
echo json_encode($items->toArray(), JSON_PRETTY_PRINT);
