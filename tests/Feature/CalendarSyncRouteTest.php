<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Support\Facades\Artisan;

test('the all-data sync route runs the sync:all command', function () {
    $kernel = Mockery::mock(ConsoleKernelContract::class);
    $kernel->shouldReceive('call')->once()->with('sync:all');

    $this->app->instance(ConsoleKernelContract::class, $kernel);
    Artisan::clearResolvedInstance(ConsoleKernelContract::class);

    $response = $this->postJson('/api/sync/all');

    $response->assertOk();
    $response->assertJson(['message' => 'Sync complete']);
});

test('the calendars sync route returns a completion message', function () {
    $response = $this->postJson('/api/sync/calendars');

    $response->assertOk();
    $response->assertJson(['message' => 'Sync complete']);
});
