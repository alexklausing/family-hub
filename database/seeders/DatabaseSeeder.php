<?php

namespace Database\Seeders;

use App\Models\Profile;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $profileNames = ['Family', 'Dad', 'Mom', 'Kids'];
        
        foreach ($profileNames as $name) {
            $profile = Profile::updateOrCreate(
                ['name' => $name], 
                ['is_default' => ($name === 'Family')]
            );
            
            $tab = $profile->tabs()->updateOrCreate(['name' => 'Calendar'], ['order' => 1]);
            
            // Apple
            $tab->calendars()->updateOrCreate(
                ['provider' => 'apple', 'external_id' => 'primary'],
                [
                    'refresh_rate' => 15,
                    'color' => '#3b82f6',
                    'credentials' => []
                ]
            );

            // Scouting
            $tab->calendars()->updateOrCreate(
                ['provider' => 'ical', 'external_id' => 'scouting'],
                [
                    'refresh_rate' => 60,
                    'color' => '#166534',
                    'credentials' => ['url' => 'https://www.OurGroupOnline.org/iCalendar.aspx?a=3257&u=208648&z=81678']
                ]
            );

            // Lakeland Montessori
            $tab->calendars()->updateOrCreate(
                ['provider' => 'ical', 'external_id' => 'montessori'],
                [
                    'refresh_rate' => 60,
                    'color' => '#d97706',
                    'credentials' => ['url' => 'https://calendar.google.com/calendar/ical/parentcalendar%40lakelandmontessori.com/public/basic.ics']
                ]
            );
        }
    }
}
