<?php

namespace Database\Factories;

use App\Models\ProviderCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProviderCategoryFactory extends Factory
{
    protected $model = ProviderCategory::class;

    public function definition(): array
    {
        return [
            'slug' => $this->faker->unique()->slug(2),
            'display_name' => $this->faker->words(3, true),
            'vetting_tier' => $this->faker->randomElement(['medical', 'licensed_nonmedical', 'consumer']),
            'requires_npi' => $this->faker->boolean(),
            'active' => true,
        ];
    }
}
