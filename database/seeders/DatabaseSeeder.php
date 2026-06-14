<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\Tab;
use App\Models\Calendar;
use App\Services\PaprikaSyncService;
use App\Services\Calendar\CalendarManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $profileNames = ['Family', 'Dad', 'Mom', 'Kids'];
        
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

        // Trigger Synchronous Syncs
        $this->command->info("Starting initial Paprika recipe sync...");
        try {
            $paprika = app(PaprikaSyncService::class);
            $paprika->syncRecipes();
            $this->command->info("✓ Recipes synced.");
        } catch (\Exception $e) {
            $this->command->error("Paprika sync failed: " . $e->getMessage());
        }

        $this->command->info("Starting initial Calendar events sync...");
        try {
            $manager = app(CalendarManager::class);
            $calendars = Calendar::all();
            foreach ($calendars as $calendar) {
                $manager->syncCalendar($calendar);
                $this->command->info("✓ Synced calendar: {$calendar->name}");
            }
        } catch (\Exception $e) {
            $this->command->error("Calendar sync failed: " . $e->getMessage());
        }

        $this->command->info("🌱 Database seeding and initial sync complete!");
    }

    protected function seedFamilyCalendars(Tab $tab): void
    {
        $calendars = [
            [
                'provider' => 'ical',
                'external_id' => 'scouting',
                'name' => 'Scouting',
                'color' => '#166534',
                'credentials' => ['url' => 'https://www.OurGroupOnline.org/iCalendar.aspx?a=3257&u=208648&z=81678']
            ],
            [
                'provider' => 'ical',
                'external_id' => 'montessori',
                'name' => 'Montessori',
                'color' => '#d97706',
                'credentials' => ['url' => 'https://calendar.google.com/calendar/ical/parentcalendar%40lakelandmontessori.com/public/basic.ics']
            ],
            [
                'provider' => 'ical',
                'external_id' => 'office365_work',
                'name' => 'Work',
                'color' => '#dc2626',
                'credentials' => ['url' => 'https://outlook.office365.com/owa/calendar/d840c903ab934902ace3cf42b1d82d31@flsouthern.edu/3345e045df0341549e22c8a1ea350d9718080190018700542250/calendar.ics']
            ],
        ];

        foreach ($calendars as $c) {
            $tab->calendars()->updateOrCreate(
                ['provider' => $c['provider'], 'external_id' => $c['external_id']],
                [
                    'name' => $c['name'],
                    'color' => $c['color'],
                    'refresh_rate' => 60,
                    'credentials' => $c['credentials']
                ]
            );
        }
    }
}
