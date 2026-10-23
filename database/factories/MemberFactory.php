<?php

namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    public function definition(): array
    {
        $firstNames = ['Jean', 'Marie', 'Paul', 'Anne', 'Joseph', 'Marie-Claire', 'Emmanuel', 'Bernadette', 'Pierre', 'Grâce',
            'Jean-Baptiste', 'Thérèse', 'Michel', 'Claudine', 'André', 'Esther', 'Vincent', 'Sylvie', 'Émile', 'Rita'];
        $lastNames = ['Koffi', 'Traoré', 'Koné', 'N’Guessan', 'Ouattara', 'Diallo', 'Bamba', 'Cissé', 'Touré', 'Sylla',
            'Yao', 'Kouassi', 'Adou', 'N’Da', 'Zadi', 'Gohou'];

        $first = $this->faker->randomElement($firstNames);
        $last = $this->faker->randomElement($lastNames);

        return [
            'first_name' => $first,
            'last_name' => $last,
            'sex' => $this->faker->randomElement(['M', 'F']),
            'birth_date' => $this->faker->dateTimeBetween('-70 years', '-16 years'),
            'voice' => $this->faker->randomElement(['soprano', 'alto', 'tenor', 'bass']),
            'part' => $this->faker->randomElement(['S1', 'S2', 'A1', 'A2', 'T', 'B']),
            'email' => Str::slug($first.'-'.$last, '.').'.'.fake()->unique()->numberBetween(1000, 999999).'@exemple.ci',
            'phone' => '+225 07 '.random_int(0, 9).' '.random_int(10, 99).' '.random_int(10, 99).' '.random_int(10, 99),
            'address' => $this->faker->randomElement([
                'Cocody Angré, 7e Tranche', 'Yopougon Selmer, Rue des Écoles', 'Plateau, Rue de la Paix',
                'Marcory Résidentiel, 3e Zone', 'Treichville, Avenue 8', 'Abobo Baoulé, Quartier Anoumabo',
            ]),
            'profession' => $this->faker->randomElement([
                'Enseignante', 'Commerçant', 'Infirmière', 'Ingénieur', 'Agent administratif',
                'Comptable', 'Étudiant', 'Artisan', 'Cadre bancaire',
            ]),
            'commission' => null,
            'joined_on' => $this->faker->dateTimeBetween('-8 years', '-1 month'),
            'left_on' => null,
            'status' => 'actif',
            'notes' => null,
            'photo_path' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'actif']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactif', 'left_on' => now()->subMonths(3)]);
    }

    public function invited(): static
    {
        return $this->state(fn () => ['status' => 'invite', 'joined_on' => null]);
    }

    public function voice(string $voice): static
    {
        return $this->state(fn () => ['voice' => $voice]);
    }
}
