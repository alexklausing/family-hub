<?php

use App\Services\Calendar\ICalSubscriptionService;
use Illuminate\Support\Facades\Http;

test('it can be instantiated', function () {
    $service = new ICalSubscriptionService;
    expect($service)->toBeInstanceOf(ICalSubscriptionService::class);
});

test('it returns an empty array if no subscriptions are configured', function () {
    config(['services.calendar.subscriptions' => null]);

    $service = new ICalSubscriptionService;
    expect($service->getEvents())->toBe([]);
});

test('it converts webcal to https', function () {
    config(['services.calendar.subscriptions' => 'webcal://example.com/cal.ics']);

    Http::fake([
        'https://example.com/cal.ics' => Http::response("BEGIN:VCALENDAR\nEND:VCALENDAR", 200),
    ]);

    $service = new ICalSubscriptionService;
    $service->getEvents();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://example.com/cal.ics';
    });
});
