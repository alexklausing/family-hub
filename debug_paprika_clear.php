<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

$s = app(App\Services\PaprikaSyncService::class);
if ($s->login()) {
    $token = (fn() => $this->token)->call($s);
    
    // Get current items
    $response = \Illuminate\Support\Facades\Http::withToken($token)
        ->withHeaders(['User-Agent' => 'Paprika/3.0.0'])
        ->get("https://www.paprikaapp.com/api/v2/sync/groceries/");
    
    $items = $response->json('result');
    if (is_string($items)) {
        $items = json_decode(gzdecode(base64_decode($items)), true);
    }
    
    if (count($items) > 0) {
        $deleteList = array_map(fn($i) => ['uid' => $i['uid'], 'deleted' => 1], $items);
        echo "Attempting to delete " . count($deleteList) . " items...\n";
        
        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->withHeaders(['User-Agent' => 'Paprika/3.0.0'])
            ->asMultipart()
            ->post("https://www.paprikaapp.com/api/v2/sync/groceries/", [
                'data' => base64_encode(gzencode(json_encode($deleteList)))
            ]);
            
        echo "Status: " . $response->status() . "\n";
        echo "Response: " . $response->body() . "\n";
    }
}
