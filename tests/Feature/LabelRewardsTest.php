<?php

use App\Models\Chore;
use App\Models\ChoreCompletion;
use App\Models\Label;
use App\Models\RewardLedger;

test('label reward group completion logic works', function () {
    // Start session and set a mock CSRF token
    $this->session(['_token' => 'test_token']);

    // 1. Create a label group with a monetary reward of $0.50
    $label = Label::create([
        'name' => 'Morning Routine',
        'reward' => '$0.50',
        'is_bankable' => true,
    ]);

    // 2. Create 2 chores under this label, assigned to Emily on all days
    $chore1 = Chore::create([
        'title' => 'Brush teeth',
        'profile' => 'Emily',
        'days' => [0, 1, 2, 3, 4, 5, 6],
        'reward' => null, // no individual reward
        'is_bankable' => true,
        'label_id' => $label->id,
    ]);

    $chore2 = Chore::create([
        'title' => 'Make bed',
        'profile' => 'Emily',
        'days' => [0, 1, 2, 3, 4, 5, 6],
        'reward' => null, // no individual reward
        'is_bankable' => true,
        'label_id' => $label->id,
    ]);

    $date = '2026-06-21'; // A Sunday (dayOfWeek = 0)

    // 3. Complete the first chore
    $response = $this->postJson("/api/chores/{$chore1->id}/toggle", [
        'date' => $date,
        '_token' => 'test_token',
    ]);
    $response->assertStatus(200)->assertJson(['completed' => true]);

    // Completion should be approved directly since the chore has no individual reward
    $completion1 = ChoreCompletion::where('chore_id', $chore1->id)->where('date', $date)->first();
    expect($completion1)->not->toBeNull();
    expect($completion1->status)->toBe('approved');

    // No ledger reward should be created yet (group is incomplete)
    expect(RewardLedger::count())->toBe(0);

    // 4. Complete the second (and last) chore in the group
    $response2 = $this->postJson("/api/chores/{$chore2->id}/toggle", [
        'date' => $date,
        '_token' => 'test_token',
    ]);
    $response2->assertStatus(200)->assertJson(['completed' => true]);

    $completion2 = ChoreCompletion::where('chore_id', $chore2->id)->where('date', $date)->first();
    expect($completion2)->not->toBeNull();
    expect($completion2->status)->toBe('approved');

    // Group is fully completed! A ledger reward should be automatically created.
    // Since it's a monetary reward, it should be pending approval.
    expect(RewardLedger::count())->toBe(1);
    $ledger = RewardLedger::first();
    expect($ledger->profile)->toBe('Emily');
    expect($ledger->source)->toBe('label_reward');
    expect($ledger->type)->toBe('monetary');
    expect((float) $ledger->amount)->toBe(0.50);
    expect($ledger->status)->toBe('pending');
    expect($ledger->reward_text)->toBe('Morning Routine');

    // 5. Toggle off the first chore (user unchecks it)
    $response3 = $this->postJson("/api/chores/{$chore1->id}/toggle", [
        'date' => $date,
        '_token' => 'test_token',
    ]);
    $response3->assertStatus(200)->assertJson(['completed' => false]);

    // The ledger reward should be revoked (deleted) since the group is no longer complete
    expect(RewardLedger::count())->toBe(0);
});

test('textual label rewards start as pending and show up in approvals', function () {
    // Start session and set a mock CSRF token
    $this->session(['_token' => 'test_token']);

    // 1. Create a label group with a textual reward
    $label = Label::create([
        'name' => 'Bedroom chores',
        'reward' => '30 mins game time',
        'is_bankable' => true,
    ]);

    $chore = Chore::create([
        'title' => 'Clean room',
        'profile' => 'Emily',
        'days' => [0, 1, 2, 3, 4, 5, 6],
        'reward' => null,
        'is_bankable' => true,
        'label_id' => $label->id,
    ]);

    $date = '2026-06-21';

    // 2. Complete the only chore in the label group -> group is complete
    $response = $this->postJson("/api/chores/{$chore->id}/toggle", [
        'date' => $date,
        '_token' => 'test_token',
    ]);
    $response->assertStatus(200)->assertJson(['completed' => true]);

    // Textual reward is created as pending
    expect(RewardLedger::count())->toBe(1);
    $ledger = RewardLedger::first();
    expect($ledger->status)->toBe('pending');
    expect($ledger->source)->toBe('label_reward');
    expect($ledger->reward_text)->toBe('30 mins game time|Bedroom chores');

    // 3. Verify it shows up in approvals endpoint
    $responseApprovals = $this->getJson('/api/chores/approvals');
    $responseApprovals->assertStatus(200);
    $data = $responseApprovals->json();

    // Check if label reward is in approvals
    $labelApproval = collect($data)->firstWhere('is_label', true);
    expect($labelApproval)->not->toBeNull();
    expect($labelApproval['chore']['title'])->toBe('Label Reward: Bedroom chores Completed!');
    expect($labelApproval['awarded_value'])->toBe('30 mins game time');

    // 4. Approve the label reward
    $responseProcess = $this->postJson("/api/chores/approvals/label_{$ledger->id}", [
        'action' => 'approve',
        '_token' => 'test_token',
    ]);
    $responseProcess->assertStatus(200);

    // Ledger entry should now be approved and clean
    $ledger->refresh();
    expect($ledger->status)->toBe('approved');
    expect($ledger->reward_text)->toBe('30 mins game time');
});
