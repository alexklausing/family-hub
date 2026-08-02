<?php

namespace Database\Factories;

use App\Models\Celebration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Celebration>
 */
class CelebrationFactory extends Factory
{
    protected $model = Celebration::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message' => $this->faker->sentence(4),
            'background' => $this->faker->randomElement(['sunset', 'ocean', 'forest', 'confetti', 'royal']),
            'font' => $this->faker->randomElement(['display', 'serif', 'cursive', 'mono']),
            'font_color' => $this->faker->randomElement(['#ffffff', '#ffd700', '#ff6b6b']),
            'is_active' => false,
        ];
    }
}
