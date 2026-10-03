<?php

namespace App\Livewire;

use App\Models\ZoneEventLog;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app', ['title' => 'رویدادها و هشدارها'])]
class EventLogTable extends Component
{
    use WithPagination;

    public string $search = '';

    public string $type = 'all';

    public string $status = 'open';

    protected $queryString = [
        'search' => ['except' => ''],
        'type' => ['except' => 'all'],
        'status' => ['except' => 'open'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setType(string $type): void
    {
        $this->type = $type;
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function resolve(int $id): void
    {
        $event = ZoneEventLog::query()->findOrFail($id);

        $event->forceFill([
            'is_resolved' => true,
            'resolved_by' => auth()->id(),
        ])->save();

        $this->dispatch('toast', type: 'success', title: 'رویداد بسته شد', body: $event->typeLabel());
    }

    public function render()
    {
        $query = ZoneEventLog::query()
            ->with(['person:id,full_name,personnel_code,department', 'zone:id,name,color,severity_level,code']);

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term) {
                $q->whereHas('person', fn ($p) => $p->where('full_name', 'like', $term)
                    ->orWhere('personnel_code', 'like', $term))
                    ->orWhereHas('zone', fn ($z) => $z->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term));
            });
        }

        if ($this->type !== 'all') {
            $query->where('event_type', $this->type);
        }

        if ($this->status === 'open') {
            $query->where('is_resolved', false);
        } elseif ($this->status === 'resolved') {
            $query->where('is_resolved', true);
        }

        $events = $query->latest('created_at')->latest('id')->paginate(20);

        $counts = [
            'open' => ZoneEventLog::query()->where('is_resolved', false)->count(),
            'violations' => ZoneEventLog::query()->where('event_type', 'like', 'violation%')->count(),
            'today' => ZoneEventLog::query()->where('created_at', '>=', now()->startOfDay())->count(),
        ];

        return view('livewire.event-log-table', [
            'events' => $events,
            'counts' => $counts,
        ]);
    }
}
