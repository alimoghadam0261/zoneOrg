<?php

namespace App\Services\Geofencing;

/**
 * Pure geometry primitives used by Phase-2 of the geofencing engine.
 */
final class Geometry
{
    /**
     * Ray-casting point-in-polygon test.
     *
     * @param  array<int, array{0: float, 1: float}>  $ring  [[lat, lng], ...]
     */
    public static function pointInPolygon(float $lat, float $lng, array $ring): bool
    {
        $n = count($ring);

        if ($n < 3) {
            return false;
        }

        $inside = false;

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $yi = $ring[$i][0];
            $xi = $ring[$i][1];
            $yj = $ring[$j][0];
            $xj = $ring[$j][1];

            $straddles = ($yi > $lat) !== ($yj > $lat);

            if (! $straddles) {
                continue;
            }

            // $yj - $yi is guaranteed non-zero here because of $straddles.
            $xIntersection = ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi;

            if ($lng < $xIntersection) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * Great-circle distance in metres (haversine).
     */
    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * GeoJson::EARTH_RADIUS_M * asin(min(1.0, sqrt($a)));
    }

    /**
     * True when ($lat,$lng) lies inside the circle described by centre + radius (metres).
     */
    public static function insideCircle(float $lat, float $lng, float $centerLat, float $centerLng, float $radius): bool
    {
        return self::distanceMeters($lat, $lng, $centerLat, $centerLng) <= $radius;
    }
}
