<?php

namespace Database\Factories;

use App\Models\InstrumentEnsemble;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstrumentEnsemble>
 */
class InstrumentEnsembleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Orchestre de la chorale', 'Ensemble de cuivre', 'Groupe de percussions',
                'Atelier de djembé', 'Troupe de la jeunesse', 'Orchestre de la fête patronale',
            ]),
            'description' => fake()->sentence(),
            'member_count' => fake()->numberBetween(3, 20),
        ];
    }
}
