<?php

namespace App\Livewire;

use App\Models\LocationPing;
use App\Models\Person;
use App\Models\Zone;
use App\Models\ZoneEventLog;
use App\Services\Geofencing\ZoneEvaluator;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app', ['title' => 'داشبورد زنده'])]
class LiveDashboard extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $positions = [];

    /** @var array<int, array<string, mixed>> */
    public array $alerts = [];

    /** @var array<int, array<string, mixed>> */
    public array $zones = [];

    /** @var array<string, int|bool> */
    public array $stats = [];

    /** @var array<int, int> ids of alerts already announced to the officer */
    public array $seenAlerts = [];

    public string $statusFilter = 'all';

    public string $selectedPerson = '';

    public function mount(ZoneEvaluator $evaluator): void
    {
        $this->zones = Zone::query()->active()->get()->map->toMapArray()->values()->all();
        $this->refresh($evaluator);
    }

    /**
     * Polled by `wire:poll.3s` — refreshes positions, alerts and stats.
     */
    public function refresh(ZoneEvaluator $evaluator): void
    {
        $offlineAfter = (int) config('zone.offline_after_minutes');
        $staleBefore = now()->subMinutes($offlineAfter);

        $people = Person::query()
            ->active()
            ->orderBy('full_name')
            ->get();

        $latestPings = $this->latestPings();
        $pingsByPerson = [];

        foreach ($latestPings as $ping) {
            $pingsByPerson[$ping->person_id] = $ping;
        }

        $positions = [];
        $online = 0;
        $offline = 0;
        $violations = 0;

        foreach ($people as $person) {
            $ping = $pingsByPerson[$person->id] ?? null;
            $isOffline = $ping === null || $ping->captured_at === null || $ping->captured_at->lt($staleBefore);

            $position = [
                'id' => $person->id,
                'name' => $person->full_name,
                'code' => $person->personnel_code,
                'department' => $person->department,
                'contract_label' => $person->contractTypeLabel(),
                'avatar' => $person->avatar_url,
                'lat' => null,
                'lng' => null,
                'accuracy' => null,
                'captured_at' => null,
                'battery' => null,
                'status' => 'offline',
                'status_label' => 'آفلاین',
                'zone' => null,
            ];

            if ($isOffline) {
                $offline++;
                $positions[] = $position;

                continue;
            }

            $online++;
            $position['lat'] = (float) $ping->lat;
            $position['lng'] = (float) $ping->lng;
            $position['accuracy'] = (float) $ping->accuracy;
            $position['captured_at'] = $ping->captured_at->format('Y-m-d H:i:s');
            $position['battery'] = $ping->device?->battery_level;
            $position['status'] = 'outside';
            $position['status_label'] = 'خارج از محدوده';

            // Two-phase evaluation for every live fix (bbox → exact).
            $matches = $evaluator->evaluate($position['lat'], $position['lng'], $person);

            if ($matches !== []) {
                $dominant = $this->dominantMatch($matches);
                $position['zone'] = [
                    'id' => $dominant['zone']->id,
                    'name' => $dominant['zone']->name,
                    'color' => $dominant['zone']->color,
                    'severity' => $dominant['zone']->severity_level,
                    'access' => $dominant['access'],
                ];

                if ($dominant['access'] === 'deny') {
                    $position['status'] = 'violation';
                    $position['status_label'] = 'نقض ممنوعیت';
                    $violations++;
                } else {
                    $position['status'] = 'allowed';
                    $position['status_label'] = 'در محدوده مجاز';
                }
            }

            $positions[] = $position;
        }

        $this->positions = $positions;

        $this->alerts = ZoneEventLog::query()
            ->with(['person:id,full_name,personnel_code', 'zone:id,name,color,severity_level'])
            ->latest('created_at')
            ->latest('id')
            ->limit(40)
            ->get()
            ->map(fn (ZoneEventLog $event) => $this->alertPayload($event))
            ->values()
            ->all();

        $this->announceNewAlerts();

        $openViolations = ZoneEventLog::query()
            ->where('is_resolved', false)
            ->where('event_type', 'like', 'violation%')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $this->stats = [
            'total' => $people->count(),
            'online' => $online,
            'offline' => $offline,
            'violations' => $violations,
            'open_violations' => $openViolations,
            'open_alerts' => count(array_filter($this->alerts, fn (array $a) => ! $a['is_resolved'])),
            'active_zones' => count($this->zones),
            'pings_today' => LocationPing::query()->where('created_at', '>=', now()->startOfDay())->count(),
        ];

        // Push to the browser map bridge.
        $this->dispatch('lm-positions', positions: $this->positions);
    }

    /**
     * @return array<int, LocationPing>
     */
    protected function latestPings()
    {
        $max = LocationPing::query()
            ->selectRaw('person_id, MAX(captured_at) as max_captured_at')
            ->groupBy('person_id');

        return LocationPing::query()
            ->joinSub($max, 'm', function ($join) {
                $join->on('location_pings.person_id', '=', 'm.person_id')
                    ->on('location_pings.captured_at', '=', 'm.max_captured_at');
            })
            ->select('location_pings.*')
            ->with('device:id,battery_level,person_id')
            ->get();
    }

    /**
     * The most dangerous zone containing the person (severity first, deny first).
     *
     * @param  array<int, array{zone: Zone, access: string}>  $matches
     * @return array{zone: Zone, access: string}
     */
    protected function dominantMatch(array $matches): array
    {
        $rank = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];

        usort($matches, function ($a, $b) use ($rank) {
            $aDeny = $a['access'] === 'deny' ? 1 : 0;
            $bDeny = $b['access'] === 'deny' ? 1 : 0;

            if ($aDeny !== $bDeny) {
                return $bDeny <=> $aDeny;
            }

            return ($rank[$b['zone']->severity_level] ?? 0) <=> ($rank[$a['zone']->severity_level] ?? 0);
        });

        return $matches[0];
    }

    /**
     * @return array<string, mixed>
     */
    protected function alertPayload(ZoneEventLog $event): array
    {
        $snapshot = $event->location_snapshot ?? [];

        return [
            'id' => $event->id,
            'type' => $event->event_type,
            'type_label' => $event->typeLabel(),
            'severity' => $event->severity,
            'is_violation' => $event->isViolation(),
            'is_resolved' => (bool) $event->is_resolved,
            'person' => $event->person?->full_name,
            'personnel_code' => $event->person?->personnel_code,
            'zone' => $event->zone?->name,
            'zone_color' => $event->zone?->color,
            'time' => $event->created_at?->diffForHumans(),
            'created_at' => $event->created_at?->toDateTimeString(),
            'lat' => $snapshot['lat'] ?? null,
            'lng' => $snapshot['lng'] ?? null,
            'accuracy' => $snapshot['accuracy'] ?? null,
        ];
    }

    protected function announceNewAlerts(): void
    {
        $ids = array_column($this->alerts, 'id');
        $fresh = array_slice(array_diff($ids, $this->seenAlerts), 0, 10);

        if ($fresh !== []) {
            $payload = array_values(array_filter(
                $this->alerts,
                fn (array $alert) => in_array($alert['id'], $fresh, true) && $alert['is_violation']
            ));

            if ($payload !== []) {
                $this->dispatch('zone-alert', alerts: $payload);
            }

            $this->seenAlerts = array_slice(array_values(array_unique(array_merge($this->seenAlerts, $ids))), -60);
        }
    }

    public function resolveAlert(int $id): void
    {
        $event = ZoneEventLog::query()->findOrFail($id);

        $event->forceFill([
            'is_resolved' => true,
            'resolved_by' => auth()->id(),
        ])->save();

        $this->dispatch('toast', type: 'success', title: 'رویداد بسته شد', body: $event->zone?->name ?? '');
        $this->refresh(app(ZoneEvaluator::class));
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = in_array($filter, ['all', 'violation', 'outside', 'allowed', 'offline'], true)
            ? $filter : 'all';
    }

    public function selectPerson(int $id): void
    {
        $this->selectedPerson = (string) $id;
        $this->dispatch('lm-focus', id: $id);
    }

    /** @return array<int, array<string, mixed>> */
    public function visiblePositions(): array
    {
        if ($this->statusFilter === 'all') {
            return $this->positions;
        }

        return array_values(array_filter($this->positions, fn (array $p) => $p['status'] === $this->statusFilter));
    }

    /** @return array<string, mixed> */
    public function mapOptions(): array
    {
        return [
            'layers' => config('zone.tile_layers'),
            'center' => config('zone.map.center'),
            'zoom' => config('zone.map.zoom'),
            'theme' => 'satellite',
            'offlineAfterMinutes' => config('zone.offline_after_minutes'),
            'zones' => $this->zones,
            'positions' => $this->positions,
        ];
    }

    public function render()
    {
        return view('livewire.live-dashboard');
    }
}
