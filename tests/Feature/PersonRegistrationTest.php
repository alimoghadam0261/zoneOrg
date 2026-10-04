<?php

namespace Tests\Feature;

use App\Livewire\PeopleDirectory;
use App\Models\Device;
use App\Models\Person;
use App\Models\Zone;
use App\Models\ZoneAccessRule;
use App\Services\Geofencing\AccessRuleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PersonRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function zone(string $code = 'REG_ZONE'): Zone
    {
        return Zone::factory()->create([
            'code' => $code,
            'type' => 'polygon',
            'severity_level' => 'critical',
            'is_active' => true,
        ]);
    }

    public function test_a_new_person_can_be_registered_with_phone_device_and_zone_link(): void
    {
        $zone = $this->zone();

        $component = Livewire::test(PeopleDirectory::class)
            ->call('openCreate')
            ->set('form.personnel_code', 'P-9001')
            ->set('form.full_name', 'رضا کریمی')
            ->set('form.phone', '09121234567')
            ->set('form.national_id', '1234567890')
            ->set('form.department', 'تعمیرات برق')
            ->set('form.contract_type', 'employee')
            ->set('form.with_device', true)
            ->set('form.device_uid', 'IMEI-9001')
            ->set('form.device_type', 'mobile_app')
            ->set('form.issue_token', true)
            ->set('form.zone_id', (string) $zone->id)
            ->set('form.access_type', 'deny')
            ->call('create')
            ->assertDispatched('toast')
            ->assertSet('showCreate', false);

        $person = Person::query()->where('personnel_code', 'P-9001')->firstOrFail();

        $this->assertSame('09121234567', $person->phone);
        $this->assertSame('تعمیرات برق', $person->department);

        // device is linked to the person
        $device = Device::query()->where('device_uid', 'IMEI-9001')->firstOrFail();
        $this->assertSame($person->id, $device->person_id);
        $this->assertSame('mobile_app', $device->type);
        $this->assertNotNull($device->api_token, 'token should be issued on registration');

        // zone link exists as a person-specific rule
        $rule = ZoneAccessRule::query()
            ->where('zone_id', $zone->id)
            ->where('target_type', 'person')
            ->where('target_id', $person->id)
            ->firstOrFail();

        $this->assertSame('deny', $rule->access_type);
        $this->assertSame('P-9001', $rule->target_value);

        // and the resolver honours it
        $this->assertTrue(app(AccessRuleResolver::class)->isDenied($zone, $person));

        // plaintext token is revealed on the same component after create
        $this->assertNotNull($component->get('plainTextToken'));
    }

    public function test_registration_without_zone_or_device_still_works(): void
    {
        Livewire::test(PeopleDirectory::class)
            ->call('openCreate')
            ->set('form.personnel_code', 'P-9002')
            ->set('form.full_name', 'مریم احمدی')
            ->set('form.phone', '09351112233')
            ->set('form.with_device', false)
            ->set('form.zone_id', '')
            ->call('create')
            ->assertDispatched('toast');

        $person = Person::query()->where('personnel_code', 'P-9002')->firstOrFail();

        $this->assertSame('09351112233', $person->phone);
        $this->assertNull($person->device);
        $this->assertSame(0, ZoneAccessRule::query()->where('target_id', $person->id)->count());
    }

    public function test_registration_validates_required_fields_and_phone_format(): void
    {
        Livewire::test(PeopleDirectory::class)
            ->call('openCreate')
            ->set('form.personnel_code', '')
            ->set('form.full_name', '')
            ->set('form.phone', '12345')
            ->set('form.with_device', true)
            ->set('form.device_uid', '')
            ->call('create')
            ->assertHasErrors([
                'form.personnel_code',
                'form.full_name',
                'form.phone',
                'form.device_uid',
            ]);

        $this->assertSame(0, Person::query()->count());
    }

    public function test_duplicate_personnel_code_is_rejected(): void
    {
        Person::factory()->create(['personnel_code' => 'P-1111']);

        Livewire::test(PeopleDirectory::class)
            ->call('openCreate')
            ->set('form.personnel_code', 'P-1111')
            ->set('form.full_name', 'تکراری')
            ->set('form.with_device', false)
            ->call('create')
            ->assertHasErrors(['form.personnel_code']);

        $this->assertSame(1, Person::query()->count());
    }

    public function test_an_existing_person_can_be_linked_and_unlinked_from_a_zone(): void
    {
        $person = Person::factory()->create();
        $zone = $this->zone('LINK_ZONE');

        $component = Livewire::test(PeopleDirectory::class)
            ->call('openZoneLink', $person->id)
            ->assertSet('showZoneLink', true)
            ->set('linkForm.zone_id', (string) $zone->id)
            ->set('linkForm.access_type', 'deny')
            ->call('linkZone')
            ->assertDispatched('toast')
            ->assertSet('showZoneLink', false);

        $rule = ZoneAccessRule::query()
            ->where('zone_id', $zone->id)
            ->where('target_id', $person->id)
            ->firstOrFail();

        $this->assertSame('deny', $rule->access_type);
        $this->assertTrue($person->zones()->count() > 0);

        // re-linking flips access instead of duplicating the rule
        $component
            ->call('openZoneLink', $person->id)
            ->set('linkForm.zone_id', (string) $zone->id)
            ->set('linkForm.access_type', 'allow')
            ->call('linkZone');

        $this->assertSame(1, ZoneAccessRule::query()->where('zone_id', $zone->id)->where('target_id', $person->id)->count());
        $this->assertSame('allow', $rule->refresh()->access_type);

        // unlink removes it
        $component->call('unlinkZone', $person->id, $zone->id)->assertDispatched('toast');

        $this->assertSame(0, ZoneAccessRule::query()->where('zone_id', $zone->id)->where('target_id', $person->id)->count());
        $this->assertSame(0, $person->zones()->count());
    }

    public function test_zone_builder_person_rule_by_personnel_code_resolves_to_the_person(): void
    {
        $person = Person::factory()->create(['personnel_code' => 'P-7777']);

        $component = \Livewire\Livewire::test(\App\Livewire\ZoneBuilder::class)
            ->call('openCreate')
            ->set('form.rule_type', 'person')
            ->set('form.rule_value', 'P-7777')
            ->set('form.rule_access', 'allow')
            ->call('addRule');

        $rules = $component->get('form')['rules'];

        $this->assertCount(1, $rules);
        $this->assertSame($person->id, $rules[0]['target_id']);
        $this->assertSame('P-7777', $rules[0]['target_value']);
    }

    public function test_person_rule_keyed_by_code_matches_in_the_resolver(): void
    {
        $person = Person::factory()->create(['personnel_code' => 'P-5555']);

        $zone = Zone::factory()->create(['type' => 'polygon', 'is_active' => true]);
        $zone->accessRules()->create([
            'target_type' => 'person',
            'target_id' => null,
            'target_value' => 'P-5555',
            'access_type' => 'deny',
        ]);
        $zone->accessRules()->create([
            'target_type' => 'person',
            'target_id' => null,
            'target_value' => '*',
            'access_type' => 'allow',
        ]);

        $resolver = app(AccessRuleResolver::class);

        $this->assertSame('deny', $resolver->resolve($zone, $person));

        $stranger = Person::factory()->create(['personnel_code' => 'P-9999']);
        $this->assertSame('allow', $resolver->resolve($zone, $stranger));
    }

    public function test_people_page_renders_the_registration_entry_point(): void
    {
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)
            ->get(route('people.index'))
            ->assertOk()
            ->assertSee('پرسنل جدید')
            ->assertSee('موبایل');
    }
}
