<?php

namespace Database\Factories;

use App\Models\Choir;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Choir>
 */
class ChoirFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Chorale '.fake()->randomElement([
            'Saint-Marc', 'Notre-Dame de la Paix', 'Sainte-Anne', 'Saint-Joseph', 'La Résurrection', 'Sainte-Croix',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'parish' => 'Paroisse Saint-Joseph',
            'patron' => 'Saint-Joseph',
            'target_size' => fake()->numberBetween(30, 80),
            'founded_on' => fake()->dateTimeBetween('-40 years', '-5 years'),
            'is_active' => true,
        ];
    }
}
