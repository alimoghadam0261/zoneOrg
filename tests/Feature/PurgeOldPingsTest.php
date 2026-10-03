<?php

namespace Tests\Feature;

use App\Models\LocationPing;
use App\Models\Person;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeOldPingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_purges_only_pings_older_than_the_retention_window(): void
    {
        $person = Person::factory()->create();
        $device = $person->devices()->create([
            'device_uid' => 'TAG-PURGE-01',
            'type' => 'gps_tag',
            'battery_level' => 90,
        ]);

        $old = LocationPing::create([
            'device_id' => $device->id,
            'person_id' => $person->id,
            'lat' => 35.0, 'lng' => 51.0, 'accuracy' => 5,
            'captured_at' => now()->subDays(8),
            'created_at' => now()->subDays(8),
        ]);

        $edge = LocationPing::create([
            'device_id' => $device->id,
            'person_id' => $person->id,
            'lat' => 35.0, 'lng' => 51.0, 'accuracy' => 5,
            'captured_at' => now()->subDays(6)->subHours(23),
            'created_at' => now()->subDays(6)->subHours(23),
        ]);

        $fresh = LocationPing::create([
            'device_id' => $device->id,
            'person_id' => $person->id,
            'lat' => 35.0, 'lng' => 51.0, 'accuracy' => 5,
            'captured_at' => now()->subMinutes(5),
            'created_at' => now(),
        ]);

        $this->artisan('zone:purge-old-pings')
            ->assertSuccessful();

        $this->assertDatabaseMissing('location_pings', ['id' => $old->id]);
        $this->assertDatabaseHas('location_pings', ['id' => $edge->id]);
        $this->assertDatabaseHas('location_pings', ['id' => $fresh->id]);
    }

    public function test_dry_run_reports_without_deleting(): void
    {
        $person = Person::factory()->create();
        $device = $person->devices()->create([
            'device_uid' => 'TAG-PURGE-02',
            'type' => 'gps_tag',
            'battery_level' => 90,
        ]);

        LocationPing::create([
            'device_id' => $device->id,
            'person_id' => $person->id,
            'lat' => 35.0, 'lng' => 51.0, 'accuracy' => 5,
            'captured_at' => now()->subDays(30),
            'created_at' => now()->subDays(30),
        ]);

        $this->artisan('zone:purge-old-pings', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(1, LocationPing::count());
    }

    public function test_retention_window_can_be_overridden(): void
    {
        $person = Person::factory()->create();
        $device = $person->devices()->create([
            'device_uid' => 'TAG-PURGE-03',
            'type' => 'gps_tag',
            'battery_level' => 90,
        ]);

        $twoDaysOld = LocationPing::create([
            'device_id' => $device->id,
            'person_id' => $person->id,
            'lat' => 35.0, 'lng' => 51.0, 'accuracy' => 5,
            'captured_at' => now()->subDays(2),
            'created_at' => now()->subDays(2),
        ]);

        $this->artisan('zone:purge-old-pings', ['--days' => 1])->assertSuccessful();

        $this->assertDatabaseMissing('location_pings', ['id' => $twoDaysOld->id]);
    }

    public function test_purge_is_scheduled_daily(): void
    {
        $events = \Illuminate\Support\Facades\Schedule::events();

        $found = collect($events)->contains(
            fn ($event) => str_contains($event->command ?? '', 'zone:purge-old-pings')
        );

        $this->assertTrue($found, 'zone:purge-old-pings must be registered on the scheduler.');
    }
}
