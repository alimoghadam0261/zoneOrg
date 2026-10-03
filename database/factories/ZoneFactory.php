<?php

namespace Database\Factories;

use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Zone>
 */
class ZoneFactory extends Factory
{
    protected $model = Zone::class;

    public function definition(): array
    {
        $lat = $this->faker->latitude(35.60, 35.78);
        $lng = $this->faker->longitude(51.30, 51.48);

        $type = $this->faker->randomElement(['polygon', 'circle', 'rectangle']);

        [$geometry, $bbox] = $this->geometry($type, $lat, $lng);

        return [
            'name' => $this->faker->unique()->words(2, true),
            'code' => 'Z-'.strtoupper($this->faker->unique()->bothify('??_##')),
            'type' => $type,
            'geometry' => $geometry,
            'min_lat' => $bbox[0],
            'min_lng' => $bbox[1],
            'max_lat' => $bbox[2],
            'max_lng' => $bbox[3],
            'center_lat' => $type === 'circle' ? $lat : null,
            'center_lng' => $type === 'circle' ? $lng : null,
            'radius' => $type === 'circle' ? 150 : null,
            'color' => $this->faker->randomElement(['#6366f1', '#0ea5e9', '#f59e0b', '#ef4444', '#16a34a']),
            'severity_level' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'is_active' => true,
            'description' => $this->faker->sentence(6),
        ];
    }

    /**
     * @return array{0: array<string, mixed>, 1: array{0: float, 1: float, 2: float, 3: float}}
     */
    protected function geometry(string $type, float $lat, float $lng): array
    {
        if ($type === 'circle') {
            $radius = 150;

            $dLat = $radius / 111320;
            $dLng = $radius / (111320 * max(cos(deg2rad($lat)), 0.01));

            return [
                [
                    'type' => 'Point',
                    'coordinates' => [round($lng, 8), round($lat, 8)],
                    'properties' => ['radius' => $radius],
                ],
                [$lat - $dLat, $lng - $dLng, $lat + $dLat, $lng + $dLng],
            ];
        }

        $dLat = 0.004;
        $dLng = 0.005;

        $ring = [
            [round($lng - $dLng, 8), round($lat - $dLat, 8)],
            [round($lng + $dLng, 8), round($lat - $dLat, 8)],
            [round($lng + $dLng, 8), round($lat + $dLat, 8)],
            [round($lng - $dLng, 8), round($lat + $dLat, 8)],
            [round($lng - $dLng, 8), round($lat - $dLat, 8)],
        ];

        return [
            ['type' => 'Polygon', 'coordinates' => [$ring]],
            [$lat - $dLat, $lng - $dLng, $lat + $dLat, $lng + $dLng],
        ];
    }
}
