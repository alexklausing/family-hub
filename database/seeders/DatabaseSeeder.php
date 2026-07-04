<?php

namespace Database\Seeders;

use App\Models\Calendar;
use App\Models\Profile;
use App\Models\Tab;
use App\Services\Calendar\AppleCalendarService;
use App\Services\Calendar\CalendarManager;
use App\Services\PaprikaSyncService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $profileNames = ['Family', 'Alex', 'Sarah', 'Emily', 'Henry'];

        foreach ($profileNames as $name) {
            $this->command->info("Initializing profile: {$name}");

            $profile = Profile::updateOrCreate(
                ['name' => $name],
                ['is_default' => ($name === 'Family')]
            );

            $tab = $profile->tabs()->updateOrCreate(['name' => 'Calendar'], ['order' => 1]);

            // Connect Calendars to the "Family" profile primarily
            if ($name === 'Family') {
                $this->seedFamilyCalendars($tab);
            }
        }

        // Seed Chores
        $this->call(ChoreSeeder::class);

        // Trigger Synchronous Syncs
        $this->command->info('Starting initial Paprika recipe sync...');
        try {
            $paprika = app(PaprikaSyncService::class);
            $paprika->syncRecipes();
            $this->command->info('✓ Recipes synced.');
        } catch (\Exception $e) {
            $this->command->error('Paprika sync failed: '.$e->getMessage());
        }

        $this->command->info('Starting initial Calendar events sync...');
        try {
            $manager = app(CalendarManager::class);
            $calendars = Calendar::all();
            foreach ($calendars as $calendar) {
                $manager->syncCalendar($calendar);
                $this->command->info("✓ Synced calendar: {$calendar->name}");
            }
        } catch (\Exception $e) {
            $this->command->error('Calendar sync failed: '.$e->getMessage());
        }

        $this->command->info('Configuring default calendar visibility for profiles...');
        $this->seedProfileCalendarVisibility();

        $this->command->info('🌱 Database seeding and initial sync complete!');
    }

    protected function seedProfileCalendarVisibility(): void
    {
        $allCalendars = Calendar::all();

        // Define which calendars each profile should see by default
        // The names here should match the 'name' column of the seeded calendars
        $visibilityMatrix = [
            'Family' => ['Scouting', 'Montessori', 'Work', 'A&S Family Calendar'],
            'Alex' => ['Work', 'A&S Family Calendar'],
            'Sarah' => ['A&S Family Calendar'],
            'Emily' => ['Montessori'],
            'Henry' => ['Montessori', 'Scouting'],
        ];

        foreach (Profile::all() as $profile) {
            $allowedNames = $visibilityMatrix[$profile->name] ?? [];
            $visibleIds = [];

            foreach ($allCalendars as $calendar) {
                if (in_array($calendar->name, $allowedNames)) {
                    $visibleIds[] = $calendar->id;
                }
            }

            // Assign all if matrix mapping isn't found
            if (empty($allowedNames)) {
                $visibleIds = $allCalendars->pluck('id')->toArray();
            }

            $profile->update(['visible_calendars' => $visibleIds]);
        }
    }

    protected function seedFamilyCalendars(Tab $tab): void
    {
        $calendars = [
            [
                'provider' => 'ical',
                'external_id' => 'scouting',
                'name' => 'Scouting',
                'color' => '#166534',
                'credentials' => ['url' => 'https://www.OurGroupOnline.org/iCalendar.aspx?a=3257&u=208648&z=81678'],
            ],
            [
                'provider' => 'ical',
                'external_id' => 'montessori',
                'name' => 'Montessori',
                'color' => '#d97706',
                'credentials' => ['url' => 'https://calendar.google.com/calendar/ical/parentcalendar%40lakelandmontessori.com/public/basic.ics'],
            ],
            [
                'provider' => 'ical',
                'external_id' => 'office365_work',
                'name' => 'Work',
                'color' => '#dc2626',
                'credentials' => ['url' => 'https://outlook.office365.com/owa/calendar/d840c903ab934902ace3cf42b1d82d31@flsouthern.edu/3345e045df0341549e22c8a1ea350d9718080190018700542250/calendar.ics'],
            ],
        ];

        if (! empty(config('services.apple.email'))) {
            try {
                $appleService = app(AppleCalendarService::class);
                $appleCalendars = $appleService->getCalendars(config('services.apple.email'), config('services.apple.password'));

                foreach ($appleCalendars as $appleCalendar) {
                    if (str_contains($appleCalendar['name'], 'A&S Family Calendar')) {
                        $calendars[] = [
                            'provider' => 'apple',
                            'external_id' => 'apple_family',
                            'name' => 'A&S Family Calendar',
                            'color' => '#9333ea', // Purple for Family
                            'credentials' => ['path' => $appleCalendar['path']],
                        ];
                    }
                }
            } catch (\Exception $e) {
                $this->command->error('Failed to fetch Apple Calendars: '.$e->getMessage());
            }
        }

        foreach ($calendars as $c) {
            $tab->calendars()->updateOrCreate(
                ['provider' => $c['provider'], 'external_id' => $c['external_id']],
                [
                    'name' => $c['name'],
                    'color' => $c['color'],
                    'refresh_rate' => 60,
                    'credentials' => $c['credentials'],
                ]
            );
        }
    }
}
