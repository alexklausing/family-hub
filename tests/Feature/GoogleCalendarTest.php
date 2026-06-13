<?php

use App\Services\Calendar\GoogleCalendarService;

test('it can be instantiated', function () {
    $service = new GoogleCalendarService;
    expect($service)->toBeInstanceOf(GoogleCalendarService::class);
});

test('it returns an empty array if no calendar ids are configured', function () {
    config(['services.google.calendar_id' => null]);

    $service = new GoogleCalendarService;
    expect($service->getEvents())->toBe([]);
});
