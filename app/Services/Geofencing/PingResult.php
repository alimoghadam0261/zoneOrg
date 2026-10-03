<?php

namespace App\Services\Geofencing;

use App\Models\Zone;
use App\Models\ZoneEventLog;

/**
 * Value object returned by the ping pipeline.
 */
final class PingResult
{
    /**
     * @param  array<int, array{zone: Zone, access: string}>  $matches
     * @param  array<int, ZoneEventLog>  $events
     */
    public function __construct(
        public readonly int $pingId,
        public readonly array $matches,
        public readonly array $events,
        public readonly bool $insideAnyZone,
        public readonly int $consecutivePings,
    ) {}

    public function hasViolation(): bool
    {
        foreach ($this->events as $event) {
            if ($event->isViolation()) {
                return true;
            }
        }

        return false;
    }

    public function highestSeverity(): ?string
    {
        $order = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
        $best = null;
        $bestScore = 0;

        foreach ($this->matches as $match) {
            $level = $match['zone']->severity_level;
            $score = $order[$level] ?? 0;

            if ($score > $bestScore) {
                $best = $level;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ping_id' => $this->pingId,
            'zones' => array_map(fn (array $match) => [
                'id' => $match['zone']->id,
                'name' => $match['zone']->name,
                'code' => $match['zone']->code,
                'severity' => $match['zone']->severity_level,
                'color' => $match['zone']->color,
                'access' => $match['access'],
                'type' => $match['zone']->type,
            ], $this->matches),
            'inside_any_zone' => $this->insideAnyZone,
            'violation' => $this->hasViolation(),
            'severity' => $this->highestSeverity(),
            'events' => array_map(fn (ZoneEventLog $event) => [
                'id' => $event->id,
                'type' => $event->event_type,
                'severity' => $event->severity,
                'zone' => $event->zone?->name,
            ], $this->events),
        ];
    }
}
