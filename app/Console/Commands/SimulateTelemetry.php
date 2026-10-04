<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Zone;
use App\Services\Geofencing\GeoJson;
use App\Services\Geofencing\Geometry;
use App\Services\Geofencing\PingProcessor;
use Illuminate\Console\Command;

/**
 * Keeps the demo dashboard alive: walks a subset of devices around the map
 * and pushes real fixes through the full geofencing pipeline.
 *
 *   php artisan zone:simulate                 # 60 ticks, 2 s apart, 40 devices
 *   php artisan zone:simulate --once          # a single batch
 *   php artisan zone:simulate --devices=100 --interval=10 --count=300
 *   php artisan zone:simulate --zone=TURBINE_HALL --once          # fixes inside one zone
 *   php artisan zone:simulate --zone=eeee --device=357208094642913 --count=20
 */
class SimulateTelemetry extends Command
{
    protected $signature = 'zone:simulate
        {--devices=40 : Number of devices to move}
        {--device= : UID (or id) of a single device to move instead of a random sample}
        {--zone= : Zone code (or id) — all fixes are generated inside this zone}
        {--interval=2 : Seconds between ticks (100 personnel / 10 s ≈ 10 tps)}
        {--count=60 : Number of ticks to run}
        {--once : Send a single batch and exit}';

    protected $description = 'Push synthetic GPS fixes through the geofencing engine (demo / load test)';

    /** @var array<int, array{lat: float, lng: float, heading: float, speed: float}> */
    protected array $track = [];

    protected ?Zone $zone = null;

    public function handle(PingProcessor $processor): int
    {
        if (! $this->resolveZone()) {
            return self::FAILURE;
        }

        $query = Device::query()
            ->whereNotNull('person_id')
            ->whereHas('person', fn ($q) => $q->where('status', 'active'))
            ->with('person:id,full_name,personnel_code');

        if (($uid = trim((string) $this->option('device'))) !== '') {
            $query->where(fn ($q) => $q->where('device_uid', $uid)->orWhere('id', (int) $uid));
        }

        $devices = $query
            ->inRandomOrder()
            ->limit(max(1, (int) $this->option('devices')))
            ->get();

        if ($devices->isEmpty()) {
            $this->error($this->option('device')
                ? 'دستگاه موردنظر با پرسنل فعال یافت نشد (شناسه یا کد دستگاه را بررسی کنید).'
                : 'هیچ دستگاه فعالی با پرسنل مرتبط یافت نشد. ابتدا db:seed را اجرا کنید.');

            return self::FAILURE;
        }

        $ticks = $this->option('once') ? 1 : max(1, (int) $this->option('count'));
        $interval = max(0, (int) $this->option('interval'));

        $this->info(sprintf(
            'شبیه‌سازی %d دستگاه × %d نوبت (هر %d ثانیه)%s…',
            $devices->count(),
            $ticks,
            $interval,
            $this->zone ? ' — داخل منطقه '.$this->zone->code : ''
        ));

        $violations = 0;
        $events = 0;
        $pings = 0;
        $started = microtime(true);

        for ($tick = 0; $tick < $ticks; $tick++) {
            foreach ($devices as $device) {
                $fix = $this->zone !== null ? $this->zoneFix($device) : $this->nextFix($device);

                $result = $processor->process($device, [
                    'lat' => round($fix['lat'], 7),
                    'lng' => round($fix['lng'], 7),
                    'accuracy' => $fix['accuracy'],
                    'speed' => $fix['speed'],
                    'heading' => round($fix['heading'], 1),
                    'battery' => $device->battery_level,
                    'captured_at' => now()->format('Y-m-d H:i:s'),
                ]);

                $pings++;
                $events += count($result->events);
                $violations += $result->hasViolation() ? 1 : 0;
            }

            if ($tick < $ticks - 1 && $interval > 0) {
                sleep($interval);
            }
        }

        $seconds = max(0.001, microtime(true) - $started);

        $this->newLine();
        $this->info(sprintf(
            '%d پینگ در %.1f ثانیه (%.1f پینگ/ثانیه) · %d رویداد · %d نقض',
            $pings,
            $seconds,
            $pings / $seconds,
            $events,
            $violations
        ));

        return self::SUCCESS;
    }

    /**
     * Resolve --zone=CODE|id into a zone whose geometry can actually contain
     * fixes. Rejects broken (zero-area) shapes with a pointer to the builder.
     */
    protected function resolveZone(): bool
    {
        $key = trim((string) $this->option('zone'));

        if ($key === '') {
            return true;
        }

        $this->zone = Zone::query()
            ->where('code', $key)
            ->when(ctype_digit($key), fn ($q) => $q->orWhere('id', (int) $key))
            ->first();

        if (! $this->zone) {
            $this->error("منطقه «{$key}» یافت نشد.");

            return false;
        }

        $validation = GeoJson::validate($this->zone->geometry, $this->zone->type);

        if (! $validation['valid']) {
            $this->error("منطقه «{$this->zone->code}» قابل استفاده نیست: {$validation['error']} — شکل را در سازنده مناطق اصلاح کنید.");
            $this->zone = null;

            return false;
        }

        if ($this->zone->type !== 'circle'
            && $this->zone->min_lat === $this->zone->max_lat
            && $this->zone->min_lng === $this->zone->max_lng) {
            $this->error("منطقه «{$this->zone->code}» مساحت صفر دارد؛ شکل را در سازنده مناطق دوباره رسم کنید.");
            $this->zone = null;

            return false;
        }

        return true;
    }

