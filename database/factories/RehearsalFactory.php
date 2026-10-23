<?php

namespace Database\Factories;

use App\Models\Rehearsal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rehearsal>
 */
class RehearsalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeBetween('-1 month', '+2 months'),
            'starts_at' => '18:00:00',
            'ends_at' => '20:00:00',
            'venue' => fake()->randomElement(['Salle paroissiale', 'Église Saint-Joseph', 'Salle de la cathédrale', 'Salle Saint-Jean']),
            'type' => fake()->randomElement(['repetition', 'repetition', 'repetition', 'formation', 'reunion', 'concert']),
            'status' => fake()->randomElement(['planifiee', 'planifiee', 'terminee']),
            'notes' => fake()->boolean(25) ? fake()->sentence() : null,
        ];
    }

    public function past(): static
    {
        return $this->state(fn () => ['date' => fake()->dateTimeBetween('-3 months', '-1 day'), 'status' => 'terminee']);
    }

    public function upcoming(): static
    {
        return $this->state(fn () => ['date' => fake()->dateTimeBetween('+1 day', '+3 months'), 'status' => 'planifiee']);
    }
}
