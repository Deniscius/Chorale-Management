<?php

namespace Database\Factories;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(['recette', 'recette', 'depense']);

        return [
            'type' => $type,
            'transaction_date' => fake()->dateTimeBetween('-10 months', 'now'),
            'description' => fake()->randomElement([
                'Offrande dominicale', 'Don de la famille Kouassi', 'Cotisations du mois de mars',
                'Location de la salle polyvalente', 'Achat de partitions', 'Accord du piano',
                'Sortie de laSubnet — transport', 'Affiche et flyers', 'Restauration après répétition',
                'Subvention du conseil paroissial', 'Vente de billets — concert de Noël',
            ]),
            'amount' => fake()->numberBetween(5_000, 500_000),
            'category' => fake()->randomElement(['Offrandes', 'Cotisations', 'Dons', 'Subventions', 'Billets', 'Partitions', 'Instruments', 'Logistique', 'Transport', 'Restauration']),
            'payment_method' => fake()->randomElement(['especes', 'mobile_money', 'virement', 'cheque', 'autre']),
            'status' => fake()->randomElement(['validee', 'validee', 'validee', 'brouillon', 'rejetee']),
            'notes' => null,
        ];
    }

    public function recette(): static
    {
        return $this->state(fn () => ['type' => 'recette', 'status' => 'validee']);
    }

    public function depense(): static
    {
        return $this->state(fn () => ['type' => 'depense', 'status' => 'validee']);
    }
}
