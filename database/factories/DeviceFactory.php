<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'device_uid' => Str::uuid()->toString(),
            'type' => $this->faker->randomElement(['gps_tag', 'mobile_app']),
            'person_id' => Person::factory(),
            'battery_level' => $this->faker->numberBetween(15, 100),
            'last_seen_at' => $this->faker->dateTimeBetween('-2 hours', 'now'),
            'api_token' => null,
        ];
    }

    public function tagged(): static
    {
        return $this->state(fn () => ['type' => 'gps_tag']);
    }
}
