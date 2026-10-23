<?php

namespace Database\Factories;

use App\Models\Instrument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Instrument>
 */
class InstrumentFactory extends Factory
{
    public function definition(): array
    {
        $names = [
            'Orgue de la nef', 'Orgue positif', 'Piano droit', 'Piano queue', 'Synthétiseur Yamaha',
            'Synthétiseur Roland', 'Tamtams', 'Cymbal crash', 'Batterie complète', 'Bongos', 'Djembé',
            'Guitare acoustique', 'Violon', 'Violoncelle', 'Hautbois', 'Clarinette', 'Saxophone', 'Trombone',
            'Micro HF', 'Console de mixage', 'ENCEintes', 'Vidéoprojecteur',
        ];

        return [
            'name' => fake()->randomElement($names),
            'category' => fake()->randomElement(['orgue', 'piano', 'synthese', 'percussions', 'tastrophes', 'cordes', 'sonorisation', 'video', 'autre']),
            'serial_number' => strtoupper(fake()->bothify('SN-####??')),
            'quantity' => fake()->numberBetween(1, 4),
            'condition' => fake()->randomElement(['excellent', 'bon', 'bon', 'moyen', 'hors_service']),
            'purchase_on' => fake()->dateTimeBetween('-15 years', '-1 year'),
            'purchase_price' => fake()->numberBetween(50_000, 3_000_000),
            'current_value' => fake()->numberBetween(20_000, 2_500_000),
            'location' => fake()->randomElement(['Sacristie', 'Salle de musique', 'Nef de l’église', 'Stockage', 'Local technique']),
            'is_borrowed' => false,
            'notes' => fake()->boolean(20) ? fake()->sentence() : null,
        ];
    }

    public function needsMaintenance(): static
    {
        return $this->state(fn () => ['condition' => 'moyen']);
    }
}
