<?php

namespace Database\Factories;

use App\Models\Metro;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;
use MatanYadaev\EloquentSpatial\Objects\Point;

class ProviderFactory extends Factory
{
    protected $model = Provider::class;

    public function definition(): array
    {
        return [
            'npi' => $this->faker->unique()->numerify('##########'),
            'display_name' => $this->faker->company(),
            'phone_e164' => '+1' . $this->faker->numerify('##########'),
            'addr_line1' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'zip' => $this->faker->postcode(),
            'location' => new Point($this->faker->latitude(25, 49), $this->faker->longitude(-124, -67), 4326),
            'metro_id' => Metro::factory(),
            'source_nppes' => true,
            'source_places' => false,
            'status' => 'active',
        ];
    }
}
