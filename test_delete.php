<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app(\App\Services\PaprikaSyncService::class);
$item = \App\Models\ShoppingListItem::first();
if ($item) {
    echo "Deleting item: " . $item->uuid . "\n";
    $result = $service->deleteItem($item->uuid);
    echo "Result: " . ($result ? 'true' : 'false') . "\n";
} else {
    echo "No items found\n";
}
