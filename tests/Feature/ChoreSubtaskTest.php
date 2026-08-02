<?php

use App\Models\Chore;
use App\Models\ChoreCompletion;
use App\Models\ChoreSubtask;
use App\Models\RewardLedger;

test('a chore can be created with subtasks', function () {
    $this->session(['_token' => 'test_token']);

    $response = $this->postJson('/api/chores', [
        '_token' => 'test_token',
        'title' => 'Clean the garage',
        'profile' => 'Alex',
        'days' => [6],
        'is_bankable' => true,
        'subtasks' => [
            ['title' => 'Sweep the floor', 'order' => 0],
            ['title' => 'Organize tools', 'order' => 1],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('subtasks.0.title', 'Sweep the floor')
        ->assertJsonPath('subtasks.1.title', 'Organize tools');

    $chore = Chore::where('title', 'Clean the garage')->first();
    expect($chore->subtasks)->toHaveCount(2);
    expect($chore->subtasks->first()->order)->toBe(0);
});

test('updating a chore syncs subtasks and preserves ids', function () {
    $this->session(['_token' => 'test_token']);

    $chore = Chore::create([
        'title' => 'Make lunch',
        'profile' => 'Emily',
        'days' => [1, 2, 3, 4, 5],
        'is_bankable' => true,
    ]);

    $st1 = $chore->subtasks()->create(['title' => 'Wash hands', 'order' => 0]);
    $chore->subtasks()->create(['title' => 'Clean up', 'order' => 1]);

    // Rename existing, drop the second, add a new one
    $response = $this->putJson("/api/chores/{$chore->id}", [
        '_token' => 'test_token',
        'title' => 'Make lunch',
        'profile' => 'Emily',
        'subtasks' => [
            ['id' => $st1->id, 'title' => 'Wash hands thoroughly', 'order' => 0],
            ['title' => 'Pack snack', 'order' => 1],
        ],
    ]);

    $response->assertStatus(200);

    $chore->refresh();
    expect($chore->subtasks)->toHaveCount(2);
    expect(ChoreSubtask::where('id', $st1->id)->first()->title)
        ->toBe('Wash hands thoroughly');
    expect($chore->subtasks->pluck('title'))->toContain('Pack snack');
    expect(ChoreSubtask::where('chore_id', $chore->id)->where('title', 'Clean up')->exists())->toBeFalse();
});

test('removing all subtasks via update deletes them', function () {
    $this->session(['_token' => 'test_token']);

    $chore = Chore::create([
        'title' => 'Tidy room',
        'profile' => 'Henry',
        'days' => [0, 6],
        'is_bankable' => true,
    ]);
    $chore->subtasks()->create(['title' => 'Make bed', 'order' => 0]);

    $this->putJson("/api/chores/{$chore->id}", [
        '_token' => 'test_token',
        'title' => 'Tidy room',
        'profile' => 'Henry',
        'subtasks' => [],
    ])->assertStatus(200);

    expect($chore->subtasks()->count())->toBe(0);
});

test('toggling a partial subtask list marks the chore in progress, not completed', function () {
    $this->session(['_token' => 'test_token']);

    $chore = Chore::create([
        'title' => 'Do homework',
        'profile' => 'Emily',
        'days' => [1, 2, 3, 4, 5],
        'is_bankable' => true,
    ]);
    $st1 = $chore->subtasks()->create(['title' => 'Math', 'order' => 0]);
    $st2 = $chore->subtasks()->create(['title' => 'Reading', 'order' => 1]);

    $date = '2026-06-23';
    $response = $this->postJson("/api/chores/{$chore->id}/toggle", [
        '_token' => 'test_token',
        'date' => $date,
        'subtask_ids' => [$st1->id],
    ]);

    $response->assertStatus(200)->assertJson([
        'completed' => false,
        'progress' => 0.5,
    ]);

    $completion = ChoreCompletion::where('chore_id', $chore->id)->where('date', $date)->first();
    expect($completion)->not->toBeNull();
    expect($completion->status)->toBe('in_progress');
    expect($completion->completed_subtasks)->toBe([$st1->id]);
});

test('toggling all subtasks completes the chore and awards the reward', function () {
    $this->session(['_token' => 'test_token']);

    $chore = Chore::create([
        'title' => 'Do homework',
        'profile' => 'Emily',
        'days' => [1, 2, 3, 4, 5],
        'reward' => '30 min screen time',
        'is_bankable' => false,
    ]);
    $st1 = $chore->subtasks()->create(['title' => 'Math', 'order' => 0]);
    $st2 = $chore->subtasks()->create(['title' => 'Reading', 'order' => 1]);

    $date = '2026-06-23';
    $response = $this->postJson("/api/chores/{$chore->id}/toggle", [
        '_token' => 'test_token',
        'date' => $date,
        'subtask_ids' => [$st1->id, $st2->id],
    ]);

    $response->assertStatus(200)->assertJson(['completed' => true]);

    $completion = ChoreCompletion::where('chore_id', $chore->id)->where('date', $date)->first();
    expect($completion->status)->toBe('approved');
    expect($completion->completed_subtasks)->toBe([$st1->id, $st2->id]);

    // Non-bankable textual rewards are auto-approved and banked
    $ledger = RewardLedger::where('chore_completion_id', $completion->id)->first();
    expect($ledger)->not->toBeNull();
    expect($ledger->type)->toBe('textual');
    expect($ledger->reward_text)->toBe('30 min screen time');
});

test('unchecking every subtask removes the completion and revokes the reward', function () {
    $this->session(['_token' => 'test_token']);

    $chore = Chore::create([
        'title' => 'Do homework',
        'profile' => 'Emily',
        'days' => [1, 2, 3, 4, 5],
        'reward' => '30 min screen time',
        'is_bankable' => false,
    ]);
    $st1 = $chore->subtasks()->create(['title' => 'Math', 'order' => 0]);
    $st2 = $chore->subtasks()->create(['title' => 'Reading', 'order' => 1]);

    $date = '2026-06-23';

    $this->postJson("/api/chores/{$chore->id}/toggle", [
        '_token' => 'test_token',
        'date' => $date,
        'subtask_ids' => [$st1->id, $st2->id],
    ])->assertStatus(200);

    expect(ChoreCompletion::where('chore_id', $chore->id)->where('date', $date)->exists())->toBeTrue();
    expect(RewardLedger::where('source', 'chore_completion')->count())->toBe(1);

    // Uncheck one -> full to partial -> reward revoked
    $this->postJson("/api/chores/{$chore->id}/toggle", [
        '_token' => 'test_token',
        'date' => $date,
        'subtask_ids' => [$st1->id],
    ])->assertStatus(200)->assertJson(['completed' => false]);

    expect(RewardLedger::where('source', 'chore_completion')->count())->toBe(0);

    $completion = ChoreCompletion::where('chore_id', $chore->id)->where('date', $date)->first();
    expect($completion->status)->toBe('in_progress');

    // Uncheck everything -> completion removed
    $this->postJson("/api/chores/{$chore->id}/toggle", [
        '_token' => 'test_token',
        'date' => $date,
        'subtask_ids' => [],
    ])->assertStatus(200)->assertJson(['completed' => false]);

    expect(ChoreCompletion::where('chore_id', $chore->id)->where('date', $date)->exists())->toBeFalse();
});

test('chores without subtasks keep the original toggle behavior', function () {
    $this->session(['_token' => 'test_token']);

    $chore = Chore::create([
        'title' => 'Feed the cat',
        'profile' => 'Emily',
        'days' => [0, 1, 2, 3, 4, 5, 6],
        'is_bankable' => true,
    ]);

    $date = '2026-06-23';
    $this->postJson("/api/chores/{$chore->id}/toggle", [
        '_token' => 'test_token',
        'date' => $date,
    ])->assertStatus(200)->assertJson(['completed' => true]);

    $this->postJson("/api/chores/{$chore->id}/toggle", [
        '_token' => 'test_token',
        'date' => $date,
    ])->assertStatus(200)->assertJson(['completed' => false]);

    expect(ChoreCompletion::where('chore_id', $chore->id)->where('date', $date)->exists())->toBeFalse();
});

test('index returns subtasks and completed state for the given date', function () {
    $this->session(['_token' => 'test_token']);

    $chore = Chore::create([
        'title' => 'Do homework',
        'profile' => 'Emily',
        'days' => [1, 2, 3, 4, 5],
        'is_bankable' => true,
    ]);
    $st1 = $chore->subtasks()->create(['title' => 'Math', 'order' => 0]);
    $st2 = $chore->subtasks()->create(['title' => 'Reading', 'order' => 1]);

    $date = '2026-06-23';

    // Partially done -> not completed
    $this->postJson("/api/chores/{$chore->id}/toggle", [
        '_token' => 'test_token',
        'date' => $date,
        'subtask_ids' => [$st1->id],
    ]);

    $response = $this->getJson("/api/chores?date={$date}&profile=Emily");
    $response->assertStatus(200);
    $this->assertTrue(collect($response->json())->contains(function ($c) use ($st1) {
        return $c['id'] === $st1->chore_id
            && $c['completed'] === false
            && count($c['subtasks']) === 2
            && $c['completed_subtasks'] === [$st1->id];
    }));

    // All done -> completed
    $this->postJson("/api/chores/{$chore->id}/toggle", [
        '_token' => 'test_token',
        'date' => $date,
        'subtask_ids' => [$st1->id, $st2->id],
    ]);

    $response2 = $this->getJson("/api/chores?date={$date}&profile=Emily");
    $this->assertTrue(collect($response2->json())->contains(fn ($c) => $c['id'] === $chore->id && $c['completed'] === true));
});
