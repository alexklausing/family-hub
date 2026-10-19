<?php

use App\Models\Calendar;
use App\Models\Profile;
use Database\Seeders\PaprikaMenuPlanCalendarSeeder;

test('it seeds the Paprika menu plan calendar and preserves Family visibility', function () {
    config(['services.paprika.menu_plan_calendar_url' => 'https://example.com/paprika.ics']);

    $profile = Profile::create([
        'name' => 'Family',
        'is_default' => true,
        'visible_calendars' => [99],
    ]);

    $this->seed(PaprikaMenuPlanCalendarSeeder::class);
    $this->seed(PaprikaMenuPlanCalendarSeeder::class);

    $calendar = Calendar::sole();

    expect($calendar->name)->toBe('Paprika Menu Plan')
        ->and($calendar->provider)->toBe('ical')
        ->and($calendar->external_id)->toBe('paprika_menu_plan')
        ->and($calendar->refresh_rate)->toBe(15)
        ->and($calendar->credentials)->toBe([
            'url' => 'https://example.com/paprika.ics',
        ])
        ->and($calendar->tab->name)->toBe('Calendar')
        ->and($profile->fresh()->visible_calendars)->toBe([99, $calendar->id]);
});

test('it skips seeding when the Paprika menu plan URL is not configured', function () {
    config(['services.paprika.menu_plan_calendar_url' => null]);

    $this->seed(PaprikaMenuPlanCalendarSeeder::class);

    expect(Calendar::count())->toBe(0)
        ->and(Profile::count())->toBe(0);
});
