<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$item = \App\Models\ShoppingListItem::first();
if ($item) {
    echo "Item ID: " . $item->id . "\n";
    $request = Illuminate\Http\Request::create('/api/shopping-list/' . $item->id, 'DELETE');
    // Disable CSRF for testing
    $app->instance(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class, new class($app) {
        public function handle($request, $next) { return $next($request); }
    });
    $response = app()->handle($request);
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Content: " . $response->getContent() . "\n";
} else {
    echo "No items found.\n";
}
