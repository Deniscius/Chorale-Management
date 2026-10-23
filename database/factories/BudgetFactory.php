<?php

namespace Database\Factories;

use App\Models\Budget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    public function definition(): array
    {
        $allocated = fake()->numberBetween(50_000, 800_000);

        return [
            'year' => now()->year,
            'category' => fake()->randomElement(['Fonctionnement', 'Partitions', 'Instruments', 'Formation', 'Sorties', 'Concerts', 'Social']),
            'allocated_amount' => $allocated,
            'committed_amount' => (int) ($allocated * fake()->randomFloat(2, 0.1, 0.6)),
            'realized_amount' => (int) ($allocated * fake()->randomFloat(2, 0, 0.55)),
            'notes' => null,
        ];
    }
}
