<?php

use App\Models\Chore;
use App\Models\ChoreCompletion;
use App\Models\Label;
use App\Models\MobileDevice;
use App\Models\Profile;
use Carbon\Carbon;

beforeEach(function () {
    config(['services.mobile.pairing_code' => '87676297']);
});

function pairedDevice(): MobileDevice
{
    return MobileDevice::create([
        'name' => 'Test iPhone',
        'paired_at' => now(),
        'last_seen_at' => now(),
    ]);
}

test('pairing is rejected when the hub has no pairing code configured', function () {
    config(['services.mobile.pairing_code' => '']);

    $this->postJson('/api/mobile/pair', [
        'code' => '87676297',
        'device_name' => 'Test iPhone',
    ])->assertStatus(503);

    expect(MobileDevice::count())->toBe(0);
});

test('pairing with the wrong code is rejected and creates no device', function () {
    $this->postJson('/api/mobile/pair', [
        'code' => '00000000',
        'device_name' => 'Test iPhone',
    ])->assertStatus(422);

    expect(MobileDevice::count())->toBe(0);
});

test('pairing with the correct code issues a device token', function () {
    $response = $this->postJson('/api/mobile/pair', [
        'code' => '87676297',
        'device_name' => 'Alex iPhone',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['token', 'device' => ['id', 'name']])
        ->assertJsonPath('device.name', 'Alex iPhone');

    expect(MobileDevice::where('name', 'Alex iPhone')->exists())->toBeTrue();
});

test('mobile routes require a valid token', function () {
    $this->getJson('/api/mobile/chores')->assertStatus(401);
    $this->getJson('/api/mobile/bootstrap')->assertStatus(401);
    $this->getJson('/api/mobile/labels')->assertStatus(401);
});

test('a paired token can bootstrap profiles and labels', function () {
    Profile::create(['name' => 'Family', 'is_default' => true, 'order' => 1]);
    Profile::create(['name' => 'Alex', 'order' => 2]);
    Label::create(['name' => 'Morning', 'reward' => '30 min screen time']);

    $token = pairedDevice()->createToken('mobile')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/mobile/bootstrap');

    $response->assertStatus(200)
        ->assertJsonPath('device.name', 'Test iPhone')
        ->assertJsonCount(2, 'profiles')
        ->assertJsonCount(1, 'labels');
});

test('a paired token can create, list, update and delete a chore', function () {
    $token = pairedDevice()->createToken('mobile')->plainTextToken;

    $create = $this->withToken($token)->postJson('/api/mobile/chores', [
        'title' => 'Clean the garage',
        'profile' => 'Alex',
        'days' => [6],
        'is_bankable' => true,
        'reward' => '$5.00',
        'subtasks' => [
            ['title' => 'Sweep the floor', 'order' => 0],
        ],
    ]);

    $create->assertStatus(201)->assertJsonPath('title', 'Clean the garage');
    $choreId = $create->json('id');

    $list = $this->withToken($token)
        ->getJson('/api/mobile/chores?profile=Alex&date='.Carbon::today()->toDateString());
    $list->assertStatus(200);
    expect(collect($list->json())->firstWhere('id', $choreId))->not->toBeNull();

    $this->withToken($token)->putJson("/api/mobile/chores/{$choreId}", [
        'title' => 'Clean the garage thoroughly',
    ])->assertStatus(200)->assertJsonPath('title', 'Clean the garage thoroughly');

    $this->withToken($token)->deleteJson("/api/mobile/chores/{$choreId}")->assertStatus(204);

    expect(Chore::find($choreId))->toBeNull();
});

test('a paired token can toggle a chore completion', function () {
    $token = pairedDevice()->createToken('mobile')->plainTextToken;

    $chore = Chore::create([
        'title' => 'Feed the cat',
        'profile' => 'Emily',
        'days' => [0, 1, 2, 3, 4, 5, 6],
        'is_bankable' => true,
    ]);

    $date = now()->toDateString();

    $this->withToken($token)->postJson("/api/mobile/chores/{$chore->id}/toggle", [
        'date' => $date,
    ])->assertStatus(200)->assertJson(['completed' => true]);

    expect(ChoreCompletion::where('chore_id', $chore->id)->where('date', $date)->exists())->toBeTrue();
});

test('a paired token can manage labels', function () {
    $token = pairedDevice()->createToken('mobile')->plainTextToken;

    $create = $this->withToken($token)->postJson('/api/mobile/labels', [
        'name' => 'Evening',
        'reward' => 'Movie night',
        'is_bankable' => true,
    ]);

    $create->assertStatus(201)->assertJsonPath('name', 'Evening');
    $labelId = $create->json('id');

    $this->withToken($token)->putJson("/api/mobile/labels/{$labelId}", [
        'name' => 'Evening Routine',
    ])->assertStatus(200)->assertJsonPath('name', 'Evening Routine');

    $this->withToken($token)->deleteJson("/api/mobile/labels/{$labelId}")->assertStatus(204);
});

test('revoking a device token blocks further requests', function () {
    $device = pairedDevice();
    $token = $device->createToken('mobile')->plainTextToken;

    $this->withToken($token)->deleteJson('/api/mobile/device')->assertStatus(200);

    expect($device->tokens()->count())->toBe(0);

    app('auth')->forgetGuards();

    $this->withToken($token)->getJson('/api/mobile/bootstrap')->assertStatus(401);
});

test('the existing unauthenticated kiosk route still works', function () {
    $this->session(['_token' => 'test_token']);

    $this->postJson('/api/chores', [
        '_token' => 'test_token',
        'title' => 'Kiosk chore',
        'profile' => 'Family',
        'is_bankable' => true,
    ])->assertStatus(201);
});
