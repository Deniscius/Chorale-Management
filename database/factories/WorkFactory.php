<?php

namespace Database\Factories;

use App\Models\LiturgicalSeason;
use App\Models\Work;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Work>
 */
class WorkFactory extends Factory
{
    public function definition(): array
    {
        $familles = [
            'Messe de Rédemption', 'Messe de Pentecôte', 'Messe de Noël', 'Messe de Pâques',
            'Messe de l’Ascension', 'Messe de la Trinité', 'Messe de l’Immaculée Conception',
            'Messe de Saint-Joseph', 'Messe des Rameaux', 'Messe de la Trinité',
        ];

        $mouvements = [
            'Kyrie', 'Gloria', 'Credo', 'Sanctus', 'Agnus Dei', 'Communion', 'Pneuma Domini',
            'Alléluia', 'Sequence', 'Veni Sancta Spiritus', 'Introit', 'Offertory', 'Final',
        ];

        $composers = [
            'Gregorio Allegri', 'Jean-Jacques Natt', 'W. A. Mozart', 'F. Schubert', 'G. F. Händel',
            'A. Vivaldi', 'O. Messiaen', 'L. Vierne', 'Traditionnel', 'D. Maw', 'C. Villiers Stanford',
        ];

        $mouvement = fake()->randomElement($mouvements);

        return [
            'title' => fake()->randomElement($familles).' — '.$mouvement,
            'type' => fake()->randomElement(['messe', 'messe_propre', 'motet', 'cantique', 'psaume', 'hymne', 'sequence', 'chant', 'benedicite']),
            'composer' => fake()->randomElement($composers),
            'arranger' => fake()->boolean(40) ? fake()->name() : null,
            'text_author' => fake()->boolean(60) ? 'Texte liturgique' : null,
            'language' => fake()->randomElement(['Latin', 'Français', 'Latin/French', 'Anglais']),
            'key_signature' => fake()->randomElement(['Do majeur', 'Ré mineur', 'Mi♭ majeur', 'Fa majeur', 'Sol majeur', 'La mineur']),
            'mode' => fake()->randomElement(['Majeur', 'Mineur', 'Mixte', 'Dorian', 'Phrygian']),
            'tempo' => fake()->numberBetween(50, 130),
            'duration_minutes' => fake()->numberBetween(2, 12),
            'difficulty' => fake()->numberBetween(1, 5),
            'min_voices' => fake()->numberBetween(4, 30),
            'required_voices' => fake()->randomElements(['soprano', 'alto', 'tenor', 'bass'], fake()->numberBetween(2, 4)),
            'instrumentation' => fake()->randomElements(['orgue', 'piano', 'percussions', 'cordes'], fake()->numberBetween(0, 2)),
            'is_repertoire' => true,
            'season_id' => null,
            'notes' => fake()->boolean(30) ? fake()->sentence() : null,
        ];
    }

    public function mass(): static
    {
        return $this->state(fn () => ['type' => 'messe', 'is_repertoire' => true]);
    }

    public function motet(): static
    {
        return $this->state(fn () => ['type' => 'motet']);
    }

    public function withSeason(LiturgicalSeason $season): static
    {
        return $this->state(fn () => ['season_id' => $season->id]);
    }
}
