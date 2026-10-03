<?php

namespace Tests\Unit;

use App\Services\Geofencing\GeoJson;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class GeoJsonTest extends TestCase
{
    public function test_bbox_of_polygon_returns_list_order(): void
    {
        $geometry = [
            'type' => 'Polygon',
            'coordinates' => [[
                [51.0, 35.0],
                [51.1, 35.0],
                [51.1, 35.2],
                [51.0, 35.2],
                [51.0, 35.0],
            ]],
        ];

        $bbox = GeoJson::bbox($geometry);

        $this->assertSame([35.0, 51.0, 35.2, 51.1], $bbox);
    }

    public function test_bbox_of_circle_accounts_for_radius(): void
    {
        $geometry = [
            'type' => 'Point',
            'coordinates' => [51.0, 35.0],
            'properties' => ['radius' => 11132], // ≈ 0.1° latitude
        ];

        $bbox = GeoJson::bbox($geometry);

        // dLat = radius / 111320 → the box spans 2 × dLat
        $this->assertEqualsWithDelta(0.2, $bbox[2] - $bbox[0], 0.002);
        $this->assertGreaterThan($bbox[0], $bbox[2]);
        $this->assertGreaterThan($bbox[1], $bbox[3]);
    }

    public function test_ring_drops_duplicate_closing_vertex_and_swaps_to_lat_lng(): void
    {
        $geometry = [
            'type' => 'Polygon',
            'coordinates' => [[
                [51.0, 35.0],
                [51.1, 35.0],
                [51.1, 35.1],
                [51.0, 35.0],
            ]],
        ];

        $ring = GeoJson::ring($geometry);

        $this->assertCount(3, $ring);
        $this->assertSame([35.0, 51.0], $ring[0]);
    }

    public function test_normalize_repairs_and_closes_a_polygon(): void
    {
        $geometry = [
            'type' => 'Polygon',
            'coordinates' => [[
                [51.0, 35.0],
                [51.1, 35.0],
                [51.1, 35.1],
            ]],
        ];

        $normalized = GeoJson::normalize($geometry, 'polygon');
        $ring = $normalized['coordinates'][0];

        $this->assertSame($ring[0], end($ring));
        $this->assertCount(4, $ring);
    }

    public function test_normalize_rejects_polygon_with_too_few_vertices(): void
    {
        $this->expectException(InvalidArgumentException::class);

        GeoJson::normalize([
            'type' => 'Polygon',
            'coordinates' => [[[51.0, 35.0], [51.1, 35.0], [51.0, 35.0]]],
        ], 'polygon');
    }

    public function test_circle_must_have_a_positive_radius(): void
    {
        $result = GeoJson::validate([
            'type' => 'Point',
            'coordinates' => [51.0, 35.0],
            'properties' => ['radius' => 0],
        ], 'circle');

        $this->assertFalse($result['valid']);
        $this->assertNotNull($result['error']);
    }

    public function test_validate_accepts_a_well_formed_circle(): void
    {
        $result = GeoJson::validate([
            'type' => 'Point',
            'coordinates' => [51.0, 35.0],
            'properties' => ['radius' => 120],
        ], 'circle');

        $this->assertTrue($result['valid']);
    }
}
