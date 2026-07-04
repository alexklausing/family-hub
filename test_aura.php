<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = new \App\Services\AuraFramesService();
$images = $service->getImages();

echo "Images found: " . count($images) . "\n";
print_r($images);
