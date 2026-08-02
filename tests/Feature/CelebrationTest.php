<?php

use App\Models\Celebration;

it('can list celebrations', function () {
    Celebration::factory()->create(['message' => 'Test', 'is_active' => true]);

    $response = $this->get('/api/celebrations');

    $response->assertStatus(200)
        ->assertJsonCount(1);
});

it('can create a celebration', function () {
    $response = $this->postJson('/api/celebrations', [
        'message' => 'Happy Birthday Henry!',
        'background' => 'confetti',
        'font' => 'display',
        'font_color' => '#ffffff',
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('celebrations', ['message' => 'Happy Birthday Henry!']);
});

it('only allows one active celebration at a time', function () {
    $first = Celebration::factory()->create(['message' => 'First', 'is_active' => true]);
    Celebration::factory()->create(['message' => 'Second']);

    $response = $this->postJson('/api/celebrations', [
        'message' => 'Third',
        'background' => 'sunset',
        'font' => 'serif',
        'font_color' => '#ffffff',
        'is_active' => true,
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('celebrations', ['message' => 'Third', 'is_active' => 1]);
    $this->assertDatabaseMissing('celebrations', ['id' => $first->id, 'is_active' => 1]);
});

it('can update a celebration', function () {
    $celebration = Celebration::factory()->create(['message' => 'Old Title']);

    $response = $this->putJson("/api/celebrations/{$celebration->id}", [
        'message' => 'Updated Title',
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('celebrations', ['message' => 'Updated Title']);
});

it('can delete a celebration', function () {
    $celebration = Celebration::factory()->create();

    $response = $this->delete("/api/celebrations/{$celebration->id}");

    $response->assertStatus(204);
    $this->assertDatabaseMissing('celebrations', ['id' => $celebration->id]);
});
