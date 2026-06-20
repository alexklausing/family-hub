<?php

use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\CalendarManagementController;
use App\Http\Controllers\Api\RecipeController;
use App\Http\Controllers\Api\ShoppingListController;
use App\Http\Controllers\Api\WeatherController;
use Illuminate\Support\Facades\Route;

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

Route::get('/api/weather', [WeatherController::class, 'index']);

Route::get('/api/recipes', [RecipeController::class, 'index']);
Route::get('/api/recipes/categories', [RecipeController::class, 'categories']);
Route::get('/api/recipes/{recipe}', [RecipeController::class, 'show']);

Route::get('/api/shopping-list', [ShoppingListController::class, 'index']);
Route::post('/api/shopping-list', [ShoppingListController::class, 'store']);
Route::delete('/api/shopping-list', [ShoppingListController::class, 'destroyAll']);
Route::post('/api/shopping-list/{item}/toggle', [ShoppingListController::class, 'toggle']);
Route::post('/api/shopping-list/add-recipe', [ShoppingListController::class, 'addRecipe']);
