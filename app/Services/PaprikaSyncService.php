<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\MealPlan;
use App\Models\ShoppingListItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PaprikaSyncService
{
    protected string $baseUrl = 'https://www.paprikaapp.com/api/v2';
    protected ?string $token = null;
    protected array $categoryMap = [];

    /**
     * Authenticate with Paprika API using a browser-like request
     */
    public function login(): bool
    {
        $email = config('services.paprika.email');
        $password = config('services.paprika.password');

        if (!$email || !$password) {
            Log::error('Paprika credentials not configured.');
            return false;
        }

        try {
            $response = Http::asForm()
                ->withHeaders([
                    'User-Agent' => 'Paprika/3.0.0 (Mac OS X 10.15.7)',
                    'Accept' => 'application/json',
                ])
                ->post("{$this->baseUrl}/account/login/", [
                    'email' => $email,
                    'password' => $password,
                ]);

            if ($response->successful()) {
                $this->token = $response->json('result.token');
                return true;
            }

            Log::error('Paprika login failed: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('Paprika login exception: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Sync all categories from Paprika
     */
    public function fetchCategories(): void
    {
        if (!$this->token && !$this->login()) return;

        $response = Http::withToken($this->token)
            ->withHeaders(['User-Agent' => 'Paprika/3.0.0'])
            ->get("{$this->baseUrl}/sync/categories/");

        if ($response->successful()) {
            $categories = $response->json('result');
            if (is_string($categories)) {
                $categories = json_decode(gzdecode(base64_decode($categories)), true);
            }

            if (is_array($categories)) {
                foreach ($categories as $cat) {
                    $this->categoryMap[$cat['uid']] = $cat['name'];
                }
            }
        }
    }

    /**
     * Sync all recipes from Paprika
     */
    public function syncRecipes(): void
    {
        if (!$this->token && !$this->login()) return;

        $this->fetchCategories();

        Log::info('Paprika: Fetching recipes list...');
        $response = Http::withToken($this->token)
            ->withHeaders(['User-Agent' => 'Paprika/3.0.0'])
            ->get("{$this->baseUrl}/sync/recipes/");
        
        if ($response->successful()) {
            $recipes = $response->json('result');
            if (is_string($recipes)) {
                $recipes = json_decode(gzdecode(base64_decode($recipes)), true);
            }

            Log::info('Paprika: Found ' . (is_array($recipes) ? count($recipes) : 0) . ' recipes in cloud.');
            
            if (is_array($recipes)) {
                foreach ($recipes as $recipeData) {
                    if (is_array($recipeData)) {
                        $this->updateOrCreateRecipe($recipeData);
                    }
                }
            }
        }
    }

    /**
     * Sync shopping list from Paprika
     */
    public function syncShoppingList(): void
    {
        if (!$this->token && !$this->login()) return;

        Log::info('Paprika: Fetching groceries...');
        $response = Http::withToken($this->token)
            ->withHeaders(['User-Agent' => 'Paprika/3.0.0'])
            ->get("{$this->baseUrl}/sync/groceries/");

        if ($response->successful()) {
            $items = $response->json('result');
            if (is_string($items)) {
                $items = json_decode(gzdecode(base64_decode($items)), true);
            }

            if (is_array($items)) {
                $syncedUuids = [];
                foreach ($items as $itemData) {
                    $syncedUuids[] = $itemData['uid'];
                    ShoppingListItem::updateOrCreate(
                        ['uuid' => $itemData['uid']],
                        [
                            'recipe_uuid' => $itemData['recipe_uid'] ?? null,
                            'name' => $itemData['name'],
                            'ingredient' => $itemData['ingredient'] ?? null,
                            'quantity' => $itemData['quantity'] ?? null,
                            'aisle' => $itemData['aisle'] ?? null,
                            'purchased' => (bool)($itemData['purchased'] ?? false),
                            'order_flag' => $itemData['order_flag'] ?? 0,
                            'data' => $itemData
                        ]
                    );
                }
                ShoppingListItem::whereNotIn('uuid', $syncedUuids)->delete();
            }
        }
    }

    /**
     * Add a recipe to the Paprika shopping list with optional scaling
     */
    public function addRecipeToShoppingList(string $recipeUuid, float $scale = 1.0): bool
    {
        if (!$this->token && !$this->login()) return false;

        $recipe = Recipe::where('uuid', $recipeUuid)->first();
        if (!$recipe) return false;

        $rawIngredients = array_filter(explode("\n", $recipe->ingredients));
        $items = [];
        foreach ($rawIngredients as $line) {
            $line = trim($line);
            if (!$line) continue;
            
            $scaledLine = $this->scaleIngredientLine($line, $scale);
            
            $items[] = [
                'uid' => strtoupper(uuid_create()),
                'recipe_uid' => $recipeUuid,
                'name' => $scaledLine,
                'purchased' => false,
                'order_flag' => 0
            ];
        }

        return $this->postSyncData('groceries', $items);
    }

    /**
     * Scale a single ingredient line
     */
    public function scaleIngredientLine(string $line, float $scale): string
    {
        if ($scale === 1.0) return $line;

        // Regex to find numbers (fractions, decimals, mixed numbers)
        // Matches: "1/2", "1.5", "2", "1 1/2"
        $pattern = '/(\d+\s+\d+\/\d+|\d+\/\d+|\d+\.\d+|\d+)/';

        return preg_replace_callback($pattern, function($matches) use ($scale) {
            $value = $matches[0];
            $numeric = $this->parseQuantity($value);
            $scaled = $numeric * $scale;
            return $this->formatQuantity($scaled);
        }, $line, 1); // Only scale the FIRST number found (usually the quantity)
    }

    protected function parseQuantity(string $val): float
    {
        // Mixed number "1 1/2"
        if (preg_match('/(\d+)\s+(\d+)\/(\d+)/', $val, $m)) {
            return (float)$m[1] + ((float)$m[2] / (float)$m[3]);
        }
        // Fraction "1/2"
        if (preg_match('/(\d+)\/(\d+)/', $val, $m)) {
            return (float)$m[1] / (float)$m[2];
        }
        return (float)$val;
    }

    protected function formatQuantity(float $num): string
    {
        if ($num == (int)$num) return (string)(int)$num;
        
        // Convert back to common fractions if close enough
        $fractions = [0.25 => '1/4', 0.5 => '1/2', 0.75 => '3/4', 0.33 => '1/3', 0.66 => '2/3'];
        $whole = floor($num);
        $decimal = $num - $whole;
        
        foreach ($fractions as $val => $text) {
            if (abs($decimal - $val) < 0.05) {
                return ($whole > 0 ? "$whole " : "") . $text;
            }
        }
        
        return (string)round($num, 2);
    }

    /**
     * Add a manual item to the Paprika shopping list
     */
    public function addItem(string $name, string $quantity = null): bool
    {
        if (!$this->token && !$this->login()) return false;

        $item = [
            'uid' => strtoupper(uuid_create()),
            'name' => $name,
            'quantity' => $quantity,
            'purchased' => false,
            'order_flag' => 0
        ];

        return $this->postSyncData('groceries', [$item]);
    }

    /**
     * Toggle item status
     */
    public function toggleItem(string $uuid, bool $purchased): bool
    {
        if (!$this->token && !$this->login()) return false;

        $item = ShoppingListItem::where('uuid', $uuid)->first();
        if (!$item) return false;

        $data = $item->data;
        $data['purchased'] = $purchased;

        return $this->postSyncData('groceries', [$data]);
    }

    /**
     * Clear all items from the shopping list
     */
    public function clearShoppingList(): bool
    {
        if (!$this->token && !$this->login()) return false;

        $items = ShoppingListItem::all();
        if ($items->isEmpty()) return true;

        $deleteList = $items->map(fn($i) => ['uid' => $i->uuid, 'deleted' => 1])->toArray();

        if ($this->postSyncData('groceries', $deleteList)) {
            ShoppingListItem::truncate();
            return true;
        }

        return false;
    }

    /**
     * Helper to post gzipped data to Paprika sync endpoints
     */
    protected function postSyncData(string $endpoint, array $data): bool
    {
        try {
            $response = Http::withToken($this->token)
                ->withHeaders(['User-Agent' => 'Paprika/3.0.0'])
                ->attach('data', gzencode(json_encode($data)), 'data')
                ->post("{$this->baseUrl}/sync/{$endpoint}/");

            if ($response->successful()) {
                $this->syncShoppingList();
                return true;
            }
            
            Log::error("Paprika sync POST failed for {$endpoint}: " . $response->body());
        } catch (\Exception $e) {
            Log::error("Paprika sync exception: " . $e->getMessage());
        }

        return false;
    }

    protected function updateOrCreateRecipe(array $data): void
    {
        $uuid = $data['uid'] ?? $data['uuid'] ?? null;
        if (!$uuid) return;

        $hash = $data['hash'] ?? null;
        $existing = Recipe::where('uuid', $uuid)->first();

        // INCREMENTAL SYNC: Only fetch full details if hash has changed or doesn't exist locally
        if (!$existing || ($hash && $existing->hash !== $hash)) {
            Log::info("Paprika: Fetching details for recipe: " . ($data['name'] ?? $uuid));
            $fullResponse = Http::withToken($this->token)
                ->withHeaders(['User-Agent' => 'Paprika/3.0.0'])
                ->get("{$this->baseUrl}/sync/recipe/{$uuid}/");

            if ($fullResponse->successful()) {
                $result = $fullResponse->json('result');
                if (is_string($result)) {
                    $data = json_decode(gzdecode(base64_decode($result)), true);
                } else {
                    $data = $result;
                }
            }
        } else {
            // Already up to date, skip detail fetch
            return;
        }

        $categoryNames = [];
        if (isset($data['categories']) && is_array($data['categories'])) {
            foreach ($data['categories'] as $catUid) {
                if (isset($this->categoryMap[$catUid])) {
                    $categoryNames[] = $this->categoryMap[$catUid];
                }
            }
        }

        $categoryString = count($categoryNames) > 0 ? implode(', ', $categoryNames) : null;

        Recipe::updateOrCreate(
            ['uuid' => $uuid],
            [
                'title' => $data['name'] ?? 'Untitled Recipe',
                'hash' => $hash,
                'ingredients' => $data['ingredients'] ?? null,
                'directions' => $data['directions'] ?? null,
                'category' => $categoryString,
                'image_url' => $this->cacheImage($uuid, $data['image_url'] ?? null),
            ]
        );
    }

    /**
     * Sync meal plans for a date range
     */
    public function syncMealPlans(): void
    {
        if (!$this->token && !$this->login()) return;

        $response = Http::withToken($this->token)
            ->withHeaders(['User-Agent' => 'Paprika/3.0.0'])
            ->get("{$this->baseUrl}/sync/meals/");

        if ($response->successful()) {
            $meals = $response->json('result');
            if (is_string($meals)) {
                $meals = json_decode(gzdecode(base64_decode($meals)), true);
            }

            if (is_array($meals)) {
                foreach ($meals as $mealData) {
                    MealPlan::updateOrCreate(
                        ['recipe_uuid' => $mealData['recipe_uid'], 'date' => $mealData['date']],
                        ['type' => $mealData['type']]
                    );
                }
            }
        }
    }

    protected function cacheImage(string $uuid, ?string $url): ?string
    {
        if (!$url) return null;

        $path = "recipes/{$uuid}.jpg";

        if (Storage::disk('public')->exists($path)) {
            return Storage::url($path);
        }

        try {
            $imageResponse = Http::get($url);
            if ($imageResponse->successful()) {
                Storage::disk('public')->put($path, $imageResponse->body());
                return Storage::url($path);
            }
        } catch (\Exception $e) {
            Log::warning("Failed to cache recipe image for {$uuid}: " . $e->getMessage());
        }

        return $url; // Fallback to remote URL
    }
}
