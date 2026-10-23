<?php

namespace Database\Factories;

use App\Models\Performance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Performance>
 */
class PerformanceFactory extends Factory
{
    public function definition(): array
    {
        $titles = [
            'Messe dominicale', 'Messe de Noël', 'Veille de Noël', 'Messe de Pâques', 'Fête patronale',
            'Concert de chants de Noël', 'Concert de chants de Pâques', 'Messe de mariage', 'Messe de funérailles',
            'Célébration de l’Action de grâce', 'Concert de l’Ascension',
        ];

        return [
            'title' => fake()->randomElement($titles),
            'type' => fake()->randomElement(['concert', 'messe', 'mariage', 'funerailles', 'fete_patronale', 'retraite', 'festival', 'ememoration', 'ordination', 'veille']),
            'scheduled_at' => fake()->dateTimeBetween('-2 months', '+6 months'),
            'venue' => fake()->randomElement([
                'Église Saint-Joseph', 'Paroisse Notre-Dame de la Paix', 'Salle paroissiale', 'Auditorium du diocèse',
                'Église Sainte-Anne', 'Plaza du 4 janvier',
            ]),
            'audience' => fake()->randomElement(['Paroissiens', 'Public', 'Famille', 'Communauté', null]),
            'status' => fake()->randomElement(['planifiee', 'planifiee', 'en_cours', 'terminee']),
            'budget' => fake()->numberBetween(0, 500000),
            'revenue' => fake()->numberBetween(0, 800000),
            'notes' => fake()->boolean(30) ? fake()->paragraph() : null,
        ];
    }

    public function upcoming(): static
    {
        return $this->state(fn () => ['scheduled_at' => fake()->dateTimeBetween('+1 day', '+8 months'), 'status' => 'planifiee']);
    }
}
