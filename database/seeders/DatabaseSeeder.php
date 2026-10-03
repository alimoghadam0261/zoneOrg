<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\LocationPing;
use App\Models\Person;
use App\Models\ZoneEventLog;
use App\Services\Geofencing\PingProcessor;
use App\Services\Geofencing\ZoneIndex;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Fixed demo credentials for the security officer account.
     */
    public const DEMO_EMAIL = 'admin@zone.local';

    public const DEMO_PASSWORD = 'password';

    /** A stable token so the API can be exercised from the terminal right after seeding. */
    public const DEMO_DEVICE_TOKEN = 'zone_dev_token_00000000000000000000000001';

    public function run(): void
    {
        // A fresh dataset must not inherit the debounce state (zone.state.person.*)
        // or the zone index from a previous run — both live in the cache store.
        Cache::flush();

        $this->call(ZoneSeeder::class);

        Cache::forget(ZoneIndex::CACHE_KEY);
        app(ZoneIndex::class)->forget();

        $this->seedUsers();
        $this->seedPeopleAndDevices();
        $this->seedTelemetry();

        app(ZoneIndex::class)->forget();
    }

    protected function seedUsers(): void
    {
        \App\Models\User::query()->updateOrCreate(
            ['email' => self::DEMO_EMAIL],
            ['name' => 'احمدی', 'password' => self::DEMO_PASSWORD]
        );
    }

    protected function seedPeopleAndDevices(): void
    {
        if (Person::query()->count() > 0) {
            return;
        }

        $departments = ['اپراتوری', 'تعمیرات مکانیک', 'تعمیرات برق', 'کنترل کیفیت', 'HSE', 'مدیریت', 'لجستیک'];

        $people = [];

        for ($i = 1; $i <= 100; $i++) {
            $contract = match ($i % 20) {
                0 => 'visitor',
                1, 2, 3, 4 => 'contractor',
                default => 'employee',
            };

            $people[] = [
                'personnel_code' => 'P-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'full_name' => fake()->name('male'),
                'national_id' => str_pad((string) $i, 10, '0', STR_PAD_LEFT),
                'department' => $departments[$i % count($departments)],
                'contract_type' => $contract,
                'avatar_url' => null,
                'status' => $i === 99 ? 'inactive' : 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Person::query()->insert($people);

        $devices = [];
        $firstPersonId = Person::query()->min('id');

        Person::query()->orderBy('id')->chunkById(50, function ($people) use ($firstPersonId) {
            foreach ($people as $person) {
                $device = new Device([
                    'device_uid' => sprintf('TAG-%04d-%s', $person->id, strtoupper(Str::random(4))),
                    'type' => $person->contract_type === 'visitor' ? 'mobile_app' : 'gps_tag',
                    'person_id' => $person->id,
                    'battery_level' => random_int(25, 100),
                    'last_seen_at' => now()->subMinutes(random_int(1, 30)),
                ]);

                // Only the first device shares the well-known demo token
                // (tokens are unique by design — the rest are issued from the UI).
                $device->api_token = $person->id === $firstPersonId
                    ? hash('sha256', self::DEMO_DEVICE_TOKEN)
                    : null;
                $device->save();
            }
        });
    }

    /**
     * Feed the rolling buffer with a realistic hour of traffic so the
     * dashboard, the alert feed and the purge command all show something.
     */
    protected function seedTelemetry(): void
    {
        if (LocationPing::query()->count() > 0) {
            return;
        }

        $fuel = \App\Models\Zone::query()->where('code', 'SOULEH_01')->first();
        $admin = \App\Models\Zone::query()->where('code', 'ADMIN_01')->first();

        $centerOf = fn (?\App\Models\Zone $zone) => $zone
            ? \App\Services\Geofencing\GeoJson::center($zone->geometry)
            : null;

        $fuelCenter = $centerOf($fuel);
        $adminCenter = $centerOf($admin);

        $processor = app(PingProcessor::class);

        $people = Person::query()->with('device')->where('status', 'active')->orderBy('id')->get();

        foreach ($people as $index => $person) {
            $device = $person->device;

            if (! $device) {
                continue;
            }

            // A handful of people stand inside the forbidden fuel depot.
            $inForbidden = in_array($index, [7, 23, 51, 78], true);
            $sloppyGps = $index === 23; // accuracy > 25 m → must NOT raise a violation

            $base = $inForbidden && $fuelCenter
                ? [$fuelCenter['lat'] + 0.0003, $fuelCenter['lng'] - 0.0002]
                : ($adminCenter
                    ? [$adminCenter['lat'] + 0.0004, $adminCenter['lng'] + 0.0002]
                    : [35.6892 + (random_int(-400, 400) / 100000), 51.3890 + (random_int(-400, 400) / 100000)]);

            $steps = $inForbidden ? 5 : 4;

            for ($step = 0; $step < $steps; $step++) {
                // Newest fix lands ~1 minute in the past so the seeded fleet
                // renders as ONLINE on the dashboard.
                $minutesAgo = ($steps - 1 - $step) * 12 + 1;

                $processor->process($device, [
                    'lat' => round($base[0] + random_int(-60, 60) / 1000000, 7),
                    'lng' => round($base[1] + random_int(-60, 60) / 1000000, 7),
                    'accuracy' => $sloppyGps ? (float) random_int(35, 70) : (float) random_int(4, 18),
                    'speed' => random_int(0, 30) / 10,
                    'heading' => (float) random_int(0, 359),
                    'battery' => $device->battery_level,
                    'captured_at' => now()->subMinutes($minutesAgo)->format('Y-m-d H:i:s'),
                ]);
            }
        }

        // Guarantee a couple of open, recent violations for the alert feed.
        if ($fuel && $fuelCenter) {
            ZoneEventLog::query()->insert([
                [
                    'person_id' => Person::query()->value('id'),
                    'zone_id' => $fuel->id,
                    'device_id' => Device::query()->value('id'),
                    'event_type' => 'violation_entered',
                    'severity' => $fuel->severity_level,
                    'location_snapshot' => json_encode([
                        'lat' => $fuelCenter['lat'],
                        'lng' => $fuelCenter['lng'],
                        'accuracy' => 8.4,
                        'personnel_code' => Person::query()->value('personnel_code'),
                        'zone_code' => $fuel->code,
                    ], JSON_UNESCAPED_UNICODE),
                    'is_resolved' => false,
                    'created_at' => now()->subMinutes(6),
                ],
            ]);
        }
    }
}
