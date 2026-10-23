<?php

namespace Database\Factories;

use App\Models\InstrumentMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstrumentMovement>
 */
class InstrumentMovementFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(['sortie', 'sortie', 'retour', 'pret', 'transfert', 'maintenance']);

        return [
            'type' => $type,
            'moved_on' => fake()->dateTimeBetween('-12 months', 'now'),
            'reason' => fake()->randomElement(['Célébration', 'Concert', 'Sortie pastorale', 'Réparation', 'Transfert de stockage', 'Prêt au groupe de jeunes']),
            'destination' => fake()->randomElement(['Église Saint-Joseph', 'Salle paroissiale', 'Atelier Yamaha', 'Sacristie', 'Cathédrale']),
            'expected_return_on' => fake()->boolean(60) ? fake()->dateTimeBetween('-6 months', '+2 months') : null,
            'actual_return_on' => $type === 'retour' ? fake()->dateTimeBetween('-6 months', 'now') : null,
            'condition_on_return' => $type === 'retour' ? fake()->randomElement(['excellent', 'bon', 'moyen']) : null,
            'notes' => null,
        ];
    }
}
