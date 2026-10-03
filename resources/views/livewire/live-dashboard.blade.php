<div class="space-y-4" wire:poll.3s="refresh">

    {{-- ======================= header ======================= --}}
    <div class="flex flex-wrap items-center gap-3">
        <div>
            <h1 class="flex items-center gap-2 text-lg font-extrabold text-slate-900 dark:text-white">
                داشبورد زنده امنیت
                <span class="h-2.5 w-2.5 animate-pulse rounded-full bg-emerald-500"></span>
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                آخرین موقعیت {{ $stats['total'] ?? 0 }} پرسنل — بروزرسانی خودکار هر ۳ ثانیه
            </p>
        </div>

        <div class="ms-auto flex flex-wrap items-center gap-2">
            <span class="hidden text-[11px] font-bold text-brand-500" wire:loading wire:target="refresh">
                <i class="fa-solid fa-circle-notch fa-spin"></i> در حال بروزرسانی…
            </span>

            <div class="flex rounded-lg border border-slate-200 p-1 dark:border-slate-700">
                @foreach (['all' => 'همه', 'violation' => 'نقض', 'outside' => 'خارج', 'offline' => 'آفلاین'] as $key => $label)
                    <button type="button" wire:click="setStatusFilter('{{ $key }}')"
                            @class([
                                'rounded-md px-3 py-1.5 text-xs font-bold transition',
                                'bg-brand-600 text-white' => $statusFilter === $key,
                                'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' => $statusFilter !== $key,
                            ])>
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div x-data="{ sound: window.ZONE ? ZONE.soundOn() : true }"
                 class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 dark:border-slate-700">
                <i class="fa-solid text-sm" :class="sound ? 'fa-bell text-rose-500' : 'fa-bell-slash text-slate-400'"></i>
                <button type="button" @click="sound = ZONE.toggleSound()" class="text-xs font-bold">
                    هشدار صوتی
                </button>
            </div>
        </div>
    </div>

    {{-- ======================= stats ======================= --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        @php
            $cards = [
                ['label' => 'کل پرسنل', 'value' => $stats['total'] ?? 0, 'icon' => 'fa-users', 'class' => 'text-slate-600 dark:text-slate-300', 'bg' => 'bg-slate-100 dark:bg-slate-800'],
                ['label' => 'آنلاین', 'value' => $stats['online'] ?? 0, 'icon' => 'fa-signal', 'class' => 'text-emerald-600 dark:text-emerald-400', 'bg' => 'bg-emerald-100 dark:bg-emerald-500/15'],
                ['label' => 'آفلاین', 'value' => $stats['offline'] ?? 0, 'icon' => 'fa-user-slash', 'class' => 'text-slate-500', 'bg' => 'bg-slate-100 dark:bg-slate-800'],
                ['label' => 'نقض فعال', 'value' => $stats['violations'] ?? 0, 'icon' => 'fa-triangle-exclamation', 'class' => 'text-rose-600 dark:text-rose-400', 'bg' => 'bg-rose-100 dark:bg-rose-500/15'],
                ['label' => 'هشدارهای باز', 'value' => $stats['open_alerts'] ?? 0, 'icon' => 'fa-bell', 'class' => 'text-amber-600 dark:text-amber-400', 'bg' => 'bg-amber-100 dark:bg-amber-500/15'],
                ['label' => 'پینگ امروز', 'value' => $stats['pings_today'] ?? 0, 'icon' => 'fa-location-arrow', 'class' => 'text-brand-600 dark:text-brand-400', 'bg' => 'bg-brand-100 dark:bg-brand-500/15'],
            ];
        @endphp

        @foreach ($cards as $card)
            <div class="card flex items-center gap-3 p-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg {{ $card['bg'] }} {{ $card['class'] }}">
                    <i class="fa-solid {{ $card['icon'] }}"></i>
                </span>
                <div class="min-w-0">
                    <div class="text-xl font-black leading-none">{{ $card['value'] }}</div>
                    <div class="mt-1 truncate text-[11px] text-slate-500 dark:text-slate-400">{{ $card['label'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ======================= body ======================= --}}
    <div class="grid gap-4 xl:grid-cols-[23rem_1fr]">

        {{-- ---------- right column: alert feed + personnel ---------- --}}
        <div class="flex flex-col gap-4">

            {{-- alert feed --}}
            <div class="card flex h-[42vh] min-h-[320px] flex-col p-0">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                    <h2 class="flex items-center gap-2 text-sm font-extrabold">
                        <i class="fa-solid fa-tower-broadcast text-rose-500"></i>
                        فید هشدار زنده
                    </h2>
                    <span class="chip bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300">
                        {{ count(array_filter($alerts, fn ($a) => ! $a['is_resolved'])) }} باز
                    </span>
                </div>

                <div class="flex-1 space-y-2 overflow-y-auto p-3">
                    @forelse ($alerts as $alert)
                        <div wire:key="alert-{{ $alert['id'] }}"
                             @class([
                                 'rounded-xl border p-3 transition',
                                 'animate-slide-in',
                                 'border-rose-200 bg-rose-50/70 dark:border-rose-500/30 dark:bg-rose-950/40' => $alert['is_violation'] && ! $alert['is_resolved'],
                                 'border-amber-200 bg-amber-50/70 dark:border-amber-500/30 dark:bg-amber-950/40' => $alert['type'] === 'entered' || $alert['type'] === 'exited',
                                 'border-slate-200 bg-white opacity-70 dark:border-slate-800 dark:bg-slate-900' => $alert['is_resolved'],
                             ])>
                            <div class="flex items-start gap-2.5">
                                <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-white"
                                      style="background: {{ $alert['zone_color'] ?? '#64748b' }}">
                                    <i class="fa-solid {{ $alert['is_violation'] ? 'fa-shield-halved' : 'fa-right-left' }} text-[11px]"></i>
                                </span>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="truncate text-xs font-extrabold">{{ $alert['person'] ?? '—' }}</span>
                                        <span dir="ltr" class="font-mono text-[10px] text-slate-400">{{ $alert['personnel_code'] }}</span>
                                    </div>
                                    <div class="mt-0.5 truncate text-[11px] font-bold" style="color: {{ $alert['zone_color'] ?? '#64748b' }}">
                                        {{ $alert['type_label'] }} — {{ $alert['zone'] }}
                                    </div>
                                    <div class="mt-1 flex items-center gap-2 text-[10px] text-slate-500 dark:text-slate-400">
                                        <span>{{ $alert['time'] }}</span>
                                        @if ($alert['accuracy'] !== null)
                                            <span>· دقت {{ $alert['accuracy'] }}m</span>
                                        @endif
                                    </div>
                                </div>

                                @if (! $alert['is_resolved'] && $alert['is_violation'])
                                    <button type="button" wire:click="resolveAlert({{ $alert['id'] }})"
                                            class="shrink-0 rounded-md bg-white px-2 py-1 text-[10px] font-bold text-slate-600 shadow transition hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                                        بستن
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="flex h-full flex-col items-center justify-center gap-2 text-slate-400">
                            <i class="fa-regular fa-circle-check text-3xl"></i>
                            <p class="text-xs">هیچ رویدادی ثبت نشده است.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- personnel list --}}
            <div class="card flex h-[26vh] min-h-[200px] flex-col p-0">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                    <h2 class="text-sm font-extrabold">پرسنل ({{ count($this->visiblePositions()) }})</h2>
                    <span class="text-[11px] text-slate-400">برای جایروی روی نقشه کلیک کنید</span>
                </div>

                <div class="flex-1 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-800">
                    @forelse ($this->visiblePositions() as $position)
                        <button type="button" wire:click="selectPerson({{ $position['id'] }})"
                                wire:key="person-{{ $position['id'] }}"
                                class="flex w-full items-center gap-3 px-4 py-2.5 text-start transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                            <span @class([
                                'h-2.5 w-2.5 shrink-0 rounded-full',
                                'bg-rose-500 animate-pulse' => $position['status'] === 'violation',
                                'bg-emerald-500' => $position['status'] === 'allowed',
                                'bg-sky-500' => $position['status'] === 'outside',
                                'bg-slate-400' => $position['status'] === 'offline',
                            ])></span>

                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-xs font-bold">{{ $position['name'] }}</span>
                                <span class="block truncate text-[10px] text-slate-400">
                                    {{ $position['code'] }} · {{ $position['department'] ?? '—' }}
                                    @if ($position['zone'])
                                        · <span style="color: {{ $position['zone']['color'] }}">{{ $position['zone']['name'] }}</span>
                                    @endif
                                </span>
                            </span>

                            <span @class([
                                'chip shrink-0',
                                'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300' => $position['status'] === 'violation',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => $position['status'] === 'allowed',
                                'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300' => $position['status'] === 'outside',
                                'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' => $position['status'] === 'offline',
                            ])>
                                {{ $position['status_label'] }}
                            </span>
                        </button>
                    @empty
                        <div class="flex h-full items-center justify-center text-xs text-slate-400">موردی یافت نشد.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ---------- map ---------- --}}
        <div class="card relative h-[72vh] min-h-[520px] overflow-hidden p-0"
             wire:ignore
             x-data
             x-init="ZONE.bootLiveMap($el)"
             data-options="{{ json_encode($this->mapOptions(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) }}"
             x-on:zone-alert.window="ZONE.beep(($event.detail.alerts[0] || {}).severity || 'critical')">

            <div data-map-canvas class="absolute inset-0 z-0"></div>

            <div class="absolute top-3 z-[1000] flex flex-col gap-2 end-3">
                <div class="rounded-xl border border-slate-200 bg-white/95 px-3 py-2 text-[11px] shadow-lg backdrop-blur dark:border-slate-700 dark:bg-slate-900/95"
                     x-data="{ theme: 'satellite', menu: false }">
                    <button type="button" @click="menu = !menu" class="flex w-full items-center gap-2 text-xs font-bold">
                        <i class="fa-solid fa-layer-group text-brand-500"></i>
                        نقشه
                        <i class="fa-solid fa-chevron-down ms-auto text-[9px] text-slate-400"></i>
                    </button>

                    <div x-show="menu" @click.outside="menu = false" x-cloak x-transition.opacity
                         class="absolute end-0 mt-2 w-44 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                        @foreach (config('zone.tile_layers') as $key => $layer)
                            <button type="button"
                                    @click="ZONE.live($root).setTheme('{{ $key }}'); theme = '{{ $key }}'; menu = false"
                                    :class="theme === '{{ $key }}' ? 'bg-brand-50 text-brand-700 dark:bg-slate-800 dark:text-brand-300' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800'"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-start text-xs font-bold">
                                {{ $layer['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white/95 px-3 py-2 shadow-lg backdrop-blur dark:border-slate-700 dark:bg-slate-900/95">
                    <div class="flex flex-col gap-1.5 text-[11px] font-bold">
                        <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full border-2 border-emerald-500 bg-white"></span> مجاز</span>
                        <span class="flex items-center gap-1.5"><span class="h-3 w-3 animate-pulse rounded-full border-2 border-rose-500 bg-white"></span> نقض</span>
                        <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full border-2 border-slate-400 bg-white opacity-70"></span> آفلاین</span>
                    </div>
            </div>
        </div>
    </div>

    @push('scripts')
        @vite(['resources/js/live-map.js'])
    @endpush
</div>

</div>
