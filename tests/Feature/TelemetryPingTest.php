<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Person;
use App\Models\Zone;
use App\Models\ZoneEventLog;
use App\Services\Geofencing\ZoneIndex;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetryPingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ZoneIndex::class)->forget();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'lat' => 35.0,
            'lng' => 51.0,
            'accuracy' => 8.5,
            'speed' => 1.2,
            'heading' => 180.0,
            'battery' => 88,
            'captured_at' => now()->format('Y-m-d H:i:s'),
        ], $overrides);
    }

    public function test_request_without_token_is_rejected(): void
    {
        $this->postJson('/api/v1/telemetry/ping', $this->payload())
            ->assertStatus(401);
    }

    public function test_request_with_unknown_token_is_rejected(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer '.str_repeat('x', 40)])
            ->postJson('/api/v1/telemetry/ping', $this->payload())
            ->assertStatus(401);
    }

    public function test_invalid_coordinates_are_rejected(): void
    {
        $device = Device::factory()->create(['person_id' => Person::factory()->create()->id]);
        $token = $device->issueToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/telemetry/ping', $this->payload(['lat' => 999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat']);
    }

    public function test_valid_ping_is_persisted_and_updates_device(): void
    {
        $device = Device::factory()->create(['person_id' => Person::factory()->create()->id]);
        $token = $device->issueToken();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/telemetry/ping', $this->payload());

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseCount('location_pings', 1);
        $this->assertNotNull($device->refresh()->last_seen_at);
        $this->assertSame(88, $device->refresh()->battery_level);
    }

    public function test_two_consecutive_pings_are_required_to_raise_a_violation(): void
    {
        $zone = $this->forbiddenZone();
        $device = Device::factory()->create(['person_id' => Person::factory()->create()->id]);
        $token = $device->issueToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/telemetry/ping', $this->payload())
            ->assertOk()
            ->assertJsonPath('data.violation', false);

        // Debounce: a single fix must never raise a violation.
        $this->assertSame(0, ZoneEventLog::where('event_type', 'violation_entered')->count());

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/telemetry/ping', $this->payload())
            ->assertOk()
            ->assertJsonPath('data.violation', true);

        $this->assertSame(1, ZoneEventLog::where('event_type', 'violation_entered')->count());
        $this->assertDatabaseHas('zone_event_logs', [
            'zone_id' => $zone->id,
            'event_type' => 'violation_entered',
            'severity' => 'critical',
        ]);

        // Lingering is throttled: an immediate third fix must not re-alert
        // (the person is still flagged as `access: deny` inside the zone).
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/telemetry/ping', $this->payload())
            ->assertOk()
            ->assertJsonPath('data.violation', false);

        $this->assertTrue(collect($response->json('data.zones'))->contains('access', 'deny'));
        $this->assertSame(0, ZoneEventLog::where('event_type', 'violation_lingering')->count());
    }

    public function test_inaccurate_fix_does_not_raise_a_violation(): void
    {
        $this->forbiddenZone();
        $device = Device::factory()->create(['person_id' => Person::factory()->create()->id]);
        $token = $device->issueToken();

        foreach ([1, 2, 3] as $attempt) {
            $this->withHeaders(['Authorization' => 'Bearer '.$token])
                ->postJson('/api/v1/telemetry/ping', $this->payload(['accuracy' => 60.0]))
                ->assertOk()
                ->assertJsonPath('data.violation', false);
        }

        $this->assertSame(0, ZoneEventLog::count());
    }

    public function test_leaving_a_zone_logs_an_exit_event(): void
    {
        $this->allowedZone();
        $device = Device::factory()->create(['person_id' => Person::factory()->create()->id]);
        $token = $device->issueToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/telemetry/ping', $this->payload())
            ->assertOk();

        $this->assertSame(1, ZoneEventLog::where('event_type', 'entered')->count());

        // 50 km away — outside every zone.
        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/telemetry/ping', $this->payload(['lat' => 35.5, 'lng' => 51.5]))
            ->assertOk();

        $this->assertSame(1, ZoneEventLog::where('event_type', 'exited')->count());
    }

    public function test_department_rule_overrides_the_wildcard_deny_rule(): void
    {
        $zone = $this->forbiddenZone();
        $zone->accessRules()->create([
            'target_type' => 'department',
            'target_value' => 'تعمیرات برق',
            'access_type' => 'allow',
        ]);

        $person = Person::factory()->create(['department' => 'تعمیرات برق']);
        $device = Device::factory()->create(['person_id' => $person->id]);
        $token = $device->issueToken();

        foreach ([1, 2, 3] as $attempt) {
            $this->withHeaders(['Authorization' => 'Bearer '.$token])
                ->postJson('/api/v1/telemetry/ping', $this->payload())
                ->assertOk()
                ->assertJsonPath('data.violation', false);
        }

        // Access is granted, so an "entered" event exists but no violation.
        $this->assertSame(1, ZoneEventLog::where('event_type', 'entered')->count());
        $this->assertSame(0, ZoneEventLog::whereIn('event_type', ['violation_entered', 'violation_lingering'])->count());
    }

    public function test_ping_outside_any_zone_reports_no_zones(): void
    {
        $device = Device::factory()->create(['person_id' => Person::factory()->create()->id]);
        $token = $device->issueToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/telemetry/ping', $this->payload(['lat' => 10.0, 'lng' => 10.0]))
            ->assertOk()
            ->assertJsonPath('data.inside_any_zone', false)
            ->assertJsonPath('data.zones', []);
    }

    public function test_seeded_demo_token_works(): void
    {
        $this->seed();

        $this->withHeaders(['Authorization' => 'Bearer '.DatabaseSeeder::DEMO_DEVICE_TOKEN])
            ->postJson('/api/v1/telemetry/ping', $this->payload())
            ->assertOk();
    }

    /* -------------------------------------------------------------- */

    private function forbiddenZone(): Zone
    {
        $zone = $this->squareZone([
            'name' => 'ممنوعه',
            'code' => 'FORBIDDEN_01',
            'severity_level' => 'critical',
        ]);

        $zone->accessRules()->create([
            'target_type' => 'person',
            'target_value' => '*',
            'access_type' => 'deny',
        ]);

        app(ZoneIndex::class)->forget();

        return $zone;
    }

    private function allowedZone(): Zone
    {
        $zone = $this->squareZone([
            'name' => 'مجازه',
            'code' => 'ALLOWED_01',
            'severity_level' => 'low',
        ]);

        $zone->accessRules()->create([
            'target_type' => 'person',
            'target_value' => '*',
            'access_type' => 'allow',
        ]);

        app(ZoneIndex::class)->forget();

        return $zone;
    }

    private function squareZone(array $attributes = []): Zone
    {
        $ring = [
            [50.9, 34.9],
            [51.1, 34.9],
            [51.1, 35.1],
            [50.9, 35.1],
            [50.9, 34.9],
        ];

        $zone = new Zone(array_merge([
            'name' => 'منطقه',
            'code' => 'ZONE_'.uniqid(),
            'type' => 'polygon',
            'geometry' => ['type' => 'Polygon', 'coordinates' => [$ring]],
            'color' => '#ef4444',
            'severity_level' => 'high',
            'is_active' => true,
        ], $attributes));

        $zone->syncBoundingBox();
        $zone->save();

        return $zone;
    }
}
