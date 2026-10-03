<?php

namespace App\Services\Geofencing;

use App\Models\Zone;
use Illuminate\Support\Facades\Cache;

/**
 * In-memory (cached) index of active zones with their bounding boxes.
 *
 * Phase-1 of the engine: reject zones whose bbox cannot contain the ping, so
 * the expensive ray-casting only ever runs on a handful of candidates. The
 * whole index is refreshed at most every ZONE_ZONE_CACHE_TTL seconds instead
 * of being queried on every ping.
 */
class ZoneIndex
{
    public const CACHE_KEY = 'zone.index.active_v1';

    private ?array $entries = null;

    /**
     * @return array<int, array{zone: Zone, min_lat: float, max_lat: float, min_lng: float, max_lng: float, ring: array, center_lat: ?float, center_lng: ?float, radius: ?int}>
     */
    public function entries(): array
    {
        if ($this->entries !== null) {
            return $this->entries;
        }

        $zones = Cache::remember(
            self::CACHE_KEY,
            (int) config('zone.zone_cache_ttl', 60),
            fn () => Zone::query()->active()->with('accessRules')->get()->all()
        );

        $this->entries = [];

        foreach ($zones as $zone) {
            $this->entries[] = [
                'zone' => $zone,
                'min_lat' => (float) $zone->min_lat,
                'max_lat' => (float) $zone->max_lat,
                'min_lng' => (float) $zone->min_lng,
                'max_lng' => (float) $zone->max_lng,
                'ring' => $zone->isCircle() ? [] : $zone->ring(),
                'center_lat' => $zone->center_lat,
                'center_lng' => $zone->center_lng,
                'radius' => $zone->radius,
            ];
        }

        return $this->entries;
    }

    /**
     * Phase-1: bounding-box filter.
     *
     * @return array<int, array{zone: Zone, min_lat: float, max_lat: float, min_lng: float, max_lng: float, ring: array, center_lat: ?float, center_lng: ?float, radius: ?int}>
     */
    public function candidates(float $lat, float $lng): array
    {
        $out = [];

        foreach ($this->entries() as $entry) {
            if ($lat < $entry['min_lat'] || $lat > $entry['max_lat']) {
                continue;
            }

            if ($lng < $entry['min_lng'] || $lng > $entry['max_lng']) {
                continue;
            }

            $out[] = $entry;
        }

        return $out;
    }

    /**
     * Phase-2: exact containment (ray-casting for polygons, haversine for circles).
     */
    public function contains(array $entry, float $lat, float $lng): bool
    {
        if ($entry['zone']->type === 'circle') {
            if ($entry['center_lat'] === null || $entry['center_lng'] === null || ! $entry['radius']) {
                return false;
            }

            return Geometry::insideCircle(
                $lat,
                $lng,
                (float) $entry['center_lat'],
                (float) $entry['center_lng'],
                (float) $entry['radius']
            );
        }

        if ($entry['ring'] === []) {
            return false;
        }

        return Geometry::pointInPolygon($lat, $lng, $entry['ring']);
    }

    public function forget(): void
    {
        $this->entries = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Fast lookup used when replaying state for a zone that may have changed.
     */
    public function zone(int $zoneId): ?Zone
    {
        foreach ($this->entries() as $entry) {
            if ($entry['zone']->id === $zoneId) {
                return $entry['zone'];
            }
        }

        return null;
    }
}
