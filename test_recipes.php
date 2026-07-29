<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$recipes = \App\Models\Recipe::whereIn('uuid', \App\Models\ShoppingListItem::select('recipe_uuid')->distinct()->get()->pluck('recipe_uuid'))->get();
echo json_encode($recipes->pluck('name', 'uuid'), JSON_PRETTY_PRINT);
