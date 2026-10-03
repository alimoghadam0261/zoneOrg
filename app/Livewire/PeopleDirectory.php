<?php

namespace App\Livewire;

use App\Models\Person;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app', ['title' => 'پرسنل'])]
class PeopleDirectory extends Component
{
    use WithPagination;

    public string $search = '';

    public string $contract = 'all';

    protected $queryString = [
        'search' => ['except' => ''],
        'contract' => ['except' => 'all'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setContract(string $contract): void
    {
        $this->contract = $contract;
        $this->resetPage();
    }

    public function render()
    {
        $people = Person::query()
            ->search($this->search)
            ->when($this->contract !== 'all', fn ($q) => $q->where('contract_type', $this->contract))
            ->with('device:id,person_id,type,battery_level,last_seen_at')
            ->orderBy('full_name')
            ->paginate(20);

        return view('livewire.people-directory', [
            'people' => $people,
        ]);
    }
}
