<?php

namespace App\Livewire;

use App\Models\Device;
use App\Models\Person;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app', ['title' => 'دستگاه‌ها و توکن‌ها'])]
class DeviceManager extends Component
{
    /** @var array<string, mixed> */
    public array $form = [];

    public bool $showCreate = false;

    public ?string $plainTextToken = null;

    public ?int $tokenDeviceId = null;

    public function mount(): void
    {
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->form = [
            'device_uid' => '',
            'type' => 'gps_tag',
            'person_id' => '',
            'battery_level' => 100,
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showCreate = true;
    }

    public function closeCreate(): void
    {
        $this->showCreate = false;
    }

    public function create(): void
    {
        $this->validate([
            'form.device_uid' => ['required', 'string', 'max:64', 'unique:devices,device_uid'],
            'form.type' => ['required', 'in:mobile_app,gps_tag'],
            'form.person_id' => ['nullable', 'integer', 'exists:people,id'],
            'form.battery_level' => ['required', 'integer', 'between:0,100'],
        ], [
            'form.device_uid.required' => 'شناسه دستگاه الزامی است.',
            'form.device_uid.unique' => 'این شناسه قبلاً ثبت شده است.',
        ]);

        $device = Device::create([
            'device_uid' => trim($this->form['device_uid']),
            'type' => $this->form['type'],
            'person_id' => $this->form['person_id'] ?: null,
            'battery_level' => (int) $this->form['battery_level'],
            'last_seen_at' => null,
        ]);

        $this->showCreate = false;
        $this->resetForm();

        $this->dispatch('toast', type: 'success', title: 'دستگاه ثبت شد', body: $device->device_uid);
    }

    public function issueToken(int $id): void
    {
        $device = Device::query()->findOrFail($id);

        $this->plainTextToken = $device->issueToken();
        $this->tokenDeviceId = $device->id;

        $this->dispatch('toast', type: 'warning', title: 'توکن جدید صادر شد',
            body: 'این توکن فقط یک بار نمایش داده می‌شود.');
    }

    public function revokeToken(int $id): void
    {
        Device::query()->findOrFail($id)->revokeToken();

        if ($this->tokenDeviceId === $id) {
            $this->plainTextToken = null;
            $this->tokenDeviceId = null;
        }

        $this->dispatch('toast', type: 'info', title: 'توکن باطل شد');
    }

    public function assignPerson(int $deviceId, $personId): void
    {
        $device = Device::query()->findOrFail($deviceId);
        $device->person_id = $personId ?: null;
        $device->save();

        $this->dispatch('toast', type: 'success', title: 'دستگاه بروزرسانی شد');
    }

    public function destroy(int $id): void
    {
        $device = Device::query()->findOrFail($id);
        $device->delete();

        $this->dispatch('toast', type: 'success', title: 'دستگاه حذف شد', body: $device->device_uid);
    }

    public function render()
    {
        return view('livewire.device-manager', [
            'devices' => Device::query()
                ->with(['person:id,full_name,personnel_code,phone', 'person.zones:id,name,code,color'])
                ->latest('id')
                ->paginate(15),
            'people' => Person::query()->with('zones:id,name,code,color')->orderBy('full_name')->get(['id', 'full_name', 'personnel_code']),
        ]);
    }
}
