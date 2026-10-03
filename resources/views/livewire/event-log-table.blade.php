<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-3">
        <div>
            <h1 class="text-lg font-extrabold text-slate-900 dark:text-white">رویدادها و هشدارها</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">کلیه ورود/خروج‌ها و نقض‌های ثبت‌شده در سامانه</p>
        </div>

        <div class="ms-auto flex flex-wrap items-center gap-2">
            <div class="flex rounded-lg border border-slate-200 p-1 dark:border-slate-700">
                @foreach (['open' => 'باز', 'resolved' => 'بسته‌شده', 'all' => 'همه'] as $key => $label)
                    <button type="button" wire:click="setStatus('{{ $key }}')"
                            @class([
                                'rounded-md px-3 py-1.5 text-xs font-bold transition',
                                'bg-brand-600 text-white' => $status === $key,
                                'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' => $status !== $key,
                            ])>{{ $label }}</button>
                @endforeach
            </div>

            <select wire:model.live="type" class="input w-44 py-2 text-xs">
                <option value="all">همه انواع</option>
                <option value="violation_entered">نقض — ورود غیرمجاز</option>
                <option value="violation_lingering">نقض — تداوم حضور</option>
                <option value="entered">ورود</option>
                <option value="exited">خروج</option>
            </select>

            <input type="search" wire:model.live.debounce.300ms="search" placeholder="جست‌وجوی پرسنل یا منطقه…"
                   class="input w-56 py-2 text-xs">
        </div>
    </div>

    <div class="grid grid-cols-3 gap-3">
        <div class="card p-3">
            <div class="text-xl font-black text-rose-600 dark:text-rose-400">{{ $counts['open'] }}</div>
            <div class="text-[11px] text-slate-500">رویداد باز</div>
        </div>
        <div class="card p-3">
            <div class="text-xl font-black text-amber-600 dark:text-amber-400">{{ $counts['violations'] }}</div>
            <div class="text-[11px] text-slate-500">کل نقض‌ها</div>
        </div>
        <div class="card p-3">
            <div class="text-xl font-black text-brand-600 dark:text-brand-400">{{ $counts['today'] }}</div>
            <div class="text-[11px] text-slate-500">رویدادهای امروز</div>
        </div>
    </div>

    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-start text-xs">
                <thead class="bg-slate-50 text-[11px] uppercase text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3 text-start font-black">زمان</th>
                        <th class="px-4 py-3 text-start font-black">پرسنل</th>
                        <th class="px-4 py-3 text-start font-black">منطقه</th>
                        <th class="px-4 py-3 text-start font-black">رویداد</th>
                        <th class="px-4 py-3 text-start font-black">شدت</th>
                        <th class="px-4 py-3 text-start font-black">موقعیت</th>
                        <th class="px-4 py-3 text-start font-black">وضعیت</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($events as $event)
                        @php
                            $isViolation = str_starts_with($event->event_type, 'violation');
                            $severityClass = [
                                'low' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                                'medium' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
                                'high' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
                                'critical' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
                            ];
                        @endphp
                        <tr wire:key="ev-{{ $event->id }}" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="whitespace-nowrap px-4 py-3 text-slate-500" dir="ltr">{{ $event->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-3">
                                <div class="font-bold">{{ $event->person?->full_name ?? '—' }}</div>
                                <div dir="ltr" class="text-start font-mono text-[10px] text-slate-400">{{ $event->person?->personnel_code }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="chip" style="background: {{ $event->zone?->color }}20; color: {{ $event->zone?->color }}">
                                    {{ $event->zone?->name ?? '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-bold {{ $isViolation ? 'text-rose-600 dark:text-rose-400' : 'text-slate-600 dark:text-slate-300' }}">
                                    <i class="fa-solid {{ $isViolation ? 'fa-shield-halved' : 'fa-right-left' }} me-1"></i>
                                    {{ $event->typeLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="chip {{ $severityClass[$event->severity] ?? '' }}">{{ $event->severity }}</span>
                            </td>
                            <td class="px-4 py-3 font-mono text-[10px] text-slate-400" dir="ltr">
                                {{ round($event->location_snapshot['lat'] ?? 0, 5) }}, {{ round($event->location_snapshot['lng'] ?? 0, 5) }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($event->is_resolved)
                                    <span class="chip bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">بسته‌شده</span>
                                @else
                                    <span class="chip bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300">باز</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-end">
                                @if (! $event->is_resolved)
                                    <button type="button" wire:click="resolve({{ $event->id }})"
                                            class="rounded-md border border-slate-200 px-2.5 py-1 text-[11px] font-bold transition hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800">
                                        بستن
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-slate-400">رویدادی یافت نشد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">
            {{ $events->links() }}
        </div>
    </div>
</div>
