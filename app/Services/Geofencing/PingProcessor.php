<?php

namespace App\Services\Geofencing;

use App\Models\Device;
use App\Models\LocationPing;
use App\Models\Zone;
use App\Models\ZoneEventLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Full ping pipeline: persist → two-phase geofence → debounce → audit trail.
 *
 * Debounce / false-alarm suppression:
 *   • a violation requires accuracy <= ZONE_MIN_ACCURACY_METERS
 *   • AND ZONE_CONFIRM_PINGS consecutive fixes inside the same forbidden zone
 *   • lingering violations are re-logged at most once per ZONE_LINGERING_SECONDS
 */
class PingProcessor
{
    public function __construct(
        private readonly ZoneEvaluator $evaluator,
        private readonly ZoneIndex $index,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(Device $device, array $payload): PingResult
    {
        $person = $device->person;

        abort_unless($person !== null, 422, 'این دستگاه به پرسنل متصل نیست.');

        $lat = (float) $payload['lat'];
        $lng = (float) $payload['lng'];
        $accuracy = (float) $payload['accuracy'];
        $capturedAt = isset($payload['captured_at'])
            ? Carbon::parse($payload['captured_at'])
            : now();

        // 1) Single fast INSERT into the 7-day rolling buffer.
        $ping = LocationPing::create([
            'device_id' => $device->id,
            'person_id' => $person->id,
            'lat' => $lat,
            'lng' => $lng,
            'accuracy' => $accuracy,
            'speed' => $payload['speed'] ?? null,
            'heading' => $payload['heading'] ?? null,
            'captured_at' => $capturedAt,
            'created_at' => now(),
        ]);

        $device->touchPing((int) ($payload['battery'] ?? $device->battery_level));

        // 2) Two-phase geofencing (bbox fast filter → exact test) + access rules.
        $matches = $this->evaluator->evaluate($lat, $lng, $person, $capturedAt);

        // 3) Debounced state machine per (person, zone).
        $stateKey = $this->stateKey($person->id);
        /** @var array<int, array<string, mixed>> $state */
        $state = Cache::get($stateKey, []);
        $events = [];
        $containedIds = [];

        foreach ($matches as $match) {
            $containedIds[] = $match['zone']->id;
        }

        // 3a) Zones the person just left.
        foreach ($state as $zoneId => $zoneState) {
            if (in_array($zoneId, $containedIds, true)) {
                continue;
            }

            if (! empty($zoneState['inside'])) {
                $zone = $this->index->zone((int) $zoneId);

                if ($zone) {
                    $events[] = $this->log('exited', $zone, $person, $device, $lat, $lng, $accuracy, $capturedAt, $payload);
                }
            }

            unset($state[$zoneId]);
        }

        // 3b) Zones the person is currently inside.
        $consecutive = 0;

        foreach ($matches as $match) {
            $zone = $match['zone'];
            $access = $match['access'];
            $zoneId = $zone->id;

            $zoneState = $state[$zoneId] ?? [
                'inside' => false,
                'consecutive' => 0,
                'confirmed' => false,
                'last_lingering' => null,
                'access' => $access,
            ];

            $wasInside = (bool) $zoneState['inside'];
            $zoneState['inside'] = true;
            $zoneState['access'] = $access;
            $zoneState['consecutive'] = $wasInside ? ((int) $zoneState['consecutive'] + 1) : 1;

            $isAccurate = $accuracy <= (float) config('zone.min_accuracy_meters');
            $confirmPings = max(1, (int) config('zone.confirm_pings'));

            if ($access === 'deny') {
                if ($isAccurate && (int) $zoneState['consecutive'] >= $confirmPings) {
                    if (! $zoneState['confirmed']) {
                        $zoneState['confirmed'] = true;
                        // Start the lingering countdown at confirmation time (using the
                        // fix timestamp) so the first `violation_lingering` waits for
                        // the configured interval.
                        $zoneState['last_lingering'] = $capturedAt->timestamp;
                        $events[] = $this->log('violation_entered', $zone, $person, $device, $lat, $lng, $accuracy, $capturedAt, $payload);
                    } else {
                        $interval = (int) config('zone.lingering_interval_seconds');
                        $last = (int) $zoneState['last_lingering'];

                        if ($capturedAt->timestamp - $last >= $interval) {
                            $zoneState['last_lingering'] = $capturedAt->timestamp;
                            $events[] = $this->log('violation_lingering', $zone, $person, $device, $lat, $lng, $accuracy, $capturedAt, $payload);
                        }
                    }
                }
            } elseif (! $wasInside) {
                $events[] = $this->log('entered', $zone, $person, $device, $lat, $lng, $accuracy, $capturedAt, $payload);
            }

            $state[$zoneId] = $zoneState;
            $consecutive = max($consecutive, (int) $zoneState['consecutive']);
        }

        Cache::put($stateKey, $state, (int) config('zone.state_ttl_seconds'));

        return new PingResult($ping->id, $matches, $events, $matches !== [], $consecutive);
    }

    private function stateKey(int $personId): string
    {
        return sprintf('zone.state.person.%d', $personId);
    }

    private function log(
        string $type,
        Zone $zone,
        $person,
        Device $device,
        float $lat,
        float $lng,
        float $accuracy,
        Carbon $capturedAt,
        array $payload,
    ): ZoneEventLog {
        return ZoneEventLog::create([
            'person_id' => $person->id,
            'zone_id' => $zone->id,
            'device_id' => $device->id,
            'event_type' => $type,
            'severity' => $zone->severity_level,
            'location_snapshot' => [
                'lat' => $lat,
                'lng' => $lng,
                'accuracy' => $accuracy,
                'speed' => $payload['speed'] ?? null,
                'heading' => $payload['heading'] ?? null,
                'captured_at' => $capturedAt->toDateTimeString(),
                'battery' => $payload['battery'] ?? null,
                'personnel_code' => $person->personnel_code,
                'zone_code' => $zone->code,
            ],
            'is_resolved' => false,
            'created_at' => now(),
        ]);
    }

    /**
     * Clear the debounce state of a person (used when zones/rules change).
     */
    public function flushState(int $personId): void
    {
        Cache::forget($this->stateKey($personId));
    }
}
