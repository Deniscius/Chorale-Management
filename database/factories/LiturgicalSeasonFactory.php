<?php

namespace Database\Factories;

use App\Models\LiturgicalSeason;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LiturgicalSeason>
 */
class LiturgicalSeasonFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Avent', 'Noël', 'Carême', 'Pâques', 'Temps de Pentecôte', 'Temps ordinaire', 'Saints', 'Sanctuaire',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'starts_on' => null,
            'ends_on' => null,
            'color' => fake()->randomElement(['red', 'green', 'violet', 'blue', 'amber', 'rose', 'indigo', 'teal']),
            'description' => fake()->sentence(),
        ];
    }
}
