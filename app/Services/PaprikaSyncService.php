<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\MealPlan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PaprikaSyncService
{
    protected string $baseUrl = 'https://www.paprikaapp.com/api/v2';
    protected ?string $token = null;

    /**
     * Authenticate with Paprika API
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
            $response = Http::asForm()->post("{$this->baseUrl}/account/login/", [
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
     * Sync all recipes from Paprika
     */
    public function syncRecipes(): void
    {
        if (!$this->token && !$this->login()) return;

        $response = Http::withToken($this->token)->get("{$this->baseUrl}/sync/recipes/");
        
        if ($response->successful()) {
            $recipes = $response->json('result');
            
            foreach ($recipes as $recipeData) {
                $this->updateOrCreateRecipe($recipeData);
            }
        }
    }

    /**
     * Sync meal plans for a date range
     */
    public function syncMealPlans(): void
    {
        if (!$this->token && !$this->login()) return;

        $response = Http::withToken($this->token)->get("{$this->baseUrl}/sync/meals/");

        if ($response->successful()) {
            $meals = $response->json('result');

            foreach ($meals as $mealData) {
                MealPlan::updateOrCreate(
                    ['recipe_uuid' => $mealData['recipe_uid'], 'date' => $mealData['date']],
                    ['type' => $mealData['type']]
                );
            }
        }
    }

    protected function updateOrCreateRecipe(array $data): void
    {
        // Fetch full recipe detail if needed
        $recipe = Recipe::updateOrCreate(
            ['uuid' => $data['uid']],
            [
                'title' => $data['name'],
                'ingredients' => $data['ingredients'] ?? null,
                'directions' => $data['directions'] ?? null,
                'category' => $data['category'] ?? null,
                'image_url' => $this->cacheImage($data['uid'], $data['image_url'] ?? null),
            ]
        );
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
