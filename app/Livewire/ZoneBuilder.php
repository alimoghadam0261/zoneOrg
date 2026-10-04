<?php

namespace App\Livewire;

use App\Models\Person;
use App\Models\Zone;
use App\Models\ZoneAccessRule;
use App\Services\Geofencing\GeoJson;
use App\Services\Geofencing\ZoneIndex;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('components.layouts.app', ['title' => 'مناطق جغرافیایی'])]
class ZoneBuilder extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $zones = [];

    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var array<string, mixed>|null */
    public ?array $geometry = null;

    public ?string $geometryType = null;

    public ?string $geometryError = null;

    /** @var array{0: float, 1: float, 2: float, 3: float}|null */
    public ?array $bbox = null;

    public string $mapTheme = 'satellite';

    public string $drawMode = 'polygon';

    public string $search = '';

    public function mount(): void
    {
        $this->resetForm();
        $this->loadZones();
    }

    public function loadZones(): void
    {
        $this->zones = Zone::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (Zone $zone) => $zone->toMapArray())
            ->values()
            ->all();

        $this->dispatch('zb-zones', zones: $this->zones);
    }

    /* ---------------------------------------------------------------- */
    /* Map bridge                                                       */
    /* ---------------------------------------------------------------- */

    /** Fired by the Leaflet bridge when the operator finishes a shape. */
    #[On('zone-geometry')]
    public function onGeometry($geometry, $type, $bbox = null): void
    {
        $this->geometry = is_array($geometry) ? $geometry : null;
        $this->geometryType = is_string($type) && $type !== '' ? $type : 'polygon';

        if ($this->geometry === null) {
            $this->geometryError = 'هندسی ثبت نشد.';
            $this->bbox = null;

            return;
        }

        // Automatic bbox — recomputed server-side, the client value is advisory.
        $this->bbox = GeoJson::bbox($this->geometry);

        $validation = GeoJson::validate($this->geometry, $this->geometryType);
        $this->geometryError = $validation['valid'] ? null : $validation['error'];

        // Adopt the drawn type so the form matches what is on the map — the
        // operator can still override the select before saving.
        $this->form['type'] = $this->geometryType;

        // A shape drawn from the toolbar while the form is closed would
        // otherwise have no save path — open the form to finish the job.
        if (! $this->showForm) {
            $this->showForm = true;
        }
    }

    #[On('zone-geometry-cleared')]
    public function onGeometryCleared(): void
    {
        $this->geometry = null;
        $this->geometryType = null;
        $this->bbox = null;
        $this->geometryError = null;
    }

    #[On('zone-select')]
    public function onSelectZone($id): void
    {
        $this->openEdit((int) $id);
    }

    public function setDrawMode(string $mode): void
    {
        $this->drawMode = $mode;
        $this->dispatch('zb-draw', mode: $mode);
    }

    public function setMapTheme(string $theme): void
    {
        if (! isset(config('zone.tile_layers')[$theme])) {
            return;
        }

        $this->mapTheme = $theme;
        $this->dispatch('zb-theme', theme: $theme);
    }

    public function fitZones(): void
    {
        $this->dispatch('zb-fit');
    }

    /* ---------------------------------------------------------------- */
    /* Form                                                             */
    /* ---------------------------------------------------------------- */

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'name' => '',
            'code' => '',
            'type' => 'polygon',
            'severity_level' => 'medium',
            'color' => '#6366f1',
            'description' => '',
            'is_active' => true,
            'default_access' => 'allow',
            'rules' => [],
        ];
        $this->geometry = null;
        $this->geometryType = null;
        $this->geometryError = null;
        $this->bbox = null;
        $this->drawMode = 'polygon';
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
        $this->dispatch('zb-draft', geometry: null, type: null);
        $this->dispatch('zb-draw', mode: 'polygon');
        $this->drawMode = 'polygon';
    }

    public function openEdit(int $id): void
    {
        $zone = Zone::query()->with('accessRules')->findOrFail($id);

        $this->resetForm();
        $this->editingId = $zone->id;
        $this->form = [
            'name' => $zone->name,
            'code' => $zone->code,
            'type' => $zone->type,
            'severity_level' => $zone->severity_level,
            'color' => $zone->color,
            'description' => (string) $zone->description,
            'is_active' => (bool) $zone->is_active,
            'default_access' => 'allow',
            'rules' => [],
        ];

        $rules = [];

        foreach ($zone->accessRules as $rule) {
            if ($rule->target_value === ZoneAccessRule::WILDCARD) {
                $this->form['default_access'] = $rule->access_type;

                continue;
            }

            $rules[] = [
                'target_type' => $rule->target_type,
                'target_id' => $rule->target_id,
                'target_value' => $rule->target_value,
                'access_type' => $rule->access_type,
            ];
        }

        $this->form['rules'] = $rules;
        $this->geometry = $zone->geometry;
        $this->geometryType = $zone->type;
        $this->bbox = [$zone->min_lat, $zone->min_lng, $zone->max_lat, $zone->max_lng];
        $this->geometryError = null;
        $this->showForm = true;

        $this->dispatch('zb-draft', geometry: $zone->geometry, type: $zone->type);
        $this->dispatch('zb-focus', geometry: $zone->geometry);
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('zb-draft', geometry: null, type: null);
        $this->dispatch('zb-draw', mode: null);
    }

    public function addRule(): void
    {
        $type = (string) ($this->form['rule_type'] ?? 'department');
        $value = trim((string) ($this->form['rule_value'] ?? ''));

        if ($value === '') {
            $this->dispatch('toast', type: 'error', title: 'قانون ناقص', body: 'مقدار قانون را وارد کنید.');

            return;
        }

        $targetId = null;

        // Person rules typed by personnel code are resolved to the person id
        // so the link is explicit (the code stays in target_value for display).
        if ($type === 'person') {
            $targetId = Person::query()->where('personnel_code', $value)->value('id');
        }

        $rules = $this->form['rules'] ?? [];
        $rules[] = [
            'target_type' => $type,
            'target_id' => $targetId,
            'target_value' => $value,
            'access_type' => (string) ($this->form['rule_access'] ?? 'deny'),
        ];

        $this->form['rules'] = $rules;
        $this->form['rule_value'] = '';
    }

    public function removeRule(int $index): void
    {
        $rules = $this->form['rules'] ?? [];
        unset($rules[$index]);
        $this->form['rules'] = array_values($rules);
    }

    /* ---------------------------------------------------------------- */
    /* Persistence                                                      */
    /* ---------------------------------------------------------------- */

    public function save(ZoneIndex $index): void
    {
        $this->reconcileGeometry();

        $validator = Validator::make(
            [
                'name' => $this->form['name'] ?? null,
                'code' => $this->form['code'] ?? null,
                'type' => $this->form['type'] ?? null,
                'severity_level' => $this->form['severity_level'] ?? null,
                'color' => $this->form['color'] ?? null,
                'geometry' => $this->geometry,
            ],
            [
                'name' => ['required', 'string', 'max:120'],
                'code' => [
                    'required',
                    'string',
                    'max:40',
                    'regex:/^[A-Za-z0-9_\-]+$/',
                    function ($attribute, $value, $fail) {
                        $exists = Zone::query()
                            ->where('code', (string) $value)
                            ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
                            ->exists();

                        if ($exists) {
                            $fail('این کد منطقه قبلاً ثبت شده است.');
                        }
                    },
                ],
                'type' => ['required', 'in:polygon,circle,rectangle'],
                'severity_level' => ['required', 'in:low,medium,high,critical'],
                'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'geometry' => ['required'],
            ],
            [
                'name.required' => 'نام منطقه الزامی است.',
                'code.required' => 'کد منطقه الزامی است.',
                'code.regex' => 'کد منطقه فقط شامل حروف انگلیسی، عدد، خط تیره و زیرخط باشد.',
                'color.regex' => 'رنگ باید به فرمت #RRGGBB باشد.',
                'geometry.required' => 'ابتدا شکل منطقه را روی نقشه رسم کنید.',
            ]
        );

        $geoValidation = $this->geometry
            ? GeoJson::validate($this->geometry, (string) $this->form['type'])
            : ['valid' => false, 'error' => 'ابتدا شکل منطقه را روی نقشه رسم کنید.'];

        if (! $geoValidation['valid']) {
            $validator->errors()->add('geometry', $geoValidation['error']);
        }

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->dispatch('toast', type: 'error', title: 'خطا در ثبت منطقه', body: $message);
            }

            return;
        }

        $zone = $this->editingId
            ? Zone::query()->findOrFail($this->editingId)
            : new Zone();

        $zone->forceFill([
            'name' => trim((string) $this->form['name']),
            'code' => trim((string) $this->form['code']),
            'type' => $this->form['type'],
            'severity_level' => $this->form['severity_level'],
            'color' => $this->form['color'],
            'description' => trim((string) ($this->form['description'] ?? '')),
            'is_active' => (bool) ($this->form['is_active'] ?? true),
            'geometry' => $this->geometry,
        ]);

        // BBox is always derived server-side before hitting the DB.
        $zone->syncBoundingBox();
        $zone->save();

        $this->syncRules($zone);

        $index->forget();

        $wasEditing = (bool) $this->editingId;

        $this->loadZones();
        $this->showForm = false;
        $this->resetForm();

        $this->dispatch('zb-draft', geometry: null, type: null);
        $this->dispatch('zb-draw', mode: null);
        $this->dispatch('toast', type: 'success',
            title: $wasEditing ? 'منطقه بروزرسانی شد' : 'منطقه جدید ثبت شد',
            body: "کد: {$zone->code}");
    }

    protected function syncRules(Zone $zone): void
    {
        $zone->accessRules()->delete();

        $zone->accessRules()->create([
            'target_type' => 'person',
            'target_id' => null,
            'target_value' => ZoneAccessRule::WILDCARD,
            'access_type' => ($this->form['default_access'] ?? 'allow') === 'deny' ? 'deny' : 'allow',
        ]);

        foreach ($this->form['rules'] ?? [] as $rule) {
            $zone->accessRules()->create([
                'target_type' => in_array($rule['target_type'] ?? null, ZoneAccessRule::TARGET_TYPES, true)
                    ? $rule['target_type'] : 'department',
                'target_id' => $rule['target_id'] ?? null,
                'target_value' => $rule['target_value'] ?? null,
                'access_type' => ($rule['access_type'] ?? 'allow') === 'deny' ? 'deny' : 'allow',
            ]);
        }
    }

    /**
     * Convert the drawn geometry to whatever shape the operator picked in the
     * form (circle ↔ polygon), so switching type never loses the drawing.
     */
    protected function reconcileGeometry(): void
    {
        if ($this->geometry === null || $this->geometryType === null) {
            return;
        }

        $target = (string) ($this->form['type'] ?? $this->geometryType);

        if ($target === $this->geometryType) {
            return;
        }

        if ($target === 'circle' && ($this->geometry['type'] ?? null) !== 'Point') {
            $bbox = GeoJson::bbox($this->geometry);

            if ($bbox) {
                $centerLat = ($bbox[0] + $bbox[2]) / 2;
                $centerLng = ($bbox[1] + $bbox[3]) / 2;
                $radius = max(5, (int) round(
                    max(
                        \App\Services\Geofencing\Geometry::distanceMeters($centerLat, $centerLng, $bbox[0], $bbox[1]),
                        \App\Services\Geofencing\Geometry::distanceMeters($centerLat, $centerLng, $bbox[2], $bbox[3])
                    )
                ));

                $this->geometry = [
                    'type' => 'Point',
                    'coordinates' => [round($centerLng, 8), round($centerLat, 8)],
                    'properties' => ['radius' => $radius],
                ];
                $this->geometryType = 'circle';
            }

            return;
        }

        if ($target !== 'circle' && ($this->geometry['type'] ?? null) === 'Point') {
            $lng = (float) $this->geometry['coordinates'][0];
            $lat = (float) $this->geometry['coordinates'][1];
            $radius = (float) ($this->geometry['properties']['radius'] ?? 100);

            $dLat = $radius / 111320;
            $cos = max(cos(deg2rad($lat)), 0.01);
            $dLng = $radius / (111320 * $cos);

            $ring = [];
            for ($i = 0; $i < 32; $i++) {
                $angle = (2 * M_PI * $i) / 32;
                $ring[] = [
                    round($lng + cos($angle) * $dLng, 8),
                    round($lat + sin($angle) * $dLat, 8),
                ];
            }
            $ring[] = $ring[0];

            $this->geometry = ['type' => 'Polygon', 'coordinates' => [$ring]];
            $this->geometryType = $target;
        }
    }

    public function toggleActive(int $id, ZoneIndex $index): void
    {
        $zone = Zone::query()->findOrFail($id);
        $zone->is_active = ! $zone->is_active;
        $zone->save();

        $index->forget();
        $this->loadZones();

        $this->dispatch('toast', type: 'info', title: 'وضعیت منطقه تغییر کرد',
            body: $zone->code.' → '.($zone->is_active ? 'فعال' : 'غیرفعال'));
    }

    public function destroy(int $id, ZoneIndex $index): void
    {
        $zone = Zone::query()->findOrFail($id);
        $zone->delete();

        $index->forget();
        $this->loadZones();

        $this->dispatch('toast', type: 'success', title: 'منطقه حذف شد', body: $zone->code);
    }

    public function focusZone(int $id): void
    {
        $zone = Zone::query()->findOrFail($id);
        $this->dispatch('zb-focus', geometry: $zone->geometry);
    }

    public function updated(string $property): void
    {
        if ($property === 'search') {
            return;
        }

        // Live re-validation of the drawing while the form is open.
        if ($property === 'form.type' && $this->geometry) {
            $this->geometryType = $this->form['type'];
            $validation = GeoJson::validate($this->geometry, $this->geometryType);
            $this->geometryError = $validation['valid'] ? null : $validation['error'];
            $this->bbox = GeoJson::bbox($this->geometry);
        }
    }

    /* ---------------------------------------------------------------- */

    /** @return array<int, array<string, mixed>> */
    public function filteredZones(): array
    {
        $term = trim($this->search);

        if ($term === '') {
            return $this->zones;
        }

        return array_values(array_filter(
            $this->zones,
            fn (array $zone) => str_contains($zone['name'], $term)
                || stripos($zone['code'], $term) !== false
        ));
    }

    /** @return array<string, mixed> */
    public function mapOptions(): array
    {
        return [
            'layers' => config('zone.tile_layers'),
            'center' => config('zone.map.center'),
            'zoom' => config('zone.map.zoom'),
            'theme' => $this->mapTheme,
            'zones' => $this->zones,
        ];
    }

    /** @return array<int, string> */
    public function departments(): array
    {
        return Person::query()
            ->whereNotNull('department')
            ->distinct()
            ->orderBy('department')
            ->pluck('department')
            ->all();
    }

    public function render()
    {
        return view('livewire.zone-builder', [
            'departments' => $this->departments(),
        ]);
    }
}
