<?php

use App\Models\Calendar;
use App\Models\Profile;
use App\Models\Tab;
use App\Services\Calendar\AppleCalendarService;
use App\Services\Calendar\CalendarManager;
use App\Services\Calendar\GoogleCalendarService;
use App\Services\Calendar\ICalSubscriptionService;
use App\Services\Calendar\Office365CalendarService;

beforeEach(function () {
    $this->apple = Mockery::mock(AppleCalendarService::class);
    $this->google = Mockery::mock(GoogleCalendarService::class);
    $this->office = Mockery::mock(Office365CalendarService::class);
    $this->ical = Mockery::mock(ICalSubscriptionService::class);

    $this->manager = new CalendarManager(
        $this->apple,
        $this->google,
        $this->office,
        $this->ical
    );
});

test('it syncs a calendar if it needs sync', function () {
    $profile = Profile::create(['name' => 'Family']);
    $tab = Tab::create(['name' => 'General', 'profile_id' => $profile->id]);
    $calendar = Calendar::create([
        'tab_id' => $tab->id,
        'provider' => 'apple',
        'external_id' => 'personal',
        'refresh_rate' => 0, // Force sync
    ]);

    $this->apple->shouldReceive('getEvents')->once()->andReturn([
        [
            'id' => 'evt_1',
            'title' => 'Test Event',
            'start' => now()->toIso8601String(),
            'end' => now()->addHour()->toIso8601String(),
        ],
    ]);

    $events = $this->manager->getEventsForTab($tab);

    expect($events)->toHaveCount(1);
    expect($events->first()->title)->toBe('Test Event');
    expect($calendar->fresh()->last_synced_at)->not->toBeNull();
});

test('it does not sync if last_synced_at is recent', function () {
    $profile = Profile::create(['name' => 'Family']);
    $tab = Tab::create(['name' => 'General', 'profile_id' => $profile->id]);
    $calendar = Calendar::create([
        'tab_id' => $tab->id,
        'provider' => 'apple',
        'external_id' => 'personal',
        'refresh_rate' => 60,
        'last_synced_at' => now(),
    ]);

    $this->apple->shouldNotReceive('getEvents');

    $events = $this->manager->getEventsForTab($tab);

    expect($events)->toHaveCount(0);
});
