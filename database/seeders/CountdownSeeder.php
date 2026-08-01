<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Countdown;

class CountdownSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Countdown::create([
            'title' => "Henry's Birthday",
            'target_date' => '2026-08-02',
            'icon' => '🎂',
        ]);

        Countdown::create([
            'title' => "Mommy's Birthday",
            'target_date' => '2026-08-31',
            'icon' => '🎂',
        ]);

        Countdown::create([
            'title' => "First Day of School",
            'target_date' => '2026-08-10',
            'icon' => '🏫',
        ]);

        Countdown::create([
            'title' => "Norway Trip",
            'target_date' => '2026-11-21',
            'icon' => '🇳🇴',
        ]);

        Countdown::create([
            'title' => "Christmas Trip",
            'target_date' => '2026-12-19',
            'icon' => '🎄',
        ]);
    }
}
