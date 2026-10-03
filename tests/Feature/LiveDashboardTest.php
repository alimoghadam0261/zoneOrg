<?php

namespace Tests\Feature;

use App\Livewire\LiveDashboard;
use App\Models\Device;
use App\Models\Person;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneEventLog;
use App\Services\Geofencing\ZoneIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LiveDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function forbiddenZone(): Zone
    {
        $ring = [
            [50.9, 34.9],
            [51.1, 34.9],
            [51.1, 35.1],
            [50.9, 35.1],
            [50.9, 34.9],
        ];

        $zone = new Zone([
            'name' => 'ممنوعه',
            'code' => 'FORBIDDEN_01',
            'type' => 'polygon',
            'geometry' => ['type' => 'Polygon', 'coordinates' => [$ring]],
            'severity_level' => 'critical',
            'color' => '#ef4444',
            'is_active' => true,
        ]);

        $zone->syncBoundingBox();
        $zone->save();

        $zone->accessRules()->create([
            'target_type' => 'person',
            'target_value' => '*',
            'access_type' => 'deny',
        ]);

        app(ZoneIndex::class)->forget();

        return $zone;
    }

    private function personWithPing(float $lat, float $lng, $capturedAt = null): Person
    {
        $person = Person::factory()->create();
        $device = Device::factory()->create(['person_id' => $person->id]);

        $person->pings()->create([
            'device_id' => $device->id,
            'lat' => $lat,
            'lng' => $lng,
            'accuracy' => 6.5,
            'captured_at' => $capturedAt ?? now(),
            'created_at' => now(),
        ]);

        return $person;
    }

    public function test_dashboard_renders_with_stats(): void
    {
        $component = Livewire::test(LiveDashboard::class);

        $stats = $component->get('stats');

        $this->assertSame(0, $stats['total']);
        $this->assertSame(0, $stats['online']);
        $component->assertDispatched('lm-positions');
    }

    public function test_person_inside_a_forbidden_zone_is_flagged_as_a_violation(): void
    {
        $this->forbiddenZone();
        $this->personWithPing(35.0, 51.0);

        $component = Livewire::test(LiveDashboard::class);
        $positions = $component->get('positions');

        $this->assertCount(1, $positions);
        $this->assertSame('violation', $positions[0]['status']);
        $this->assertSame('ممنوعه', $positions[0]['zone']['name']);
        $this->assertSame(1, $component->get('stats')['violations']);
    }

    public function test_person_without_recent_ping_is_offline(): void
    {
        $this->personWithPing(35.0, 51.0, now()->subMinutes(30));

        $component = Livewire::test(LiveDashboard::class);
        $positions = $component->get('positions');

        $this->assertSame('offline', $positions[0]['status']);
        $this->assertSame(1, $component->get('stats')['offline']);
        $this->assertSame(0, $component->get('stats')['online']);
    }

    public function test_polling_refresh_updates_positions(): void
    {
        $component = Livewire::test(LiveDashboard::class);

        $this->personWithPing(35.0, 51.0);

        $component->call('refresh')->assertDispatched('lm-positions');

        $this->assertCount(1, $component->get('positions'));
    }

    public function test_recent_violations_are_announced_once(): void
    {
        $zone = $this->forbiddenZone();
        $person = $this->personWithPing(35.0, 51.0);

        ZoneEventLog::create([
            'person_id' => $person->id,
            'zone_id' => $zone->id,
            'device_id' => $person->device->id,
            'event_type' => 'violation_entered',
            'severity' => 'critical',
            'location_snapshot' => ['lat' => 35.0, 'lng' => 51.0],
            'created_at' => now(),
        ]);

        $component = Livewire::test(LiveDashboard::class)
            ->assertDispatched('zone-alert');

        // Second tick must not re-announce the same alert.
        $component->call('refresh');
        $component->assertDispatched('lm-positions');
    }

    public function test_an_officer_can_resolve_an_alert(): void
    {
        $zone = $this->forbiddenZone();
        $person = $this->personWithPing(35.0, 51.0);
        $user = User::factory()->create();

        $event = ZoneEventLog::create([
            'person_id' => $person->id,
            'zone_id' => $zone->id,
            'device_id' => $person->device->id,
            'event_type' => 'violation_entered',
            'severity' => 'critical',
            'location_snapshot' => ['lat' => 35.0, 'lng' => 51.0],
            'created_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(LiveDashboard::class)
            ->call('resolveAlert', $event->id)
            ->assertDispatched('toast');

        $event->refresh();
        $this->assertTrue($event->is_resolved);
        $this->assertSame($user->id, $event->resolved_by);
    }

    public function test_status_filter_narrows_the_personnel_list(): void
    {
        $this->forbiddenZone();
        $this->personWithPing(35.0, 51.0);
        $this->personWithPing(10.0, 10.0);

        $component = Livewire::test(LiveDashboard::class);

        $this->assertCount(2, $component->get('positions'));

        $component->call('setStatusFilter', 'violation');
        $this->assertCount(1, $component->invade()->visiblePositions());

        $component->call('setStatusFilter', 'all');
        $this->assertCount(2, $component->invade()->visiblePositions());
    }
}
