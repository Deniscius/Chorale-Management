<?php

namespace Database\Factories;

use App\Models\CorrespondenceService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CorrespondenceService>
 */
class CorrespondenceServiceFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Bureau pastoral', 'Commission liturgique', 'Commission des instruments',
            'Trésorerie', 'Secrétariat général', 'Commission sociale', 'Pastoral des jeunes',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
        ];
    }
}
