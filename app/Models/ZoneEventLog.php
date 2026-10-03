<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZoneEventLog extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = ['entered', 'exited', 'violation_entered', 'violation_lingering'];

    protected $fillable = [
        'person_id',
        'zone_id',
        'device_id',
        'event_type',
        'severity',
        'location_snapshot',
        'is_resolved',
        'resolved_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'location_snapshot' => 'array',
            'is_resolved' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isViolation(): bool
    {
        return str_starts_with($this->event_type, 'violation');
    }

    public function typeLabel(): string
    {
        return match ($this->event_type) {
            'entered' => 'ورود به منطقه',
            'exited' => 'خروج از منطقه',
            'violation_entered' => 'نقض قانون — ورود غیرمجاز',
            'violation_lingering' => 'نقض قانون — تداوم حضور',
            default => $this->event_type,
        };
    }
}
