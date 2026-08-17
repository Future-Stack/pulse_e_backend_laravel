<?php

namespace Database\Factories;

use App\Models\Metro;
use Illuminate\Database\Eloquent\Factories\Factory;
use MatanYadaev\EloquentSpatial\Objects\Point;

class MetroFactory extends Factory
{
    protected $model = Metro::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'centroid' => new Point($this->faker->latitude(25, 49), $this->faker->longitude(-124, -67), 4326),
            'radius_km' => 25,
            'density_tier' => $this->faker->numberBetween(1, 5),
            'active' => true,
        ];
    }
}
