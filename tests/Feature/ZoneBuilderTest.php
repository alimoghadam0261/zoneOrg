<?php

namespace Tests\Feature;

use App\Livewire\ZoneBuilder;
use App\Models\Person;
use App\Models\Zone;
use App\Services\Geofencing\ZoneIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ZoneBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function polygon(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [50.9, 34.9],
                [51.1, 34.9],
                [51.1, 35.1],
                [50.9, 35.1],
                [50.9, 34.9],
            ]],
        ];
    }

    public function test_drawing_a_shape_calculates_the_bounding_box_live(): void
    {
        Livewire::test(ZoneBuilder::class)
            ->call('onGeometry', $this->polygon(), 'polygon', null)
            ->assertSet('geometryError', null)
            ->assertSet('bbox', [34.9, 50.9, 35.1, 51.1]);
    }

    public function test_a_zone_can_be_saved_with_a_server_side_bbox(): void
    {
        Livewire::test(ZoneBuilder::class)
            ->call('openCreate')
            ->set('form.name', 'منطقه آزمایشی')
            ->set('form.code', 'TEST_01')
            ->set('form.severity_level', 'critical')
            ->set('form.default_access', 'deny')
            ->set('geometry', $this->polygon())
            ->set('geometryType', 'polygon')
            ->call('save')
            ->assertDispatched('toast');

        $zone = Zone::query()->where('code', 'TEST_01')->first();

        $this->assertNotNull($zone);
        $this->assertEqualsWithDelta(34.9, (float) $zone->min_lat, 0.0001);
        $this->assertEqualsWithDelta(51.1, (float) $zone->max_lng, 0.0001);
        $this->assertSame('critical', $zone->severity_level);
        $this->assertTrue($zone->is_active);

        // wildcard rule + explicit rules
        $this->assertSame(1, $zone->accessRules()->count());
        $this->assertSame('deny', $zone->accessRules()->first()->access_type);
    }

    public function test_saving_without_a_drawing_is_rejected(): void
    {
        Livewire::test(ZoneBuilder::class)
            ->call('openCreate')
            ->set('form.name', 'بدون شکل')
            ->set('form.code', 'NO_SHAPE')
            ->call('save')
            ->assertDispatched('toast');

        $this->assertSame(0, Zone::query()->count());
    }

    public function test_duplicate_codes_are_rejected(): void
    {
        $zone = Zone::factory()->create(['code' => 'DUP_01']);

        Livewire::test(ZoneBuilder::class)
            ->call('openCreate')
            ->set('form.name', 'کپی')
            ->set('form.code', 'DUP_01')
            ->set('geometry', $this->polygon())
            ->set('geometryType', 'polygon')
            ->call('save');

        $this->assertSame(1, Zone::query()->where('code', 'DUP_01')->count());
    }

    public function test_existing_zone_can_be_loaded_edited_and_deleted(): void
    {
        $zone = Zone::factory()->create();

        $component = Livewire::test(ZoneBuilder::class)
            ->call('openEdit', $zone->id)
            ->assertSet('editingId', $zone->id);

        $this->assertNotNull($component->get('geometry'));

        $component
            ->set('form.name', 'نام تازه')
            ->call('save')
            ->assertDispatched('toast');

        $this->assertSame('نام تازه', $zone->refresh()->name);

        $component->call('destroy', $zone->id)->assertDispatched('toast');
        $this->assertDatabaseMissing('zones', ['id' => $zone->id]);
    }

    public function test_toggling_a_zone_clears_the_evaluation_cache(): void
    {
        $zone = Zone::factory()->create(['is_active' => true]);
        $index = app(ZoneIndex::class);
        $index->forget();

        $this->assertCount(1, $index->entries());

        Livewire::test(ZoneBuilder::class)
            ->call('toggleActive', $zone->id)
            ->assertDispatched('toast');

        $this->assertFalse($zone->refresh()->is_active);
        $this->assertCount(0, $index->entries());
    }

    public function test_access_rules_are_persisted_with_precedence_data(): void
    {
        Person::factory()->create(['department' => 'HSE']);

        Livewire::test(ZoneBuilder::class)
            ->call('openCreate')
            ->set('form.name', '规则')
            ->set('form.code', 'RULES_01')
            ->set('form.default_access', 'deny')
            ->set('form.rules', [
                ['target_type' => 'department', 'target_id' => null, 'target_value' => 'HSE', 'access_type' => 'allow'],
            ])
            ->set('geometry', $this->polygon())
            ->set('geometryType', 'polygon')
            ->call('save');

        $zone = Zone::query()->where('code', 'RULES_01')->firstOrFail();

        $this->assertSame(2, $zone->accessRules()->count());
        $this->assertTrue(
            $zone->accessRules()->where('target_type', 'department')->where('access_type', 'allow')->exists()
        );
    }

    public function test_the_page_renders_for_an_authenticated_user(): void
    {
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)
            ->get(route('zones.index'))
            ->assertOk()
            ->assertSee('مناطق جغرافیایی');
    }
}
