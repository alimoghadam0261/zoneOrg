<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-3">
        <div>
            <h1 class="text-lg font-extrabold text-slate-900 dark:text-white">پرسنل</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">کارمندان، پیمانکاران و مهمان‌های ثبت‌شده — لینک گوشی و محدوده دسترسی</p>
        </div>

        <div class="ms-auto flex flex-wrap items-center gap-2">
            <div class="flex rounded-lg border border-slate-200 p-1 dark:border-slate-700">
                @foreach (['all' => 'همه', 'employee' => 'کارمند', 'contractor' => 'پیمانکار', 'visitor' => 'مهمان'] as $key => $label)
                    <button type="button" wire:click="setContract('{{ $key }}')"
                            @class([
                                'rounded-md px-3 py-1.5 text-xs font-bold transition',
                                'bg-brand-600 text-white' => $contract === $key,
                                'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' => $contract !== $key,
                            ])>{{ $label }}</button>
                @endforeach
            </div>

            <input type="search" wire:model.live.debounce.300ms="search" placeholder="نام، کد پرسنلی، موبایل یا واحد…"
                   class="input w-60 py-2 text-xs">

            <button type="button" wire:click="openCreate" class="btn-primary">
                <i class="fa-solid fa-user-plus"></i> پرسنل جدید
            </button>
        </div>
    </div>

    {{-- one-time token reveal after registration --}}
    @if ($plainTextToken)
        <div class="card border-amber-300 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-950/40">
            <div class="flex flex-wrap items-start gap-3">
                <i class="fa-solid fa-key mt-1 text-amber-500"></i>
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-extrabold text-amber-800 dark:text-amber-200">
                        توکن گوشی صادر شد — فقط یک بار نمایش داده می‌شود
                    </div>
                    <code dir="ltr" class="mt-2 block select-all break-all rounded-lg bg-slate-900 px-3 py-2 text-xs text-emerald-300">
                        {{ $plainTextToken }}
                    </code>
                    <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">
                        این توکن را در اپلیکیشن موبایل پرسنل ثبت کنید؛ پینگ‌ها با
                        <span dir="ltr" class="font-mono">Authorization: Bearer …</span> ارسال می‌شوند.
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
                        <th class="px-4 py-3 text-start font-black">پرسنل</th>
                        <th class="px-4 py-3 text-start font-black">موبایل</th>
                        <th class="px-4 py-3 text-start font-black">کد ملی</th>
                        <th class="px-4 py-3 text-start font-black">واحد</th>
                        <th class="px-4 py-3 text-start font-black">نوع قرارداد</th>
                        <th class="px-4 py-3 text-start font-black">وضعیت</th>
                        <th class="px-4 py-3 text-start font-black">دستگاه</th>
                        <th class="px-4 py-3 text-start font-black">محدوده‌ها</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($people as $person)
                        <tr wire:key="person-{{ $person->id }}" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    @if ($person->avatar_url)
                                        <img src="{{ $person->avatar_url }}" alt=""
                                             class="h-9 w-9 rounded-full object-cover ring-2 ring-slate-200 dark:ring-slate-700">
                                    @else
                                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-sm font-black text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                                            {{ mb_substr($person->full_name, 0, 1) }}
                                        </span>
                                    @endif
                                    <div>
                                        <div class="font-bold">{{ $person->full_name }}</div>
                                        <div dir="ltr" class="text-start font-mono text-[10px] text-slate-400">{{ $person->personnel_code }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if ($person->phone)
                                    <span dir="ltr" class="font-mono text-[11px]" title="شماره موبایل">{{ $person->phone }}</span>
                                @else
                                    <span class="text-[11px] text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-[11px] text-slate-500" dir="ltr">{{ $person->national_id ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $person->department ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="chip bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    {{ $person->contractTypeLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if ($person->status === 'active')
                                    <span class="chip bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">فعال</span>
                                @else
                                    <span class="chip bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">غیرفعال</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-500">
                                @if ($person->device)
                                    <span dir="ltr" class="font-mono text-[10px]">{{ $person->device->device_uid }}</span>
                                    <span class="ms-2 text-[10px]">🔋{{ $person->device->battery_level }}٪</span>
                                @else
                                    <span class="text-[11px] text-slate-400">بدون دستگاه</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @forelse ($person->zones as $zone)
                                        <span wire:key="pz-{{ $person->id }}-{{ $zone->id }}"
                                              @class([
                                                  'chip gap-1 py-1',
                                                  'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300' => $zone->pivot->access_type === 'deny',
                                                  'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => $zone->pivot->access_type !== 'deny',
                                              ])>
                                            <span class="h-2 w-2 rounded-full" style="background: {{ $zone->color }}"></span>
                                            {{ $zone->code }}
                                            <button type="button" wire:click="unlinkZone({{ $person->id }}, {{ $zone->id }})"
                                                    wire:confirm="لینک این پرسنل به محدوده حذف شود؟"
                                                    class="ms-0.5 rounded-full px-1 transition hover:bg-black/10"
                                                    title="حذف لینک">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </span>
                                    @empty
                                        <span class="text-[11px] text-slate-400">بدون محدوده</span>
                                    @endforelse

                                    <button type="button" wire:click="openZoneLink({{ $person->id }})"
                                            class="rounded-md border border-slate-200 px-2 py-1 text-[10px] font-bold text-brand-600 transition hover:bg-brand-50 dark:border-slate-700 dark:text-brand-400 dark:hover:bg-brand-500/10"
                                            title="لینک به محدوده">
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-slate-400">پرسنلی یافت نشد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">
            {{ $people->links() }}
        </div>
    </div>

    {{-- ======================= register modal ======================= --}}
    @if ($showCreate)
        <div class="fixed inset-0 z-[2000] flex items-start justify-center overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-sm"
             x-data x-show="true" x-transition.opacity>
            <div class="card my-6 w-full max-w-2xl p-5 shadow-2xl" x-transition.scale.95>
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-extrabold">ثبت پرسنل جدید</h2>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">مشخصات فردی + گوشی همراه + محدوده دسترسی</p>
                    </div>
                    <button type="button" wire:click="closeCreate"
                            class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 dark:hover:bg-slate-800">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="space-y-5">
                    {{-- 1) person --}}
                    <fieldset class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                        <legend class="px-2 text-xs font-extrabold text-brand-600 dark:text-brand-400">
                            <i class="fa-solid fa-id-card ms-1"></i> ۱) مشخصات فردی
                        </legend>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="label">کد پرسنلی <span class="text-rose-500">*</span></label>
                                <input type="text" wire:model="form.personnel_code" dir="ltr" class="input font-mono" placeholder="P-0101">
                                @error('form.personnel_code') <p class="mt-1 text-[11px] text-rose-500">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">نام و نام خانوادگی <span class="text-rose-500">*</span></label>
                                <input type="text" wire:model="form.full_name" class="input" placeholder="مثال: علی احمدی">
                                @error('form.full_name') <p class="mt-1 text-[11px] text-rose-500">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">شماره موبایل</label>
                                <input type="tel" wire:model="form.phone" dir="ltr" class="input font-mono" placeholder="09123456789">
                                @error('form.phone') <p class="mt-1 text-[11px] text-rose-500">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">کد ملی</label>
                                <input type="text" wire:model="form.national_id" dir="ltr" class="input font-mono" placeholder="0012345678">
                                @error('form.national_id') <p class="mt-1 text-[11px] text-rose-500">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">واحد سازمانی</label>
                                <input type="text" wire:model="form.department" class="input" placeholder="تعمیرات برق">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="label">نوع قرارداد</label>
                                    <select wire:model="form.contract_type" class="input">
                                        <option value="employee">کارمند</option>
                                        <option value="contractor">پیمانکار</option>
                                        <option value="visitor">مهمان</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label">وضعیت</label>
                                    <select wire:model="form.status" class="input">
                                        <option value="active">فعال</option>
                                        <option value="inactive">غیرفعال</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    {{-- 2) mobile device --}}
                    <fieldset class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                        <legend class="px-2 text-xs font-extrabold text-brand-600 dark:text-brand-400">
                            <i class="fa-solid fa-mobile-screen-button ms-1"></i> ۲) گوشی همراه (دستگاه)
                        </legend>

                        <label class="mb-3 flex items-center gap-2 text-xs font-bold">
                            <input type="checkbox" wire:model="form.with_device" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            گوشی/دستگاه به این فرد لینک شود
                        </label>

                        @if ($form['with_device'])
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="label">شناسه دستگاه (UUID / IMEI) <span class="text-rose-500">*</span></label>
                                    <input type="text" wire:model="form.device_uid" dir="ltr" class="input font-mono" placeholder="a1b2c3d4-…">
                                    @error('form.device_uid') <p class="mt-1 text-[11px] text-rose-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="label">نوع دستگاه</label>
                                    <select wire:model="form.device_type" class="input">
                                        <option value="mobile_app">اپلیکیشن موبایل</option>
                                        <option value="gps_tag">تگ GPS</option>
                                    </select>
                                </div>
                            </div>

                            <label class="mt-3 flex items-center gap-2 text-xs font-bold">
                                <input type="checkbox" wire:model="form.issue_token" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                توکن دسترسی (Bearer Token) همین حالا صادر شود
                                <span class="text-[10px] font-normal text-slate-400">— فقط یک بار نمایش داده می‌شود</span>
                            </label>
                        @endif
                    </fieldset>

                    {{-- 3) zone link --}}
                    <fieldset class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                        <legend class="px-2 text-xs font-extrabold text-brand-600 dark:text-brand-400">
                            <i class="fa-solid fa-draw-polygon ms-1"></i> ۳) محدوده دسترسی
                        </legend>

                        <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
                            <div>
                                <label class="label">محدوده (منطقه جغرافیایی)</label>
                                <select wire:model="form.zone_id" class="input">
                                    <option value="">— بدون محدوده —</option>
                                    @foreach ($zones as $zone)
                                        <option value="{{ $zone->id }}" @disabled(! $zone->is_active)>
                                            {{ $zone->name }} ({{ $zone->code }})@if(! $zone->is_active) — غیرفعال@endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('form.zone_id') <p class="mt-1 text-[11px] text-rose-500">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">نوع دسترسی</label>
                                <select wire:model="form.access_type" class="input w-36">
                                    <option value="allow">مجاز</option>
                                    <option value="deny">ممنوع</option>
                                </select>
                            </div>
                        </div>

                        <p class="mt-2 text-[10px] leading-5 text-slate-400">
                            با ثبت، برای این فرد یک قانون دسترسی «فردی» (اولویت ۳۰) روی محدوده انتخاب‌شده ساخته می‌شود که بر
                            واحد سازمانی، نوع قرارداد و قانون پیش‌فرض مقدم است.
                        </p>
                    </fieldset>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="closeCreate" class="btn-ghost">انصراف</button>
                    <button type="button" wire:click="create" wire:loading.attr="disabled" class="btn-primary">
                        <span wire:loading.remove wire:target="create">
                            <i class="fa-solid fa-user-check"></i> ثبت پرسنل
                        </span>
                        <span wire:loading wire:target="create">
                            <i class="fa-solid fa-circle-notch fa-spin"></i> در حال ثبت…
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ======================= zone-link modal (existing person) ======================= --}}
    @if ($showZoneLink)
        <div class="fixed inset-0 z-[2000] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
             x-data x-show="true" x-transition.opacity>
            <div class="card w-full max-w-md p-5 shadow-2xl" x-transition.scale.95>
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-extrabold">لینک به محدوده</h2>
                        @if ($linkPerson)
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                {{ $linkPerson->full_name }}
                                <span dir="ltr" class="font-mono">({{ $linkPerson->personnel_code }})</span>
                            </p>
                        @endif
                    </div>
                    <button type="button" wire:click="closeZoneLink"
                            class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 dark:hover:bg-slate-800">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                @if ($linkPerson && count($linkPersonZones))
                    <div class="mb-4">
                        <p class="label">محدوده‌های فعلی</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($linkPerson->zones as $zone)
                                <span wire:key="linkpz-{{ $zone->id }}" class="chip">
                                    <span class="h-2 w-2 rounded-full" style="background: {{ $zone->color }}"></span>
                                    {{ $zone->code }}
                                    <button type="button" wire:click="unlinkZone({{ $linkPerson->id }}, {{ $zone->id }})"
                                            class="ms-1 rounded-full px-1 transition hover:bg-black/10">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
                    <div>
                        <label class="label">محدوده</label>
                        <select wire:model="linkForm.zone_id" class="input">
                            <option value="">— انتخاب کنید —</option>
                            @foreach ($zones as $zone)
                                <option value="{{ $zone->id }}" @disabled(! $zone->is_active)>
                                    {{ $zone->name }} ({{ $zone->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('linkForm.zone_id') <p class="mt-1 text-[11px] text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">دسترسی</label>
                        <select wire:model="linkForm.access_type" class="input w-32">
                            <option value="allow">مجاز</option>
                            <option value="deny">ممنوع</option>
                        </select>
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="closeZoneLink" class="btn-ghost">بستن</button>
                    <button type="button" wire:click="linkZone" class="btn-primary">
                        <i class="fa-solid fa-link"></i> لینک محدوده
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
