<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\Zone;
use App\Models\ZoneAccessRule;
use App\Services\Geofencing\AccessRuleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessRulesTest extends TestCase
{
    use RefreshDatabase;

    private function zoneWithRules(array $rules): Zone
    {
        $zone = new Zone([
            'name' => 'منطقه',
            'code' => 'RULE_TEST',
            'type' => 'polygon',
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 0], [1, 1], [0, 0]]]],
            'severity_level' => 'high',
            'color' => '#ef4444',
            'is_active' => true,
        ]);

        $zone->syncBoundingBox();

        $models = collect($rules)->map(fn (array $rule) => new ZoneAccessRule($rule));

        $zone->setRelation('accessRules', $models);

        return $zone;
    }

    public function test_wildcard_deny_blocks_everyone_by_default(): void
    {
        $zone = $this->zoneWithRules([
            ['target_type' => 'person', 'target_value' => '*', 'access_type' => 'deny'],
        ]);

        $person = new Person(['department' => 'اپراتوری', 'contract_type' => 'employee']);

        $this->assertSame('deny', app(AccessRuleResolver::class)->resolve($zone, $person));
    }

    public function test_department_rule_beats_the_wildcard_default(): void
    {
        $zone = $this->zoneWithRules([
            ['target_type' => 'person', 'target_value' => '*', 'access_type' => 'deny'],
            ['target_type' => 'department', 'target_value' => 'تعمیرات برق', 'access_type' => 'allow'],
        ]);

        $electrician = new Person(['department' => 'تعمیرات برق', 'contract_type' => 'employee']);
        $operator = new Person(['department' => 'اپراتوری', 'contract_type' => 'employee']);

        $resolver = app(AccessRuleResolver::class);

        $this->assertSame('allow', $resolver->resolve($zone, $electrician));
        $this->assertSame('deny', $resolver->resolve($zone, $operator));
    }

    public function test_person_rule_is_more_specific_than_a_department_rule(): void
    {
        $zone = $this->zoneWithRules([
            ['target_type' => 'department', 'target_value' => 'HSE', 'access_type' => 'deny'],
            ['target_type' => 'person', 'target_id' => 7, 'target_value' => null, 'access_type' => 'allow'],
        ]);

        $resolver = app(AccessRuleResolver::class);

        $favoured = new Person(['department' => 'HSE', 'contract_type' => 'employee']);
        $favoured->id = 7;

        $other = new Person(['department' => 'HSE', 'contract_type' => 'employee']);
        $other->id = 8;

        $this->assertSame('allow', $resolver->resolve($zone, $favoured));
        $this->assertSame('deny', $resolver->resolve($zone, $other));
    }

    public function test_contract_type_rule_applies_to_contractors_only(): void
    {
        $zone = $this->zoneWithRules([
            ['target_type' => 'person', 'target_value' => '*', 'access_type' => 'allow'],
            ['target_type' => 'contract_type', 'target_value' => 'visitor', 'access_type' => 'deny'],
        ]);

        $resolver = app(AccessRuleResolver::class);

        $visitor = new Person(['contract_type' => 'visitor', 'department' => 'مدیریت']);
        $employee = new Person(['contract_type' => 'employee', 'department' => 'مدیریت']);

        $this->assertSame('deny', $resolver->resolve($zone, $visitor));
        $this->assertSame('allow', $resolver->resolve($zone, $employee));
    }

    public function test_rules_outside_their_validity_window_are_ignored(): void
    {
        $zone = $this->zoneWithRules([
            ['target_type' => 'person', 'target_value' => '*', 'access_type' => 'allow'],
            [
                'target_type' => 'department',
                'target_value' => 'HSE',
                'access_type' => 'deny',
                'valid_from' => now()->addDay(),
                'valid_to' => now()->addDays(2),
            ],
        ]);

        $person = new Person(['department' => 'HSE', 'contract_type' => 'employee']);

        $this->assertSame('allow', app(AccessRuleResolver::class)->resolve($zone, $person));

        $zoneDuringWindow = $this->zoneWithRules([
            ['target_type' => 'person', 'target_value' => '*', 'access_type' => 'allow'],
            [
                'target_type' => 'department',
                'target_value' => 'HSE',
                'access_type' => 'deny',
                'valid_from' => now()->subHour(),
                'valid_to' => now()->addHour(),
            ],
        ]);

        $this->assertSame('deny', app(AccessRuleResolver::class)->resolve($zoneDuringWindow, $person));
    }

    public function test_zone_without_rules_is_accessible_by_default(): void
    {
        $zone = $this->zoneWithRules([]);
        $person = new Person(['department' => 'اپراتوری', 'contract_type' => 'employee']);

        $this->assertSame('allow', app(AccessRuleResolver::class)->resolve($zone, $person));
    }
}
