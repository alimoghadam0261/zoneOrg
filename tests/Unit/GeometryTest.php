<?php

namespace Tests\Unit;

use App\Services\Geofencing\Geometry;
use PHPUnit\Framework\TestCase;

class GeometryTest extends TestCase
{
    private array $square = [
        [0.0, 0.0],
        [0.0, 10.0],
        [10.0, 10.0],
        [10.0, 0.0],
    ];

    public function test_point_inside_polygon(): void
    {
        $this->assertTrue(Geometry::pointInPolygon(5.0, 5.0, $this->square));
        $this->assertTrue(Geometry::pointInPolygon(0.1, 0.1, $this->square));
    }

    public function test_point_outside_polygon(): void
    {
        $this->assertFalse(Geometry::pointInPolygon(11.0, 5.0, $this->square));
        $this->assertFalse(Geometry::pointInPolygon(-1.0, -1.0, $this->square));
        $this->assertFalse(Geometry::pointInPolygon(5.0, 15.0, $this->square));
    }

    public function test_concave_polygon_is_handled_by_ray_casting(): void
    {
        // U-shaped polygon: the notch in the middle is outside.
        $concave = [
            [0.0, 0.0],
            [0.0, 10.0],
            [4.0, 10.0],
            [4.0, 4.0],
            [6.0, 4.0],
            [6.0, 10.0],
            [10.0, 10.0],
            [10.0, 0.0],
        ];

        $this->assertTrue(Geometry::pointInPolygon(2.0, 5.0, $concave));
        $this->assertFalse(Geometry::pointInPolygon(5.0, 8.0, $concave));
    }

    public function test_polygon_with_fewer_than_three_vertices_is_rejected(): void
    {
        $this->assertFalse(Geometry::pointInPolygon(1.0, 1.0, [[0.0, 0.0], [1.0, 1.0]]));
    }

    public function test_haversine_distance_between_known_points(): void
    {
        // One degree of latitude ≈ 111.19 km
        $distance = Geometry::distanceMeters(0.0, 0.0, 1.0, 0.0);

        $this->assertEqualsWithDelta(111195, $distance, 500);
    }

    public function test_haversine_is_zero_for_identical_points(): void
    {
        $this->assertSame(0.0, Geometry::distanceMeters(35.6892, 51.3890, 35.6892, 51.3890));
    }

    public function test_inside_circle_uses_metre_radius(): void
    {
        // 0.001° latitude ≈ 111 m
        $this->assertTrue(Geometry::insideCircle(35.0005, 51.0, 35.0, 51.0, 120));
        $this->assertFalse(Geometry::insideCircle(35.0030, 51.0, 35.0, 51.0, 120));
    }
}
