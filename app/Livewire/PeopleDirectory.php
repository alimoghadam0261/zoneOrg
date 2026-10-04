<?php

namespace App\Livewire;

use App\Models\Device;
use App\Models\Person;
use App\Models\Zone;
use App\Models\ZoneAccessRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app', ['title' => 'پرسنل'])]
class PeopleDirectory extends Component
{
    use WithPagination;

    public string $search = '';

    public string $contract = 'all';

    /* ---------------------------------------------------------------- */
    /* Register new personnel                                           */
    /* ---------------------------------------------------------------- */

    public bool $showCreate = false;

    /** @var array<string, mixed> */
    public array $form = [];

    /** One-time plaintext token shown after registration (issue token ON). */
    public ?string $plainTextToken = null;

    /* Zone-link modal for an already-registered person */
    public bool $showZoneLink = false;

    public ?int $linkPersonId = null;

    /** @var array<string, mixed> */
    public array $linkForm = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'contract' => ['except' => 'all'],
    ];

    public function mount(): void
    {
        $this->resetForm();
        $this->resetLinkForm();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setContract(string $contract): void
    {
        $this->contract = $contract;
        $this->resetPage();
    }

    /* ---------------------------------------------------------------- */
    /* Registration form                                                 */
    /* ---------------------------------------------------------------- */

    public function resetForm(): void
    {
        $this->form = [
            'personnel_code' => '',
            'full_name' => '',
            'phone' => '',
            'national_id' => '',
            'department' => '',
            'contract_type' => 'employee',
            'status' => 'active',
            // device (mobile phone of the person)
            'with_device' => true,
            'device_uid' => '',
            'device_type' => 'mobile_app',
            'issue_token' => true,
            // zone link
            'zone_id' => '',
            'access_type' => 'allow',
        ];
        $this->plainTextToken = null;
    }

    public function resetLinkForm(): void
    {
        $this->linkForm = [
            'zone_id' => '',
            'access_type' => 'allow',
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
        $this->resetForm();
    }

    /**
     * Register a new person, optionally create+token their mobile device and
     * link them to a zone — all in one transaction so the trio never drifts.
     */
    public function create(): void
    {
        $this->validate([
            'form.personnel_code' => ['required', 'string', 'max:40', 'unique:people,personnel_code'],
            'form.full_name' => ['required', 'string', 'max:120'],
            'form.phone' => ['nullable', 'string', 'max:20', 'regex:/^(?:\+98|0098|0)?9[0-9]{9}$/'],
            'form.national_id' => ['nullable', 'string', 'max:10', 'digits:10', 'unique:people,national_id'],
            'form.department' => ['nullable', 'string', 'max:80'],
            'form.contract_type' => ['required', Rule::in(Person::CONTRACT_TYPES)],
            'form.status' => ['required', Rule::in(Person::STATUSES)],
            'form.device_uid' => [$this->form['with_device'] ? 'required' : 'nullable', 'string', 'max:64', 'unique:devices,device_uid'],
            'form.device_type' => ['required', Rule::in(Device::TYPES)],
            'form.zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'form.access_type' => ['required', Rule::in(ZoneAccessRule::ACCESS_TYPES)],
        ], [
            'form.personnel_code.required' => 'کد پرسنلی الزامی است.',
            'form.personnel_code.unique' => 'این کد پرسنلی قبلاً ثبت شده است.',
            'form.full_name.required' => 'نام و نام خانوادگی الزامی است.',
            'form.phone.regex' => 'شماره موبایل معتبر نیست (مثال: 09123456789).',
            'form.national_id.digits' => 'کد ملی باید ۱۰ رقم باشد.',
            'form.national_id.unique' => 'این کد ملی قبلاً ثبت شده است.',
            'form.device_uid.required' => 'برای لینک گوشی، شناسه دستگاه (IMEI/UUID) الزامی است.',
            'form.device_uid.unique' => 'این شناسه دستگاه قبلاً ثبت شده است.',
            'form.zone_id.exists' => 'منطقه انتخاب‌شده معتبر نیست.',
        ]);

        $token = null;

        DB::transaction(function () use (&$token) {
            $person = Person::create([
                'personnel_code' => trim((string) $this->form['personnel_code']),
                'full_name' => trim((string) $this->form['full_name']),
                'phone' => $this->form['phone'] ? trim((string) $this->form['phone']) : null,
                'national_id' => $this->form['national_id'] ?: null,
                'department' => $this->form['department'] ?: null,
                'contract_type' => $this->form['contract_type'],
                'status' => $this->form['status'],
            ]);

            if ($this->form['with_device'] && ($this->form['device_uid'] ?? '') !== '') {
                $device = Device::create([
                    'device_uid' => trim((string) $this->form['device_uid']),
                    'type' => $this->form['device_type'],
                    'person_id' => $person->id,
                    'battery_level' => 100,
                    'last_seen_at' => null,
                ]);

                if ($this->form['issue_token']) {
                    $token = $device->issueToken();
                }
            }

            if ($this->form['zone_id']) {
                ZoneAccessRule::create([
                    'zone_id' => (int) $this->form['zone_id'],
                    'target_type' => 'person',
                    'target_id' => $person->id,
                    'target_value' => $person->personnel_code,
                    'access_type' => $this->form['access_type'] === 'deny' ? 'deny' : 'allow',
                ]);
            }
        });

        $createdCode = (string) $this->form['personnel_code'];
        $linkedZone = $this->form['zone_id']
            ? Zone::query()->find((int) $this->form['zone_id'])?->name
            : null;

        $this->showCreate = false;
        $this->resetForm();
        $this->plainTextToken = $token;
        $this->resetPage();

        $this->dispatch('toast', type: 'success', title: 'پرسنل ثبت شد',
            body: $linkedZone ? "«{$createdCode}» به محدوده «{$linkedZone}» لینک شد." : $createdCode);
    }

    /* ---------------------------------------------------------------- */
    /* Zone link for an existing person                                  */
    /* ---------------------------------------------------------------- */

    public function openZoneLink(int $personId): void
    {
        $this->linkPersonId = $personId;
        $this->resetLinkForm();
        $this->showZoneLink = true;
    }

    public function closeZoneLink(): void
    {
        $this->showZoneLink = false;
        $this->linkPersonId = null;
        $this->resetLinkForm();
    }

    public function linkZone(): void
    {
        if (! $this->linkPersonId) {
            return;
        }

        $this->validate([
            'linkForm.zone_id' => ['required', 'integer', 'exists:zones,id'],
            'linkForm.access_type' => ['required', Rule::in(ZoneAccessRule::ACCESS_TYPES)],
        ], [
            'linkForm.zone_id.required' => 'یک محدوده انتخاب کنید.',
            'linkForm.zone_id.exists' => 'منطقه انتخاب‌شده معتبر نیست.',
        ]);

        $person = Person::query()->findOrFail($this->linkPersonId);
        $zoneId = (int) $this->linkForm['zone_id'];

        // Re-linking updates the existing rule instead of duplicating it.
        ZoneAccessRule::query()->updateOrCreate(
            [
                'zone_id' => $zoneId,
                'target_type' => 'person',
                'target_id' => $person->id,
            ],
            [
                'target_value' => $person->personnel_code,
                'access_type' => $this->linkForm['access_type'] === 'deny' ? 'deny' : 'allow',
            ]
        );

        $zoneName = Zone::query()->find($zoneId)?->name;

        $this->closeZoneLink();

        $this->dispatch('toast', type: 'success', title: 'لینک محدوده ثبت شد',
            body: "{$person->full_name} ← {$zoneName}");
    }

    public function unlinkZone(int $personId, int $zoneId): void
    {
        $person = Person::query()->findOrFail($personId);

        ZoneAccessRule::query()
            ->where('zone_id', $zoneId)
            ->where('target_type', 'person')
            ->where('target_id', $person->id)
            ->delete();

        $zoneName = Zone::query()->find($zoneId)?->name;

        $this->dispatch('toast', type: 'info', title: 'لینک محدوده حذف شد',
            body: "{$person->full_name} ← {$zoneName}");
    }

    /* ---------------------------------------------------------------- */

    public function render()
    {
        $people = Person::query()
            ->search($this->search)
            ->when($this->contract !== 'all', fn ($q) => $q->where('contract_type', $this->contract))
            ->with([
                'device:id,device_uid,person_id,type,battery_level,last_seen_at',
                'zones:id,name,code,color',
            ])
            ->orderBy('full_name')
            ->paginate(20);

        return view('livewire.people-directory', [
            'people' => $people,
            'zones' => Zone::query()->orderBy('name')->get(['id', 'name', 'code', 'color', 'is_active']),
            'linkPerson' => $this->linkPersonId
                ? Person::query()->find($this->linkPersonId)
                : null,
            'linkPersonZones' => $this->linkPersonId
                ? ZoneAccessRule::query()
                    ->where('target_type', 'person')
                    ->where('target_id', $this->linkPersonId)
                    ->pluck('zone_id')
                    ->all()
                : [],
        ]);
    }
}
