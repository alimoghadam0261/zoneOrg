@props([])

<aside
    x-show="open"
    x-transition.opacity.duration.150ms
    class="fixed inset-y-0 start-0 z-40 w-64 shrink-0 translate-x-full border-e border-slate-200 bg-white pt-4 shadow-xl transition-transform dark:border-slate-800 dark:bg-slate-900 lg:static lg:z-auto lg:translate-x-0 lg:shadow-none"
    x-cloak
>
    <div class="mb-6 flex items-center gap-3 px-5">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-lg shadow-brand-600/30">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <div>
            <div class="text-base font-extrabold leading-tight text-slate-900 dark:text-white">ZONE</div>
            <div class="text-[11px] leading-tight text-slate-500 dark:text-slate-400">سامانه کنترل دسترسی و محدوده جغرافیایی</div>
        </div>
    </div>

    <nav class="space-y-1 px-3 pb-8 text-sm">
        <p class="px-3 pb-1 pt-3 text-[11px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">نظارت</p>

        <a href="{{ route('monitoring') }}"
           @class([
               'flex items-center gap-3 rounded-lg px-3 py-2.5 font-semibold transition',
               'bg-brand-600 text-white shadow-md shadow-brand-600/25' => request()->routeIs('monitoring'),
               'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' => ! request()->routeIs('monitoring'),
           ])>
            <i class="fa-solid fa-tower-broadcast w-5 text-center"></i>
            <span>داشبورد زنده</span>
            <span class="ms-auto h-2 w-2 animate-pulse rounded-full bg-emerald-400"></span>
        </a>

        <a href="{{ route('events.index') }}"
           @class([
               'flex items-center gap-3 rounded-lg px-3 py-2.5 font-semibold transition',
               'bg-brand-600 text-white shadow-md shadow-brand-600/25' => request()->routeIs('events.*'),
               'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' => ! request()->routeIs('events.*'),
           ])>
            <i class="fa-solid fa-triangle-exclamation w-5 text-center"></i>
            <span>رویدادها و هشدارها</span>
        </a>

        <p class="px-3 pb-1 pt-5 text-[11px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">مدیریت محدوده</p>

        <div x-data="{ expanded: {{ request()->routeIs('zones.*') ? 'true' : 'false' }} }">
            <button type="button" @click="expanded = !expanded"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 font-semibold transition
                           {{ request()->routeIs('zones.*') ? 'bg-brand-600 text-white shadow-md shadow-brand-600/25' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                <i class="fa-solid fa-draw-polygon w-5 text-center"></i>
                <span>مناطق جغرافیایی</span>
                <i class="fa-solid fa-chevron-down ms-auto text-[10px] transition-transform"
                   :class="expanded ? 'rotate-180' : ''"></i>
            </button>

            <div x-show="expanded" x-transition.opacity x-cloak class="mt-1 space-y-1 ps-4">
                <a href="{{ route('zones.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 font-semibold transition {{ request()->routeIs('zones.index') ? 'bg-brand-50 text-brand-700 dark:bg-slate-800 dark:text-brand-300' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                    <i class="fa-solid fa-plus w-4 text-center text-[11px]"></i> ساخت و ویرایش منطقه
                </a>
                <a href="{{ route('people.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 font-semibold transition {{ request()->routeIs('people.*') ? 'bg-brand-50 text-brand-700 dark:bg-slate-800 dark:text-brand-300' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                    <i class="fa-solid fa-users w-4 text-center text-[11px]"></i> پرسنل
                </a>
                <a href="{{ route('devices.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 font-semibold transition {{ request()->routeIs('devices.*') ? 'bg-brand-50 text-brand-700 dark:bg-slate-800 dark:text-brand-300' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                    <i class="fa-solid fa-satellite-dish w-4 text-center text-[11px]"></i> دستگاه‌ها و توکن‌ها
                </a>
            </div>
        </div>

        <p class="px-3 pb-1 pt-5 text-[11px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">سیستم</p>

        <div class="rounded-lg border border-dashed border-slate-200 p-3 text-[11px] leading-6 text-slate-500 dark:border-slate-700 dark:text-slate-400">
            <div class="mb-1 flex items-center justify-between">
                <span>نگهداری داده خام</span>
                <span class="chip bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">{{ config('zone.retention_days') }} روز</span>
            </div>
            <div class="flex items-center justify-between">
                <span>آستانه دقت هشدار</span>
                <span class="chip bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">{{ rtrim(rtrim(number_format(config('zone.min_accuracy_meters'), 0), '0'), '.') }} متر</span>
            </div>
            <div class="flex items-center justify-between">
                <span>تأیید نقض (پینگ متوالی)</span>
                <span class="chip bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">{{ config('zone.confirm_pings') }}</span>
            </div>
        </div>
    </nav>
</aside>

<div class="fixed inset-0 z-30 bg-slate-950/50 lg:hidden" x-show="open" @click="open = false" x-cloak></div>
