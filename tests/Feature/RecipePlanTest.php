<?php

use App\Models\Recipe;
use App\Services\PaprikaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

it('can plan a meal to menu', function () {
    $recipe = Recipe::create([
        'uuid' => 'test-uuid-123',
        'title' => 'Test Recipe',
        'hash' => 'hash123',
    ]);

    $this->mock(PaprikaSyncService::class, function (MockInterface $mock) use ($recipe) {
        $mock->shouldReceive('addMealToMenu')
            ->once()
            ->with($recipe->uuid, '2025-01-01', 2)
            ->andReturn(true);
            
        $mock->shouldReceive('addRecipeToShoppingList')
            ->never();
    });

    $response = $this->postJson("/api/recipes/{$recipe->uuid}/plan", [
        'date' => '2025-01-01',
        'type' => 2,
        'add_to_menu' => true,
        'add_to_shopping_list' => false,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Recipe planned successfully',
            'results' => [
                'menu' => true,
                'shopping_list' => false,
            ]
        ]);
});

it('can add a recipe to shopping list', function () {
    $recipe = Recipe::create([
        'uuid' => 'test-uuid-456',
        'title' => 'Test Recipe 2',
        'hash' => 'hash456',
    ]);

    $this->mock(PaprikaSyncService::class, function (MockInterface $mock) use ($recipe) {
        $mock->shouldReceive('addRecipeToShoppingList')
            ->once()
            ->with($recipe->uuid, 2.0)
            ->andReturn(true);
            
        $mock->shouldReceive('addMealToMenu')
            ->never();
    });

    $response = $this->postJson("/api/recipes/{$recipe->uuid}/plan", [
        'add_to_menu' => false,
        'add_to_shopping_list' => true,
        'scale' => 2.0,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'results' => [
                'menu' => false,
                'shopping_list' => true,
            ]
        ]);
});
