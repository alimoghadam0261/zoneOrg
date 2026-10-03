<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-3">
        <div>
            <h1 class="text-lg font-extrabold text-slate-900 dark:text-white">دستگاه‌ها و توکن‌ها</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">تگ‌های GPS و اپلیکیشن موبایل — احراز هویت با Bearer Token (Sanctum)</p>
        </div>

        <div class="ms-auto">
            <button type="button" wire:click="openCreate" class="btn-primary">
                <i class="fa-solid fa-plus"></i> دستگاه جدید
            </button>
        </div>
    </div>

    {{-- token reveal --}}
    @if ($plainTextToken)
        <div class="card border-amber-300 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-950/40">
            <div class="flex flex-wrap items-start gap-3">
                <i class="fa-solid fa-key mt-1 text-amber-500"></i>
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-extrabold text-amber-800 dark:text-amber-200">
                        توکن دستگاه صادر شد — فقط یک بار نمایش داده می‌شود
                    </div>
                    <code dir="ltr" class="mt-2 block select-all break-all rounded-lg bg-slate-900 px-3 py-2 text-xs text-emerald-300">
                        {{ $plainTextToken }}
                    </code>
                    <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">
                        نمونه فراخوانی:
                        <span dir="ltr" class="font-mono">POST /api/v1/telemetry/ping</span> با هدر
                        <span dir="ltr" class="font-mono">Authorization: Bearer {{ mb_substr($plainTextToken, 0, 12) }}…</span>
                    </p>
                </div>
                <button type="button" wire:click="$set('plainTextToken', null)" class="btn-ghost text-xs">بستن</button>
            </div>
        </div>
    @endif

    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-start text-xs">
                <thead class="bg-slate-50 text-[11px] text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3 text-start font-black">شناسه دستگاه</th>
                        <th class="px-4 py-3 text-start font-black">نوع</th>
                        <th class="px-4 py-3 text-start font-black">پرسنل</th>
                        <th class="px-4 py-3 text-start font-black">باتری</th>
                        <th class="px-4 py-3 text-start font-black">آخرین اتصال</th>
                        <th class="px-4 py-3 text-start font-black">توکن</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($devices as $device)
                        <tr wire:key="device-{{ $device->id }}" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-4 py-3 font-mono text-[11px]" dir="ltr">{{ $device->device_uid }}</td>
                            <td class="px-4 py-3">
                                <span class="chip bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                                    <i class="fa-solid {{ $device->type === 'gps_tag' ? 'fa-satellite-dish' : 'fa-mobile-screen' }}"></i>
                                    {{ $device->type === 'gps_tag' ? 'تگ GPS' : 'اپ موبایل' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if ($device->person)
                                    <div class="font-bold">{{ $device->person->full_name }}</div>
                                    <div dir="ltr" class="text-start font-mono text-[10px] text-slate-400">{{ $device->person->personnel_code }}</div>
                                @else
                                    <span class="text-[11px] text-slate-400">متصل نیست</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 {{ $device->battery_level <= 20 ? 'text-rose-600' : 'text-slate-500' }}">
                                    <i class="fa-solid {{ $device->battery_level <= 20 ? 'fa-battery-quarter' : 'fa-battery-three-quarters' }}"></i>
                                    {{ $device->battery_level }}٪
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono text-[10px] text-slate-500" dir="ltr">
                                {{ $device->last_seen_at?->format('Y-m-d H:i:s') ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($device->api_token)
                                    <span class="chip bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">فعال</span>
                                @else
                                    <span class="chip bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">بدون توکن</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button" wire:click="issueToken({{ $device->id }})"
                                            class="rounded-md border border-slate-200 px-2.5 py-1 text-[11px] font-bold transition hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800">
                                        <i class="fa-solid fa-key me-1"></i> صدور توکن
                                    </button>
                                    @if ($device->api_token)
                                        <button type="button" wire:click="revokeToken({{ $device->id }})"
                                                class="rounded-md border border-slate-200 px-2.5 py-1 text-[11px] font-bold text-amber-600 transition hover:bg-amber-50 dark:border-slate-700 dark:text-amber-400 dark:hover:bg-amber-500/10">
                                            باطل‌سازی
                                        </button>
                                    @endif
                                    <button type="button" wire:click="destroy({{ $device->id }})"
                                            wire:confirm="این دستگاه حذف شود؟"
                                            class="rounded-md px-2 py-1 text-[11px] font-bold text-rose-500 transition hover:bg-rose-50 dark:hover:bg-rose-500/10">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-slate-400">دستگاهی ثبت نشده است.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">
            {{ $devices->links() }}
        </div>
    </div>

    {{-- create modal --}}
    @if ($showCreate)
        <div class="fixed inset-0 z-[2000] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
             x-data x-show="true" x-transition.opacity>
            <div class="card w-full max-w-lg p-5 shadow-2xl" x-transition.scale.95>
                <h2 class="mb-4 text-base font-extrabold">ثبت دستگاه جدید</h2>

                <div class="space-y-3">
                    <div>
                        <label class="label">شناسه دستگاه (UUID / IMEI) <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="form.device_uid" dir="ltr" class="input font-mono" placeholder="a1b2c3d4-...">
                        @error('form.device_uid') <p class="mt-1 text-[11px] text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label">نوع دستگاه</label>
                            <select wire:model="form.type" class="input">
                                <option value="gps_tag">تگ GPS</option>
                                <option value="mobile_app">اپلیکیشن موبایل</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">پرسنل مرتبط</label>
                            <select wire:model="form.person_id" class="input">
                                <option value="">— بدون پرسنل —</option>
                                @foreach ($people as $person)
                                    <option value="{{ $person->id }}">{{ $person->full_name }} ({{ $person->personnel_code }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="label">میزان باتری اولیه (٪)</label>
                        <input type="number" wire:model="form.battery_level" min="0" max="100" class="input" dir="ltr">
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="closeCreate" class="btn-ghost">انصراف</button>
                    <button type="button" wire:click="create" class="btn-primary">ثبت دستگاه</button>
                </div>
            </div>
        </div>
    @endif
</div>
