<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Geofencing\PingProcessor;
use Illuminate\Console\Command;

/**
 * Keeps the demo dashboard alive: walks a subset of devices around the map
 * and pushes real fixes through the full geofencing pipeline.
 *
 *   php artisan zone:simulate                 # 60 ticks, 2 s apart, 40 devices
 *   php artisan zone:simulate --once          # a single batch
 *   php artisan zone:simulate --devices=100 --interval=10 --count=300
 */
class SimulateTelemetry extends Command
{
    protected $signature = 'zone:simulate
        {--devices=40 : Number of devices to move}
        {--interval=2 : Seconds between ticks (100 personnel / 10 s ≈ 10 tps)}
        {--count=60 : Number of ticks to run}
        {--once : Send a single batch and exit}';

    protected $description = 'Push synthetic GPS fixes through the geofencing engine (demo / load test)';

    /** @var array<int, array{lat: float, lng: float, heading: float, speed: float}> */
    protected array $track = [];

    public function handle(PingProcessor $processor): int
    {
        $devices = Device::query()
            ->whereNotNull('person_id')
            ->whereHas('person', fn ($q) => $q->where('status', 'active'))
            ->with('person:id,full_name,personnel_code')
            ->inRandomOrder()
            ->limit(max(1, (int) $this->option('devices')))
            ->get();

        if ($devices->isEmpty()) {
            $this->error('هیچ دستگاه فعالی با پرسنل مرتبط یافت نشد. ابتدا db:seed را اجرا کنید.');

            return self::FAILURE;
        }

        $ticks = $this->option('once') ? 1 : max(1, (int) $this->option('count'));
        $interval = max(0, (int) $this->option('interval'));

        $this->info(sprintf(
            'شبیه‌سازی %d دستگاه × %d نوبت (هر %d ثانیه)…',
            $devices->count(),
            $ticks,
            $interval
        ));

        $violations = 0;
        $events = 0;
        $pings = 0;
        $started = microtime(true);

        for ($tick = 0; $tick < $ticks; $tick++) {
            foreach ($devices as $device) {
                $fix = $this->nextFix($device);

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
