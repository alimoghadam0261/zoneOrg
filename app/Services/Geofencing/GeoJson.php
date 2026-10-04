<?php

namespace App\Services\Geofencing;

use InvalidArgumentException;

/**
 * GeoJSON helpers: normalisation, validation, bounding-box extraction.
 *
 * Supported geometries:
 *  - polygon / rectangle → {"type":"Polygon","coordinates":[[[lng,lat],...]]}
 *  - circle             → {"type":"Point","coordinates":[lng,lat],"properties":{"radius":metres}}
 */
final class GeoJson
{
    public const EARTH_RADIUS_M = 6371000.0;

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}|null [minLat, minLng, maxLat, maxLng]
     */
    public static function bbox(array $geometry): ?array
    {
        if (($geometry['type'] ?? null) === 'Point') {
            $lat = (float) ($geometry['coordinates'][1] ?? 0);
            $lng = (float) ($geometry['coordinates'][0] ?? 0);
            $radius = (float) ($geometry['properties']['radius'] ?? 0);

            $dLat = $radius / 111320.0;
            $cos = max(cos(deg2rad($lat)), 0.01);
            $dLng = $radius / (111320.0 * $cos);

            return [$lat - $dLat, $lng - $dLng, $lat + $dLat, $lng + $dLng];
        }

        $ring = self::ring($geometry);

        if ($ring === []) {
            return null;
        }

        $minLat = $maxLat = $ring[0][0];
        $minLng = $maxLng = $ring[0][1];

        foreach ($ring as [$lat, $lng]) {
            $minLat = min($minLat, $lat);
            $maxLat = max($maxLat, $lat);
            $minLng = min($minLng, $lng);
            $maxLng = max($maxLng, $lng);
        }

        return [$minLat, $minLng, $maxLat, $maxLng];
    }

    /**
     * Flat ring of [lat, lng] pairs (never closed — ray casting does not need it).
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public static function ring(array $geometry): array
    {
        $type = $geometry['type'] ?? null;

        $raw = match ($type) {
            'Polygon' => $geometry['coordinates'][0] ?? [],
            'LineString' => $geometry['coordinates'] ?? [],
            'Rectangle' => $geometry['coordinates'][0] ?? [],
            default => [],
        };

        $ring = [];

        foreach ($raw as $point) {
            if (! is_array($point) || count($point) < 2) {
                continue;
            }
            $ring[] = [(float) $point[1], (float) $point[0]]; // GeoJSON is [lng, lat]
        }

        // Drop duplicate closing vertex (harmless, but keeps arrays tidy).
        $count = count($ring);
        if ($count > 1 && $ring[0] === $ring[$count - 1]) {
            array_pop($ring);
        }

        return $ring;
    }

    /**
     * @return array{lat: float, lng: float}
     */
    public static function center(array $geometry): array
    {
        if (($geometry['type'] ?? null) === 'Point') {
            return [
                'lat' => (float) ($geometry['coordinates'][1] ?? 0),
                'lng' => (float) ($geometry['coordinates'][0] ?? 0),
            ];
        }

        $bbox = self::bbox($geometry) ?? [0, 0, 0, 0];

        return [
            'lat' => ($bbox[0] + $bbox[2]) / 2,
            'lng' => ($bbox[1] + $bbox[3]) / 2,
        ];
    }

    /**
     * Force a canonical, storable geometry for the given zone type.
     *
     * @throws InvalidArgumentException when the geometry cannot be repaired
     */
    public static function normalize(?array $geometry, string $type): array
    {
        if (! is_array($geometry) || $geometry === []) {
            throw new InvalidArgumentException('هندسه منطقه (GeoJSON) ارسال نشده است.');
        }

        if ($type === 'circle') {
            if (($geometry['type'] ?? null) !== 'Point') {
                throw new InvalidArgumentException('هندسه منطقه دایره‌ای باید از نوع Point باشد.');
            }

            $radius = (int) ($geometry['properties']['radius'] ?? 0);

            if ($radius <= 0) {
                throw new InvalidArgumentException('شعاع منطقه دایره‌ای باید بزرگ‌تر از صفر باشد.');
            }

            return [
                'type' => 'Point',
                'coordinates' => [
                    round((float) $geometry['coordinates'][0], 8),
                    round((float) $geometry['coordinates'][1], 8),
                ],
                'properties' => ['radius' => $radius],
            ];
        }

        if (($geometry['type'] ?? null) === 'Point') {
            throw new InvalidArgumentException('هندسه منطقه چندضلعیی باید از نوع Polygon باشد.');
        }

        $ring = self::ring($geometry);

        if (count($ring) < 3) {
            throw new InvalidArgumentException('چندضلعی باید دست‌کم ۳ رأس داشته باشد.');
        }

        // A collapsed shape (identical or collinear vertices) has a zero-size
        // bbox and could never contain a ping — reject it at save time.
        if (self::area($ring) < 1e-12) {
            throw new InvalidArgumentException('مساحت چندضلعی صفر است؛ شکل بزرگ‌تری رسم کنید.');
        }

        $closed = array_map(fn (array $p) => [round($p[1], 8), round($p[0], 8)], $ring);
        $closed[] = $closed[0];

        return [
            'type' => 'Polygon',
            'coordinates' => [$closed],
        ];
    }

    /**
     * Shoelace area of a flat [lat, lng] ring, in square degrees.
     * (Unit-agnostic: only "is it zero?" matters here.)
     *
     * @param  array<int, array{0: float, 1: float}>  $ring
     */
    private static function area(array $ring): float
    {
        $sum = 0.0;
        $n = count($ring);

        for ($i = 0; $i < $n; $i++) {
            [$lat1, $lng1] = $ring[$i];
            [$lat2, $lng2] = $ring[($i + 1) % $n];

            $sum += ($lng1 * $lat2) - ($lng2 * $lat1);
        }

        return abs($sum) / 2;
    }

    /**
     * @return array{valid: bool, error: ?string}
     */
    public static function validate(?array $geometry, string $type): array
    {
        try {
            self::normalize($geometry, $type);
        } catch (InvalidArgumentException $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }

        return ['valid' => true, 'error' => null];
    }
}