    /**
     * A fix inside the target zone: jitter around a base point that is
     * guaranteed (or best-effort sampled) to lie within the geometry.
     *
     * @return array{lat: float, lng: float, accuracy: float, speed: float, heading: float}
     */
    protected function zoneFix(Device $device): array
    {
        $zone = $this->zone;
        $state = $this->track[$device->id] ?? null;

        if ($state === null) {
            [$lat, $lng] = $this->randomPointInZone($zone);

            $state = [
                'lat' => $lat,
                'lng' => $lng,
                'heading' => random_int(0, 359),
                'speed' => random_int(5, 30) / 10,
            ];
        } else {
            $state['heading'] = fmod($state['heading'] + random_int(-45, 45) + 360, 360);
            $state['speed'] = max(0, min(40, $state['speed'] + random_int(-8, 8) / 10));

            $radians = deg2rad($state['heading']);
            $metres = $state['speed'] * 10;

            $lat = $state['lat'] + ($metres * cos($radians)) / 111320;
            $lng = $state['lng'] + ($metres * sin($radians)) / (111320 * max(cos(deg2rad($state['lat'])), 0.01));

            if ($this->insideZone($lat, $lng)) {
                $state['lat'] = $lat;
                $state['lng'] = $lng;
            } else {
                // Stepped out of the zone — re-seed inside it so every tick counts.
                [$state['lat'], $state['lng']] = $this->randomPointInZone($zone);
            }
        }

        $this->track[$device->id] = $state;

        return [
            'lat' => $state['lat'],
            'lng' => $state['lng'],
            'accuracy' => (float) random_int(4, 22),
            'speed' => $state['speed'],
            'heading' => round($state['heading'], 1),
        ];
    }

    /**
     * Uniform sample inside the zone: polar sampling for circles, rejection
     * sampling in the bbox for polygons (bbox centre as the last resort).
     *
     * @return array{0: float, 1: float} [lat, lng]
     */
    protected function randomPointInZone(Zone $zone): array
    {
        if ($zone->isCircle()) {
            $radius = max(1, (int) $zone->radius);
            $cos = max(cos(deg2rad((float) $zone->center_lat)), 0.01);

            $angle = deg2rad(random_int(0, 359));
            $r = $radius * sqrt(random_int(0, 9999) / 9999);

            return [
                (float) $zone->center_lat + ($r * sin($angle)) / 111320,
                (float) $zone->center_lng + ($r * cos($angle)) / (111320 * $cos),
            ];
        }

        $spanLat = (float) ($zone->max_lat - $zone->min_lat);
        $spanLng = (float) ($zone->max_lng - $zone->min_lng);
        $ring = $zone->ring();

        for ($attempt = 0; $attempt < 64; $attempt++) {
            $lat = (float) $zone->min_lat + (random_int(0, 1000000) / 1000000) * $spanLat;
            $lng = (float) $zone->min_lng + (random_int(0, 1000000) / 1000000) * $spanLng;

            if (Geometry::pointInPolygon($lat, $lng, $ring)) {
                return [$lat, $lng];
            }
        }

        return [
            ((float) $zone->min_lat + (float) $zone->max_lat) / 2,
            ((float) $zone->min_lng + (float) $zone->max_lng) / 2,
        ];
    }

    protected function insideZone(float $lat, float $lng): bool
    {
        $zone = $this->zone;

        if ($zone->isCircle()) {
            return Geometry::insideCircle(
                $lat,
                $lng,
                (float) $zone->center_lat,
                (float) $zone->center_lng,
                (float) $zone->radius
            );
        }

        return Geometry::pointInPolygon($lat, $lng, $zone->ring());
    }

    /**
     * @return array{lat: float, lng: float, accuracy: float, speed: float, heading: float}
     */
    protected function nextFix(Device $device): array
    {
        $state = $this->track[$device->id] ?? null;

        if ($state === null) {
            $ping = $device->pings()->latest('captured_at')->first();

            $lat = $ping ? (float) $ping->lat : (float) config('zone.map.center.0');
            $lng = $ping ? (float) $ping->lng : (float) config('zone.map.center.1');

            $state = [
                'lat' => $lat,
                'lng' => $lng,
                'heading' => random_int(0, 359),
                'speed' => random_int(5, 30) / 10,
            ];
        }

        // Random walk: turn a little, move along the heading.
        $state['heading'] = fmod($state['heading'] + random_int(-45, 45) + 360, 360);
        $state['speed'] = max(0, min(40, $state['speed'] + random_int(-8, 8) / 10));

        $radians = deg2rad($state['heading']);
        $metres = $state['speed'] * 10; // one tick ≈ 10 s

        $state['lat'] += ($metres * cos($radians)) / 111320;
        $state['lng'] += ($metres * sin($radians)) / (111320 * max(cos(deg2rad($state['lat'])), 0.01));

        // Keep the walk inside a ~1 km box around the map centre so markers
        // never drift off-screen.
        $center = config('zone.map.center');
        $state['lat'] = max($center[0] - 0.006, min($center[0] + 0.006, $state['lat']));
        $state['lng'] = max($center[1] - 0.008, min($center[1] + 0.008, $state['lng']));

        // GPS noise
        $state['lat'] += random_int(-40, 40) / 1000000;
        $state['lng'] += random_int(-40, 40) / 1000000;

        $this->track[$device->id] = $state;

        return [
            'lat' => $state['lat'],
            'lng' => $state['lng'],
            'accuracy' => (float) random_int(4, 22),
            'speed' => $state['speed'],
            'heading' => $state['heading'],
        ];
    }
}
