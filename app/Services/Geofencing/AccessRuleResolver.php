<?php

namespace App\Services\Geofencing;

use App\Models\Person;
use App\Models\Zone;
use Illuminate\Support\Carbon;

/**
 * Resolves whether a person is allowed inside a zone.
 *
 * Precedence (most specific wins):
 *   person > department > contract_type > wildcard("*")
 * Rules outside their valid_from/valid_to window are ignored.
 */
class AccessRuleResolver
{
    public function resolve(Zone $zone, Person $person, ?Carbon $moment = null): string
    {
        $moment = $moment ?? now();

        $best = null;
        $bestScore = -1;

        foreach ($zone->accessRules as $rule) {
            if (! $rule->isValidAt($moment)) {
                continue;
            }

            if (! $rule->matchesPerson($person)) {
                continue;
            }

            $score = $rule->specificity();

            if ($score > $bestScore) {
                $best = $rule;
                $bestScore = $score;
            }
        }

        if ($best === null) {
            return 'allow';
        }

        return $best->access_type === 'deny' ? 'deny' : 'allow';
    }

    public function isDenied(Zone $zone, Person $person, ?Carbon $moment = null): bool
    {
        return $this->resolve($zone, $person, $moment) === 'deny';
    }
}
