<div class="space-y-4">

    {{-- ======================= header ======================= --}}
    <div class="flex flex-wrap items-center gap-3">
        <div>
            <h1 class="text-lg font-extrabold text-slate-900 dark:text-white">مناطق جغرافیایی (Zone Builder)</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">رسم چندضلعی/دایره، اعتبارسنجی GeoJSON و محاسبه خودکار BBox پیش از ذخیره</p>
        </div>

        <div class="ms-auto flex items-center gap-2">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="جست‌وجوی نام یا کد..."
                   class="input w-52">
            <button type="button" wire:click="fitZones" class="btn-ghost" title="نمایش همه مناطق">
                <i class="fa-solid fa-expand"></i>
            </button>
            <button type="button" wire:click="openCreate" class="btn-primary">
                <i class="fa-solid fa-plus"></i>
                منطقه جدید
            </button>
        </div>
    </div>

    {{-- ======================= body ======================= --}}
    <div class="grid gap-4 xl:grid-cols-[1fr_22rem]">

        {{-- ---------- map (Leaflet) ---------- --}}
        <div class="card relative h-[70vh] min-h-[480px] overflow-hidden p-0 xl:h-[calc(100vh-13rem)]"
             wire:ignore
             x-data="{ theme: @js($mapTheme), mode: 'polygon', menu: false }"
             x-init="ZONE.bootBuilder($el)"
             x-on:zb-draw.window="mode = $event.detail.mode || null"
             data-options="{{ json_encode($this->mapOptions(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) }}">

            <div data-map-canvas class="absolute inset-0 z-0"></div>

            {{-- drawing toolbar --}}
            <div class="absolute top-3 z-[1000] flex flex-col gap-2 end-3">
                <div class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-white/95 p-1.5 shadow-lg backdrop-blur dark:border-slate-700 dark:bg-slate-900/95">
                    <button type="button"
                            @click="ZONE.builder($root).setDrawMode('polygon'); mode = 'polygon'"
                            :class="mode === 'polygon' ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
                            class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-bold transition" title="چندضلعی">
                        <i class="fa-solid fa-draw-polygon w-4 text-center"></i> چندضلعی
                    </button>
                    <button type="button"
                            @click="ZONE.builder($root).setDrawMode('rectangle'); mode = 'rectangle'"
                            :class="mode === 'rectangle' ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
                            class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-bold transition" title="مستطیل">
                        <i class="fa-regular fa-square w-4 text-center"></i> مستطیل
                    </button>
                    <button type="button"
                            @click="ZONE.builder($root).setDrawMode('circle'); mode = 'circle'"
                            :class="mode === 'circle' ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
                            class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-bold transition" title="دایره">
                        <i class="fa-regular fa-circle-dot w-4 text-center"></i> دایره
                    </button>

                    <div class="my-1 h-px bg-slate-200 dark:bg-slate-700"></div>

                    <button type="button" @click="ZONE.builder($root).setDrawMode('edit')"
                            class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-bold text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" title="ویرایش شکل">
                        <i class="fa-solid fa-pen-to-square w-4 text-center"></i> ویرایش
                    </button>
                    <button type="button" @click="ZONE.builder($root).setDrawMode('clear'); mode = null"
                            class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-bold text-rose-600 transition hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10" title="حذف شکل">
                        <i class="fa-solid fa-eraser w-4 text-center"></i> حذف شکل
                    </button>
                </div>

                {{-- layer switcher --}}
                <div class="relative">
                    <button type="button" @click="menu = !menu"
                            class="flex w-full items-center gap-2 rounded-xl border border-slate-200 bg-white/95 px-3 py-2 text-xs font-bold shadow-lg backdrop-blur transition hover:bg-white dark:border-slate-700 dark:bg-slate-900/95 dark:hover:bg-slate-800">
                        <i class="fa-solid fa-layer-group text-brand-500"></i>
                        نقشه
                        <i class="fa-solid fa-chevron-down ms-auto text-[9px] text-slate-400"></i>
                    </button>

                    <div x-show="menu" @click.outside="menu = false" x-cloak x-transition.opacity
                         class="absolute end-0 mt-2 w-44 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                        @foreach (config('zone.tile_layers') as $key => $layer)
                            <button type="button"
                                    @click="ZONE.builder($root).setTheme('{{ $key }}'); theme = '{{ $key }}'; menu = false"
                                    :class="theme === '{{ $key }}' ? 'bg-brand-50 text-brand-700 dark:bg-slate-800 dark:text-brand-300' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800'"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-start text-xs font-bold">
                                <span class="h-2.5 w-2.5 rounded-full"
                                      style="background: {{ ['street' => '#22c55e', 'satellite' => '#0ea5e9', 'dark' => '#334155', 'light' => '#f59e0b'][$key] ?? '#6366f1' }}"></span>
                                {{ $layer['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- legend --}}
            <div class="absolute bottom-3 start-3 z-[1000] rounded-xl border border-slate-200 bg-white/95 px-3 py-2 text-[11px] shadow-lg backdrop-blur dark:border-slate-700 dark:bg-slate-900/95">
                <div class="flex items-center gap-3 font-bold">
                    <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-500"></span> فعال</span>
                    <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-slate-400"></span> غیرفعال</span>
                    <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-violet-500"></span> شکل در حال رسم</span>
                </div>
            </div>
        </div>

        {{-- ---------- zone list ---------- --}}
        <aside class="card flex h-[70vh] min-h-[480px] flex-col p-0 xl:h-[calc(100vh-13rem)]">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                <h2 class="text-sm font-extrabold">فهرست مناطق ({{ count($this->filteredZones()) }})</h2>
                <span class="chip bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                    {{ collect($zones)->where('is_active', true)->count() }} فعال
                </span>
            </div>

            <div class="flex-1 space-y-2 overflow-y-auto p-3">
                @forelse ($this->filteredZones() as $zone)
                    <div wire:key="zone-{{ $zone['id'] }}"
                         @class([
                             'group rounded-xl border p-3 transition hover:shadow-md',
                             'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950' => $zone['is_active'],
                             'border-dashed border-slate-300 bg-slate-50/60 opacity-70 dark:border-slate-700 dark:bg-slate-900/40' => ! $zone['is_active'],
                         ])>
                        <div class="flex items-start gap-2.5">
                            <span class="mt-1 h-8 w-2.5 shrink-0 rounded-full" style="background: {{ $zone['color'] }}"></span>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="truncate text-sm font-extrabold">{{ $zone['name'] }}</span>
                                    <span dir="ltr" class="shrink-0 font-mono text-[10px] text-slate-400">{{ $zone['code'] }}</span>
                                </div>

                                <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[10px]">
                                    @php
                                        $severityClass = [
                                            'low' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                                            'medium' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
                                            'high' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
                                            'critical' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
                                        ];
                                        $severityLabel = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد', 'critical' => 'بحرانی'];
                                    @endphp
                                    <span class="chip {{ $severityClass[$zone['severity_level']] ?? '' }}">
                                        {{ $severityLabel[$zone['severity_level']] ?? $zone['severity_level'] }}
                                    </span>
                                    <span class="chip bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                        {{ $zone['type'] === 'circle' ? 'دایره' : ($zone['type'] === 'rectangle' ? 'مستطیل' : 'چندضلعی') }}
                                    </span>
                                    <span class="chip {{ $zone['is_active'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-slate-200 text-slate-500 dark:bg-slate-800 dark:text-slate-400' }}">
                                        {{ $zone['is_active'] ? 'فعال' : 'غیرفعال' }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-col gap-1 opacity-0 transition group-hover:opacity-100">
                                <button type="button" wire:click="focusZone({{ $zone['id'] }})"
                                        class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-brand-600 dark:hover:bg-slate-800"
                                        title="نمایش روی نقشه">
                                    <i class="fa-solid fa-crosshairs text-xs"></i>
                                </button>
                                <button type="button" wire:click="openEdit({{ $zone['id'] }})"
                                        class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-brand-600 dark:hover:bg-slate-800"
                                        title="ویرایش">
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </button>
                                <button type="button" wire:click="toggleActive({{ $zone['id'] }})"
                                        class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-amber-600 dark:hover:bg-slate-800"
                                        title="فعال/غیرفعال">
                                    <i class="fa-solid {{ $zone['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off' }} text-xs"></i>
                                </button>
                                <button type="button" wire:click="destroy({{ $zone['id'] }})"
                                        wire:confirm="این منطقه و قوانین آن حذف شود؟"
                                        class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10"
                                        title="حذف">
                                    <i class="fa-solid fa-trash text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="flex h-full flex-col items-center justify-center gap-2 px-6 text-center text-slate-400">
                        <i class="fa-regular fa-pen-to-square text-3xl"></i>
                        <p class="text-xs leading-6">هنوز منطقه‌ای تعریف نشده است.<br>اولین محدوده را روی نقشه رسم کنید.</p>
                        <button type="button" wire:click="openCreate" class="btn-primary mt-1 text-xs">ساخت منطقه</button>
                    </div>
                @endforelse
            </div>
        </aside>
    </div>

    {{-- ======================= metadata modal ======================= --}}
    @if ($showForm)
        {{-- The backdrop must NOT swallow map clicks: the operator draws the
             shape while this form is open, so only the card itself is hit-testable. --}}
        <div class="pointer-events-none fixed inset-0 z-[2000] flex items-start justify-center overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-sm"
             x-data x-show="true" x-transition.opacity>
            <div class="card pointer-events-auto my-8 w-full max-w-3xl shadow-2xl" x-transition.scale.95>
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-base font-extrabold">
                            {{ $editingId ? 'ویرایش منطقه' : 'منطقه جدید' }}
                        </h2>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">متادیتا، شدت ریسک و قوانین دسترسی</p>
                    </div>
                    <button type="button" wire:click="closeForm"
                            class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 dark:hover:bg-slate-800">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="grid gap-5 p-5 md:grid-cols-2">

                    {{-- geometry status --}}
                    <div class="md:col-span-2">
                        @if ($geometry)
                            <div @class([
                                'rounded-xl border p-3 text-xs',
                                'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-950 dark:text-emerald-200' => ! $geometryError,
                                'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-500/30 dark:bg-rose-950 dark:text-rose-200' => $geometryError,
                            ])>
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 font-bold">
                                    <span><i class="fa-solid fa-shapes ms-1"></i> نوع هندسه: {{ $geometry['type'] ?? '—' }}</span>
                                    @if ($bbox)
                                        <span dir="ltr" class="font-mono">
                                            BBox: {{ number_format($bbox[0], 6) }}, {{ number_format($bbox[1], 6) }} → {{ number_format($bbox[2], 6) }}, {{ number_format($bbox[3], 6) }}
                                        </span>
                                    @endif
                                    @if (! $geometryError)
                                        <span class="chip bg-emerald-600 text-white"><i class="fa-solid fa-check"></i> GeoJSON معتبر</span>
                                    @endif
                                </div>
                                @if ($geometryError)
                                    <div class="mt-1 font-semibold">{{ $geometryError }}</div>
                                @endif
                            </div>
                        @else
                            <div class="rounded-xl border border-dashed border-slate-300 p-3 text-center text-xs font-semibold text-slate-500 dark:border-slate-700 dark:text-slate-400">
                                <i class="fa-solid fa-hand-pointer ms-1"></i>
                                ابزار رسم را انتخاب کنید و شکل منطقه را روی نقشه بکشید.
                            </div>
                        @endif
                    </div>

                    <div>
                        <label class="label">نام منطقه <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="form.name" class="input" placeholder="مثلاً: محوطه سوخت">
                    </div>

                    <div>
                        <label class="label">کد منطقه <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="form.code" dir="ltr" class="input font-mono" placeholder="SOULEH_01">
                    </div>

                    <div>
                        <label class="label">نوع هندسه</label>
                        <select wire:model.live="form.type" class="input">
                            <option value="polygon">چندضلعی (Polygon)</option>
                            <option value="rectangle">مستطیل (Rectangle)</option>
                            <option value="circle">دایره (Circle)</option>
                        </select>
                    </div>

                    <div>
                        <label class="label">شدت ریسک</label>
                        <select wire:model="form.severity_level" class="input">
                            <option value="low">کم</option>
                            <option value="medium">متوسط</option>
                            <option value="high">زیاد</option>
                            <option value="critical">بحرانی</option>
                        </select>
                    </div>

                    <div>
                        <label class="label">رنگ منطقه</label>
                        <div class="flex items-center gap-2">
                            <input type="color" wire:model.live="form.color"
                                   class="h-10 w-14 cursor-pointer rounded-lg border border-slate-300 bg-transparent p-1 dark:border-slate-700">
                            <input type="text" wire:model.live="form.color" dir="ltr" class="input font-mono" placeholder="#6366f1">
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach (['#6366f1', '#0ea5e9', '#f59e0b', '#ef4444', '#16a34a', '#8b5cf6', '#64748b'] as $swatch)
                                <button type="button" wire:click="$set('form.color', '{{ $swatch }}')"
                                        class="h-6 w-6 rounded-full border-2 border-white shadow transition hover:scale-110 dark:border-slate-800"
                                        style="background: {{ $swatch }}"></button>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="label">وضعیت</label>
                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-semibold dark:border-slate-700">
                            <input type="checkbox" wire:model="form.is_active" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            منطقه فعال باشد (در ارزیابی پینگ‌ها شرکت کند)
                        </label>
                    </div>

                    <div class="md:col-span-2">
                        <label class="label">توضیحات</label>
                        <textarea wire:model="form.description" rows="2" class="input" placeholder="توضیح کوتاه درباره این محدوده"></textarea>
                    </div>

                    {{-- access rules --}}
                    <div class="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-950/60">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-sm font-extrabold"><i class="fa-solid fa-user-shield ms-1 text-brand-500"></i> قوانین دسترسی</h3>
                            <div class="flex items-center gap-2">
                                <span class="text-[11px] text-slate-500">بقیه افراد:</span>
                                <select wire:model.live="form.default_access" class="input w-28 py-1.5 text-xs">
                                    <option value="allow">مجاز</option>
                                    <option value="deny">ممنوع</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3 grid gap-2 sm:grid-cols-[1fr_1.4fr_auto_auto]">
                            <select wire:model.live="form.rule_type" class="input py-1.5 text-xs">
                                <option value="department">واحد سازمانی</option>
                                <option value="contract_type">نوع قرارداد</option>
                                <option value="person">پرسنل (کد)</option>
                            </select>

                            <input type="text" wire:model="form.rule_value" list="department-list"
                                   class="input py-1.5 text-xs" placeholder="مقدار قانون…"
                                   wire:keydown.enter="addRule">

                            <select wire:model.live="form.rule_access" class="input w-24 py-1.5 text-xs">
                                <option value="allow">مجاز</option>
                                <option value="deny">ممنوع</option>
                            </select>

                            <button type="button" wire:click="addRule" class="btn-primary px-3 py-1.5 text-xs">
                                <i class="fa-solid fa-plus"></i> افزودن
                            </button>
                        </div>

                        <datalist id="department-list">
                            @foreach ($departments as $department)
                                <option value="{{ $department }}"></option>
                            @endforeach
                            @foreach (\App\Models\Person::CONTRACT_TYPES as $contract)
                                <option value="{{ $contract }}"></option>
                            @endforeach
                        </datalist>

                        <div class="flex flex-wrap gap-2">
                            @forelse ($form['rules'] ?? [] as $index => $rule)
                                <span @class([
                                    'chip gap-2 py-1.5 ps-3',
                                    'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300' => ($rule['access_type'] ?? '') === 'deny',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => ($rule['access_type'] ?? '') !== 'deny',
                                ]) wire:key="rule-{{ $index }}">
                                    {{ ['person' => 'پرسنل', 'department' => 'واحد', 'contract_type' => 'قرارداد'][$rule['target_type']] ?? '—' }}:
                                    <b dir="auto">{{ $rule['target_value'] }}</b>
                                    → {{ ($rule['access_type'] ?? '') === 'deny' ? 'ممنوع' : 'مجاز' }}
                                    <button type="button" wire:click="removeRule({{ $index }})"
                                            class="ms-1 rounded-full px-1 transition hover:bg-black/10">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </span>
                            @empty
                                <span class="text-[11px] text-slate-400">قانون خاصی اضافه نشده — فقط قانون پیش‌فرض بالا اعمال می‌شود.</span>
                            @endforelse
                        </div>

                        <p class="mt-2 text-[10px] leading-5 text-slate-400">
                            اولویت اعمال: پرسنل ← واحد سازمانی ← نوع قرارداد ← قانون پیش‌فرض.
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                    <p class="text-[11px] text-slate-400">
                        BBox به‌صورت سروری از روی GeoJSON محاسبه و در ستون‌های
                        <span dir="ltr" class="font-mono">min_/max_lat/lng</span> ذخیره می‌شود.
                    </p>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="closeForm" class="btn-ghost">انصراف</button>
                        <button type="button" wire:click="save" wire:loading.attr="disabled" class="btn-primary">
                            <span wire:loading.remove wire:target="save">
                                <i class="fa-solid fa-floppy-disk"></i> ذخیره منطقه
                            </span>
                            <span wire:loading wire:target="save">
                                <i class="fa-solid fa-circle-notch fa-spin"></i> در حال ذخیره…
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @push('scripts')
        @vite(['resources/js/zone-builder.js'])
    @endpush
</div>
