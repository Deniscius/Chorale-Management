<?php

namespace Database\Factories;

use App\Models\Donation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Donation>
 */
class DonationFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(['choriste', 'paroissien', 'externe', 'anonyme']);

        return [
            'donor_type' => $type,
            'donation_type' => fake()->randomElement([
                'offrande_messe', 'support_caisse', 'don_materiel',
                'cotisation_exceptionnelle', 'subvention', 'collecte', 'mecenat', 'autre',
            ]),
            'donor_name' => $type === 'anonyme' ? null : fake()->name(),
            'amount' => fake()->randomElement([5000, 10000, 20000, 25000, 50000, 100000, 250000]),
            'donated_on' => fake()->dateTimeBetween('-10 months', 'now'),
            'purpose' => fake()->randomElement(['Achat de partitions', 'Réparation de l’orgue', 'Aide aux choristes en difficulté', 'Concert de Noël', 'Général', null]),
            'notes' => null,
        ];
    }
}
