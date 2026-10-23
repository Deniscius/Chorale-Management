<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    public function definition(): array
    {
        $code = fake()->unique()->numberBetween(100, 899);

        return [
            'code' => (string) $code,
            'name' => fake()->randomElement([
                'Caisse de la chorale', 'Caisse des offrandes', 'Produits des concerts',
                'Cotisations des choristes', 'Subventions', 'Don et legs', 'Frais de repetition',
                'Achat de partitions', 'Maintenance des instruments', 'Location de salle',
                'Communication et affichage', 'Frais de déplacement', 'Frais divers',
            ]),
            'type' => fake()->randomElement(['tresorerie', 'actif', 'passif', 'produit', 'charge']),
            'is_active' => true,
        ];
    }
}
