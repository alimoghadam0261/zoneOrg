<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-3">
        <div>
            <h1 class="text-lg font-extrabold text-slate-900 dark:text-white">پرسنل</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">کارمندان، پیمانکاران و مهمان‌های ثبت‌شده</p>
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

            <input type="search" wire:model.live.debounce.300ms="search" placeholder="نام، کد پرسنلی یا واحد…"
                   class="input w-60 py-2 text-xs">
        </div>
    </div>

    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-start text-xs">
                <thead class="bg-slate-50 text-[11px] text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3 text-start font-black">پرسنل</th>
                        <th class="px-4 py-3 text-start font-black">کد ملی</th>
                        <th class="px-4 py-3 text-start font-black">واحد</th>
                        <th class="px-4 py-3 text-start font-black">نوع قرارداد</th>
                        <th class="px-4 py-3 text-start font-black">وضعیت</th>
                        <th class="px-4 py-3 text-start font-black">دستگاه</th>
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
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-slate-400">پرسنلی یافت نشد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">
            {{ $people->links() }}
        </div>
    </div>
</div>
