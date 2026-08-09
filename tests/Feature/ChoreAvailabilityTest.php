<?php

use App\Models\Chore;
use App\Models\Label;

test('a chore can be created with an available_from time', function () {
    $this->session(['_token' => 'test_token']);

    $response = $this->postJson('/api/chores', [
        '_token' => 'test_token',
        'title' => 'Screen time',
        'profile' => 'Alex',
        'days' => [1, 2, 3, 4, 5],
        'available_from' => '15:30',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('available_from', '15:30');

    $chore = Chore::where('title', 'Screen time')->first();
    expect($chore->available_from)->toBe('15:30');
});

test('updating a chore can set and clear available_from', function () {
    $this->session(['_token' => 'test_token']);

    $chore = Chore::create([
        'title' => 'Homework',
        'profile' => 'Emily',
        'days' => [1, 2, 3, 4, 5],
        'is_bankable' => true,
    ]);

    $this->putJson("/api/chores/{$chore->id}", [
        '_token' => 'test_token',
        'available_from' => '16:00',
    ])->assertStatus(200)
        ->assertJsonPath('available_from', '16:00');

    $this->putJson("/api/chores/{$chore->id}", [
        '_token' => 'test_token',
        'available_from' => null,
    ])->assertStatus(200)
        ->assertJsonPath('available_from', null);
});

test('a label can be created with an available_from time', function () {
    $this->session(['_token' => 'test_token']);

    $response = $this->postJson('/api/labels', [
        '_token' => 'test_token',
        'name' => 'Screen Time',
        'available_from' => '15:30',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('available_from', '15:30');

    expect(Label::where('name', 'Screen Time')->first()->available_from)->toBe('15:30');
});

test('updating a label can set available_from', function () {
    $this->session(['_token' => 'test_token']);

    $label = Label::create(['name' => 'Morning']);

    $this->putJson("/api/labels/{$label->id}", [
        '_token' => 'test_token',
        'available_from' => '07:00',
    ])->assertStatus(200)
        ->assertJsonPath('available_from', '07:00');
});

test('a chore is hidden from the index until available_from passes', function () {
    $this->session(['_token' => 'test_token']);

    $chore = Chore::create([
        'title' => 'Late chore',
        'profile' => 'Alex',
        'days' => [0, 1, 2, 3, 4, 5, 6],
        'available_from' => '23:59',
    ]);

    $response = $this->getJson('/api/chores?profile=Alex');

    $response->assertStatus(200);
    $this->assertTrue(collect($response->json())->contains(fn ($c) => $c['id'] === $chore->id));
});

test('a chore with a label available_from is locked until the label time', function () {
    Carbon\Carbon::setTestNow('2026-06-23 14:00:00'); // Tue 2pm

    $this->session(['_token' => 'test_token']);

    $label = Label::create(['name' => 'Evening', 'available_from' => '18:00']);
    $chore = Chore::create([
        'title' => 'Piano',
        'profile' => 'Alex',
        'days' => [0, 1, 2, 3, 4, 5, 6],
        'label_id' => $label->id,
    ]);

    // Before unlock -> 403
    $this->postJson("/api/chores/{$chore->id}/toggle", [
        '_token' => 'test_token',
        'date' => '2026-06-23',
    ])->assertStatus(403);

    // After unlock -> completes
    Carbon\Carbon::setTestNow('2026-06-23 18:01:00');
    $this->postJson("/api/chores/{$chore->id}/toggle", [
        '_token' => 'test_token',
        'date' => '2026-06-23',
    ])->assertStatus(200)
        ->assertJson(['completed' => true]);

    Carbon\Carbon::setTestNow();
});

test('the latest of chore and label available_from wins', function () {
    Carbon\Carbon::setTestNow('2026-06-23 16:00:00'); // Tue 4pm

    $this->session(['_token' => 'test_token']);

    $label = Label::create(['name' => 'Group', 'available_from' => '15:00']);
    $chore = Chore::create([
        'title' => 'Strict chore',
        'profile' => 'Alex',
        'days' => [0, 1, 2, 3, 4, 5, 6],
        'available_from' => '17:00',
        'label_id' => $label->id,
    ]);

    // Chore-level 17:00 is later than label 15:00 -> still locked at 16:00
    $this->postJson("/api/chores/{$chore->id}/toggle", [
        '_token' => 'test_token',
        'date' => '2026-06-23',
    ])->assertStatus(403);

    Carbon\Carbon::setTestNow();
});

test('cloning a group copies available_from', function () {
    $this->session(['_token' => 'test_token']);

    $label = Label::create(['name' => 'Group']);
    $source = Chore::create([
        'title' => 'Tidy room',
        'profile' => 'Alex',
        'days' => [1, 2, 3],
        'available_from' => '14:00',
        'label_id' => $label->id,
    ]);

    $response = $this->postJson('/api/chores/clone-group', [
        '_token' => 'test_token',
        'from_profile' => 'Alex',
        'to_profile' => 'Emily',
        'label_id' => $label->id,
        'mode' => 'replace',
    ]);

    $response->assertStatus(200);

    $clone = Chore::where('profile', 'Emily')->where('title', 'Tidy room')->first();
    expect($clone->available_from)->toBe('14:00');
});
