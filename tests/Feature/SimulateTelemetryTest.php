<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\LocationPing;
use App\Models\Person;
use App\Models\Zone;
use App\Services\Geofencing\Geometry;
use App\Services\Geofencing\ZoneIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulateTelemetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ZoneIndex::class)->forget();
    }

    private function squareZone(string $code = 'SIM_ZONE'): Zone
    {
        $zone = new Zone([
            'name' => 'منطقه شبیه‌سازی',
            'code' => $code,
            'type' => 'polygon',
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[
                [51.0, 35.0],
                [51.1, 35.0],
                [51.1, 35.1],
                [51.0, 35.1],
                [51.0, 35.0],
            ]]],
            'color' => '#6366f1',
            'severity_level' => 'medium',
            'is_active' => true,
        ]);

        $zone->syncBoundingBox();
        $zone->save();

        return $zone;
    }

    public function test_zone_option_generates_fixes_inside_that_zone(): void
    {
        $zone = $this->squareZone();
        $person = Person::factory()->create();
        $device = Device::factory()->create(['person_id' => $person->id]);

        $this->artisan('zone:simulate', [
            '--once' => true,
            '--zone' => 'SIM_ZONE',
            '--devices' => 5,
        ])->assertSuccessful();

        $pings = LocationPing::query()->where('device_id', $device->id)->get();

        $this->assertNotEmpty($pings);

        foreach ($pings as $ping) {
            $this->assertTrue(
                Geometry::pointInPolygon((float) $ping->lat, (float) $ping->lng, $zone->ring()),
                "ping ({$ping->lat}, {$ping->lng}) must fall inside zone {$zone->code}"
            );
        }
    }

    public function test_device_option_moves_only_the_named_device(): void
    {
        $this->squareZone();

        $target = Person::factory()->create();
        $targetDevice = Device::factory()->create([
            'person_id' => $target->id,
            'device_uid' => 'IMEI-SIM-001',
        ]);

        $other = Person::factory()->create();
        Device::factory()->create(['person_id' => $other->id]);

        $this->artisan('zone:simulate', [
            '--once' => true,
            '--zone' => 'SIM_ZONE',
            '--device' => 'IMEI-SIM-001',
        ])->assertSuccessful();

        $this->assertGreaterThan(0, LocationPing::query()->where('device_id', $targetDevice->id)->count());
        $this->assertSame(0, LocationPing::query()->where('device_id', '!=', $targetDevice->id)->count());
    }

    public function test_unknown_zone_is_rejected(): void
    {
        Person::factory()->create();

        $this->artisan('zone:simulate', [
            '--once' => true,
            '--zone' => 'NOPE_999',
        ])->assertFailed();

        $this->assertSame(0, LocationPing::count());
    }

    public function test_zero_area_zone_is_rejected_with_a_clear_message(): void
    {
        Person::factory()->create();

        // A collapsed rectangle: every vertex identical (single-click draw).
        $zone = new Zone([
            'name' => 'خراب',
            'code' => 'COLLAPSED',
            'type' => 'rectangle',
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[
                [51.357758, 35.711141],
                [51.357758, 35.711141],
                [51.357758, 35.711141],
                [51.357758, 35.711141],
            ]]],
            'color' => '#6366f1',
            'severity_level' => 'low',
            'is_active' => true,
        ]);

        // Bypass syncBoundingBox (it would reject the shape) — the row already
        // exists in production data, so the command must cope with it.
        $zone->min_lat = $zone->max_lat = 35.711141;
        $zone->min_lng = $zone->max_lng = 51.357758;
        $zone->save();

        $this->artisan('zone:simulate', [
            '--once' => true,
            '--zone' => 'COLLAPSED',
        ])->assertFailed();

        $this->assertSame(0, LocationPing::count());
    }

    public function test_multiple_ticks_stay_inside_the_zone(): void
    {
        $zone = $this->squareZone('WALK_ZONE');
        $person = Person::factory()->create();
        $device = Device::factory()->create(['person_id' => $person->id]);

        $this->artisan('zone:simulate', [
            '--zone' => 'WALK_ZONE',
            '--count' => 5,
            '--interval' => 0,
            '--devices' => 3,
        ])->assertSuccessful();

        $pings = LocationPing::query()->where('device_id', $device->id)->get();

        $this->assertCount(5, $pings);

        foreach ($pings as $ping) {
            $this->assertTrue(
                Geometry::pointInPolygon((float) $ping->lat, (float) $ping->lng, $zone->ring())
            );
        }
    }
}
