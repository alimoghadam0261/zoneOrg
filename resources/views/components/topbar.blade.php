@props([])

<header
    class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/85 px-3 backdrop-blur lg:px-5 dark:border-slate-800 dark:bg-slate-900/85">

    <button type="button" @click="open = !open"
            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50 lg:hidden dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
            title="منو">
        <i class="fa-solid fa-bars"></i>
    </button>

    <div class="hidden items-center gap-2 text-sm text-slate-500 sm:flex dark:text-slate-400">
        <i class="fa-solid fa-location-crosshairs text-brand-500"></i>
        <span class="font-bold">ایران تعمیرات نیروگاهی</span>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <span>{{ config('app.name') }}</span>
    </div>

    <div class="ms-auto flex items-center gap-2">
        <span class="chip hidden bg-emerald-100 text-emerald-700 sm:inline-flex dark:bg-emerald-500/15 dark:text-emerald-300">
            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>
            سامانه فعال
        </span>

        <button type="button" @click="toggleTheme()"
                class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                title="تاریک / روشن">
            <i class="fa-solid" :class="dark ? 'fa-sun' : 'fa-moon'"></i>
        </button>

        <div class="relative" x-data="{ menu: false }">
            <button type="button" @click="menu = !menu"
                    class="flex items-center gap-2 rounded-lg border border-slate-200 py-1.5 pe-3 ps-1.5 text-sm font-semibold transition hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                <span class="flex h-8 w-8 items-center justify-center rounded-md bg-brand-600 text-xs font-black text-white">
                    {{ substr(auth()->user()->name ?? 'ک', 0, 1) }}
                </span>
                <span class="hidden sm:inline">{{ auth()->user()->name ?? 'کاربر' }}</span>
                <i class="fa-solid fa-chevron-down text-[10px] text-slate-400"></i>
            </button>

            <div x-show="menu" @click.outside="menu = false" x-cloak
                 x-transition.origin.top.start
                 class="absolute start-0 mt-2 w-48 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="border-b border-slate-100 px-4 py-2 text-[11px] text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    {{ auth()->user()->email ?? '' }}
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="flex w-full items-center gap-2 px-4 py-2.5 text-start text-sm font-semibold text-rose-600 transition hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">
                        <i class="fa-solid fa-right-from-bracket w-4"></i>
                        خروج از حساب
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
