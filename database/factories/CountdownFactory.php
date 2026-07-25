<?php

namespace Database\Factories;

use App\Models\Countdown;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Countdown>
 */
class CountdownFactory extends Factory
{
    protected $model = Countdown::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->words(3, true),
            'target_date' => $this->faker->date(),
            'icon' => $this->faker->randomElement(['🎂', '✈️', '🎄', '🎉', '🏖️']),
        ];
    }
}
