<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZoneAccessRule extends Model
{
    public const TARGET_TYPES = ['person', 'department', 'contract_type'];

    public const ACCESS_TYPES = ['allow', 'deny'];

    /** target_value wildcard: applies to everybody not matched by a more specific rule. */
    public const WILDCARD = '*';

    protected $fillable = [
        'zone_id',
        'target_type',
        'target_id',
        'target_value',
        'access_type',
        'valid_from',
        'valid_to',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function isValidAt($moment): bool
    {
        if ($this->valid_from === null && $this->valid_to === null) {
            return true;
        }

        if ($this->valid_from && $moment->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_to && $moment->gt($this->valid_to)) {
            return false;
        }

        return true;
    }

    public function matchesPerson(Person $person): bool
    {
        return match ($this->target_type) {
            'person' => $this->target_id !== null
                ? $this->target_id === $person->id
                : $this->target_value === self::WILDCARD,
            'department' => $this->target_value === self::WILDCARD
                || ($person->department !== null && strcasecmp((string) $this->target_value, $person->department) === 0),
            'contract_type' => $this->target_value === self::WILDCARD
                || $this->target_value === $person->contract_type,
            default => false,
        };
    }

    /** Specificity: wildcard default (0) < contract_type (10) < department (20) < person (30). */
    public function specificity(): int
    {
        if ($this->target_value === self::WILDCARD) {
            return 0;
        }

        return match ($this->target_type) {
            'person' => 30,
            'department' => 20,
            'contract_type' => 10,
            default => 5,
        };
    }
}
