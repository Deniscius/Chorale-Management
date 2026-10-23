<?php

namespace Database\Factories;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    public function definition(): array
    {
        $titles = [
            'Répétition hebdomadaire', 'Formation au chant liturgique', 'Sortie à la cathédrale',
            'Action sociale de Noël', 'Retraite spirituelle', 'Concert de bienfaisance',
            'Réunion de la commission liturgique', 'Campagne de rentrée', 'Catéchèse musicale',
            'Répétition générale', 'Journée de l’orgue', 'Réunion de trésorerie',
        ];

        $starts = fake()->dateTimeBetween('-2 months', '+3 months');

        return [
            'title' => fake()->randomElement($titles),
            'type' => fake()->randomElement(['repetition', 'formation', 'concert', 'action_sociale', 'retraite', 'sortie', 'reunion', 'campagne', 'catechese', 'autre']),
            'description' => fake()->paragraph(),
            'starts_at' => $starts,
            'ends_at' => (clone $starts)->modify('+2 hours'),
            'venue' => fake()->randomElement(['Salle paroissiale', 'Église Saint-Joseph', 'Salle de la cathédrale', 'Parc de la paroisse']),
            'budget_allocated' => fake()->numberBetween(0, 400000),
            'budget_spent' => fake()->numberBetween(0, 350000),
            'status' => fake()->randomElement(['planifiee', 'planifiee', 'en_cours', 'terminee', 'annulee']),
            'report' => fake()->boolean(20) ? fake()->paragraph() : null,
            'is_public' => fake()->boolean(50),
        ];
    }

    public function upcoming(): static
    {
        return $this->state(fn () => ['starts_at' => fake()->dateTimeBetween('+1 day', '+3 months'), 'status' => 'planifiee']);
    }
}
