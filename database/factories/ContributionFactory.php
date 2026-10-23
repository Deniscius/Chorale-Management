<?php

namespace Database\Factories;

use App\Models\Contribution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contribution>
 */
class ContributionFactory extends Factory
{
    public function definition(): array
    {
        $paid = fake()->boolean(65);
        $due = fake()->randomElement([1000, 1500, 2000, 2500, 3000, 5000]);

        return [
            'year' => now()->year,
            'month' => fake()->numberBetween(1, 12),
            'due_amount' => $due,
            'paid_amount' => $paid ? $due : 0,
            'paid_on' => $paid ? fake()->dateTimeBetween('-6 months', 'now') : null,
            'status' => $paid ? 'payee' : 'due',
            'notes' => null,
        ];
    }

    public function payee(): static
    {
        return $this->state(fn () => ['status' => 'payee', 'paid_on' => now()->subDays(random_int(0, 20))]);
    }
}
