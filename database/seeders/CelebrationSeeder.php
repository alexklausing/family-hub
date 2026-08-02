<?php

namespace Database\Seeders;

use App\Models\Celebration;
use Illuminate\Database\Seeder;

class CelebrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Celebration::create([
            'message' => 'Happy Birthday Henry!',
            'background' => 'confetti',
            'font' => 'display',
            'font_color' => '#ffffff',
            'is_active' => true,
        ]);
    }
}
