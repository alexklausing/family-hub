<?php

namespace Database\Seeders;

use App\Models\Calendar;
use App\Models\Profile;
use App\Models\Tab;
use Illuminate\Database\Seeder;

class PaprikaMenuPlanCalendarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $url = config('services.paprika.menu_plan_calendar_url');

        if (blank($url)) {
            $this->command->warn('Paprika menu plan calendar URL is not configured; skipping.');

            return;
        }

        $profile = (new Profile)->newQuery()->firstOrCreate(
            ['name' => 'Family'],
            ['is_default' => true]
        );
        $tab = (new Tab)->newQuery()->firstOrCreate(
            [
                'profile_id' => $profile->getKey(),
                'name' => 'Calendar',
            ],
            ['order' => 1]
        );
        $calendar = (new Calendar)->newQuery()->updateOrCreate(
            [
                'tab_id' => $tab->getKey(),
                'provider' => 'ical',
                'external_id' => 'paprika_menu_plan',
            ],
            [
                'name' => 'Paprika Menu Plan',
                'color' => '#f97316',
                'refresh_rate' => 15,
                'credentials' => ['url' => $url],
            ]
        );

        $visibleCalendars = $profile->getAttribute('visible_calendars');

        if ($visibleCalendars !== null) {
            $visibleCalendarIds = collect($visibleCalendars)
                ->map(fn ($id) => (int) $id)
                ->push($calendar->getKey())
                ->unique()
                ->values()
                ->all();

            $profile->update(['visible_calendars' => $visibleCalendarIds]);
        }
    }
}
