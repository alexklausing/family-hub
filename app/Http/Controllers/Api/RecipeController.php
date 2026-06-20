<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncPaprikaRecipes;
use App\Models\Recipe;
use App\Services\PaprikaSyncService;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    public function __construct(
        protected PaprikaSyncService $paprikaService
    ) {}

    public function index(Request $request)
    {
        // Dispatch background sync if requested
        if ($request->has('sync')) {
            SyncPaprikaRecipes::dispatch();

            return response()->json(['message' => 'Sync initiated in background'], 202);
        }

        $recipes = Recipe::query()
            ->when($request->query('category'), fn ($q, $cat) => $q->where('category', 'like', "%{$cat}%"))
            ->when($request->query('search'), fn ($q, $search) => $q->where('title', 'like', "%{$search}%"))
            ->orderBy('title')
            ->paginate(24);

        return response()->json($recipes);
    }

    public function show(Recipe $recipe)
    {
        return response()->json($recipe);
    }

    public function categories()
    {
        $rawCategories = Recipe::whereNotNull('category')
            ->distinct()
            ->pluck('category');

        $allCategories = collect();
        foreach ($rawCategories as $raw) {
            $parts = explode(', ', $raw);
            foreach ($parts as $part) {
                $allCategories->push(trim($part));
            }
        }

        $categories = $allCategories->unique()
            ->sort()
            ->values();

        return response()->json($categories);
    }
}
