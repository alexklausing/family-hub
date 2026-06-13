<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShoppingListItem;
use App\Services\PaprikaSyncService;
use Illuminate\Http\Request;

class ShoppingListController extends Controller
{
    public function __construct(
        protected PaprikaSyncService $paprikaService
    ) {}

    public function index(Request $request)
    {
        if ($request->has('sync') || ShoppingListItem::count() === 0) {
            \App\Jobs\SyncPaprikaShoppingList::dispatch();
            if ($request->has('sync')) {
                return response()->json(['message' => 'Sync initiated in background'], 202);
            }
        }

        $items = ShoppingListItem::query()
            ->orderBy('purchased')
            ->orderBy('aisle')
            ->orderBy('name')
            ->get()
            ->groupBy('aisle');

        return response()->json($items);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'quantity' => 'nullable|string',
        ]);

        $success = $this->paprikaService->addItem(
            $request->name,
            $request->quantity
        );

        return response()->json(['success' => $success]);
    }

    public function toggle(Request $request, ShoppingListItem $item)
    {
        $request->validate([
            'purchased' => 'required|boolean',
        ]);

        $success = $this->paprikaService->toggleItem(
            $item->uuid,
            $request->purchased
        );

        return response()->json(['success' => $success]);
    }

    public function addRecipe(Request $request)
    {
        $request->validate([
            'recipe_uuid' => 'required|string',
            'scale' => 'nullable|numeric',
        ]);

        $success = $this->paprikaService->addRecipeToShoppingList(
            $request->recipe_uuid, 
            (float)($request->scale ?? 1.0)
        );

        return response()->json(['success' => $success]);
    }

    public function destroyAll()
    {
        $success = $this->paprikaService->clearShoppingList();

        return response()->json(['success' => $success]);
    }
}
