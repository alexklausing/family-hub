<?php

use App\Models\Countdown;


it('can list countdowns', function () {
    Countdown::factory()->create(['title' => 'Test', 'target_date' => '2026-10-01']);

    $response = $this->get('/api/countdowns');

    $response->assertStatus(200)
             ->assertJsonCount(1);
});

it('can create a countdown', function () {
    $response = $this->postJson('/api/countdowns', [
        'title' => 'Updated Title',
        'target_date' => '2027-01-01',
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('countdowns', ['title' => 'Updated Title']);
});

it('can update a countdown', function () {
    $countdown = Countdown::factory()->create(['title' => 'Old Title']);

    $response = $this->putJson("/api/countdowns/{$countdown->id}", [
        'title' => 'Updated Title'
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('countdowns', ['title' => 'Updated Title']);
});

it('can delete a countdown', function () {
    $countdown = Countdown::factory()->create();

    $response = $this->delete("/api/countdowns/{$countdown->id}");

    $response->assertStatus(204);
    $this->assertDatabaseMissing('countdowns', ['id' => $countdown->id]);
});
