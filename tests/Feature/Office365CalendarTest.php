<?php

use App\Services\Calendar\Office365CalendarService;

test('it can be instantiated', function () {
    $service = new Office365CalendarService;
    expect($service)->toBeInstanceOf(Office365CalendarService::class);
});

test('it returns an empty array if credentials are missing', function () {
    config([
        'services.microsoft.client_id' => null,
        'services.microsoft.tenant_id' => null,
        'services.microsoft.client_secret' => null,
        'services.microsoft.user_id' => null,
    ]);

    $service = new Office365CalendarService;
    expect($service->getEvents())->toBe([]);
});
