<?php

namespace Database\Factories;

use App\Models\InstrumentMaintenance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstrumentMaintenance>
 */
class InstrumentMaintenanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['accord', 'reparation', 'entretien', 'reglage', 'remplacement']),
            'description' => fake()->randomElement([
                'Accord annuel du piano', 'Remplacement de la pédale de l’orgue', 'Réparation de la mécanique de pédale',
                'Nettoyage des maracas et du vibraphone', 'Remplacement de la peau de la caisse claire',
                'Réglage des tirants de l’orgue', 'Changement des cordes de guitare',
            ]),
            'provider' => fake()->randomElement(['Atelier Adjoua', 'Music Service CI', 'Technicien de la paroisse', 'Atelier Yamaha']),
            'cost' => fake()->numberBetween(10_000, 500_000),
            'performed_on' => fake()->dateTimeBetween('-18 months', 'now'),
            'scheduled_for' => null,
            'status' => fake()->randomElement(['terminee', 'terminee', 'en_cours', 'planifiee']),
            'notes' => null,
        ];
    }
}
