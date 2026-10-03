<?php

namespace App\Services\Geofencing;

use App\Models\Person;

/**
 * Two-phase geofencing evaluation for a single coordinate fix.
 */
class ZoneEvaluator
{
    public function __construct(
        private readonly ZoneIndex $index,
        private readonly AccessRuleResolver $accessRules,
    ) {}

    /**
     * @return array<int, array{zone: \App\Models\Zone, access: string}>
     */
    public function evaluate(float $lat, float $lng, Person $person, ?\Illuminate\Support\Carbon $moment = null): array
    {
        $matches = [];

        foreach ($this->index->candidates($lat, $lng) as $entry) {
            if (! $this->index->contains($entry, $lat, $lng)) {
                continue;
            }

            $matches[] = [
                'zone' => $entry['zone'],
                'access' => $this->accessRules->resolve($entry['zone'], $person, $moment),
            ];
        }

        return $matches;
    }

    /**
     * Zones containing a coordinate, ignoring access rules (used by the map layers).
     *
     * @return array<int, \App\Models\Zone>
     */
    public function zonesAt(float $lat, float $lng): array
    {
        $zones = [];

        foreach ($this->index->candidates($lat, $lng) as $entry) {
            if ($this->index->contains($entry, $lat, $lng)) {
                $zones[] = $entry['zone'];
            }
        }

        return $zones;
    }

    public function index(): ZoneIndex
    {
        return $this->index;
    }
}
