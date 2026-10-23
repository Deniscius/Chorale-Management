<?php

namespace Database\Factories;

use App\Enums\Commission;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(3, true).'.pdf';

        return [
            'commission' => fake()->randomElement(array_map(
                fn (Commission $c): string => $c->value,
                Commission::cases()
            )),
            'title' => ucfirst(fake()->words(3, true)),
            'description' => fake()->boolean(60) ? fake()->sentence() : null,
            'category' => fake()->randomElement(['Compte rendu', 'Rapport', 'Statuts', 'Budget', 'Convocation', null]),
            'original_name' => $name,
            'path' => 'documents/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(40000, 4000000),
            'member_id' => null,
            'uploaded_by' => null,
        ];
    }
}
