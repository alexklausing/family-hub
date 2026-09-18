<?php

namespace Database\Seeders;

use App\Models\Destination;
use Illuminate\Database\Seeder;

class CommuteDestinationSeeder extends Seeder
{
    public function run(): void
    {
        $destinations = [
            [
                'name' => 'Lakeland Montessori',
                'address' => '1124 N. Lake Parker Ave, Lakeland, FL 33805',
                'lat' => 28.0636,
                'lon' => -81.9434,
                'icon' => 'GraduationCap',
                'sort_order' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Florida Southern College',
                'address' => '111 Lake Hollingsworth Dr, Lakeland, FL 33801',
                'lat' => 28.0327,
                'lon' => -81.9502,
                'icon' => 'Building2',
                'sort_order' => 1,
                'is_active' => true,
            ],
        ];

        foreach ($destinations as $dest) {
            Destination::updateOrCreate(
                ['name' => $dest['name']],
                $dest,
            );
        }
    }
}
