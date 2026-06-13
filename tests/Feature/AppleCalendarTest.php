<?php

use App\Services\Calendar\AppleCalendarService;

test('it can be instantiated', function () {
    $service = new AppleCalendarService;
    expect($service)->toBeInstanceOf(AppleCalendarService::class);
});

test('it returns an empty array if credentials are missing', function () {
    config(['services.apple.email' => null]);
    config(['services.apple.password' => null]);

    $service = new AppleCalendarService;
    expect($service->getEvents())->toBe([]);
});
