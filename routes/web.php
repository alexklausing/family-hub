<?php

use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\CalendarManagementController;
use App\Http\Controllers\Api\RecipeController;
use App\Http\Controllers\Api\ShoppingListController;
use App\Http\Controllers\Api\WeatherController;
use App\Http\Controllers\ChoreController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\RewardLedgerController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api/events', [CalendarController::class, 'index']);
Route::post('/api/sync/calendars', [CalendarController::class, 'sync']);
Route::post('/api/calendars/{calendar}/events', [CalendarController::class, 'storeEvent']);
Route::post('/api/calendars/apple/fetch', [CalendarManagementController::class, 'fetchAppleCalendars']);
Route::post('/api/calendars', [CalendarManagementController::class, 'store']);
Route::put('/api/calendars/{calendar}', [CalendarManagementController::class, 'update']);
Route::delete('/api/calendars/{calendar}', [CalendarManagementController::class, 'destroy']);
Route::post('/api/profiles/{name}/default-calendar', [CalendarManagementController::class, 'setDefaultCalendar']);
Route::post('/api/profiles/{name}/visible-calendars', [CalendarManagementController::class, 'updateVisibleCalendars']);


Route::get('/api/weather', [WeatherController::class, 'index']);

Route::get('/api/aura', [\App\Http\Controllers\Api\AuraFramesController::class, 'index']);

Route::get('/api/recipes', [RecipeController::class, 'index']);
Route::get('/api/recipes/categories', [RecipeController::class, 'categories']);
Route::get('/api/recipes/menu', [RecipeController::class, 'menu']);
Route::get('/api/recipes/{recipe:uuid}', [RecipeController::class, 'show']);
Route::post('/api/recipes/{recipe:uuid}/plan', [RecipeController::class, 'plan']);

Route::get('/api/shopping-list', [ShoppingListController::class, 'index']);
Route::post('/api/shopping-list', [ShoppingListController::class, 'store']);
Route::delete('/api/shopping-list', [ShoppingListController::class, 'destroyAll']);
Route::post('/api/shopping-list/{item}/toggle', [ShoppingListController::class, 'toggle']);
Route::post('/api/shopping-list/add-recipe', [ShoppingListController::class, 'addRecipe']);

Route::get('/api/chores/approvals', [ChoreController::class, 'approvals']);
Route::post('/api/chores/approvals/{completion}', [ChoreController::class, 'processApproval']);

Route::get('/api/chores', [ChoreController::class, 'index']);
Route::post('/api/chores', [ChoreController::class, 'store']);
Route::put('/api/chores/{chore}', [ChoreController::class, 'update']);
Route::delete('/api/chores/{chore}', [ChoreController::class, 'destroy']);
Route::post('/api/chores/{chore}/toggle', [ChoreController::class, 'toggle']);
Route::post('/api/chores/clone-group', [ChoreController::class, 'cloneGroup']);

Route::get('/api/labels', [LabelController::class, 'index']);
Route::post('/api/labels', [LabelController::class, 'store']);
Route::put('/api/labels/{label}', [LabelController::class, 'update']);
Route::delete('/api/labels/{label}', [LabelController::class, 'destroy']);

Route::get('/api/rewards/bank', [RewardLedgerController::class, 'index']);
Route::post('/api/rewards/redeem', [RewardLedgerController::class, 'redeem']);

Route::get('/api/kiosk/version', function () {
    return response()->json(['version' => Cache::get('kiosk_version', 1)]);
});

Route::post('/api/kiosk/refresh', function () {
    if (Cache::has('kiosk_version')) {
        Cache::increment('kiosk_version');
    } else {
        Cache::put('kiosk_version', 2);
    }
    return response()->json(['message' => 'Refresh signal sent']);
});

// Auto-patch Vite public/hot for Kiosk and Remote Mac access
if (file_exists(public_path('hot'))) {
    $hotContent = file_get_contents(public_path('hot'));
    if (strpos($hotContent, '127.0.0.1') !== false) {
        file_put_contents(public_path('hot'), str_replace('127.0.0.1', '192.168.4.140', $hotContent));
    }
}
