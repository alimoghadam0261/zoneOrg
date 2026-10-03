@props([])

<div class="pointer-events-none fixed bottom-5 start-5 z-[9999] flex w-80 max-w-[calc(100vw-2.5rem)] flex-col gap-2"
     x-data="{ toasts: [] }"
     x-on:toast.window="
         const d = $event.detail || {};
         const id = Date.now() + Math.random();
         toasts.push({ id, title: d.title || 'اعلان', body: d.body || '', type: d.type || 'success' });
         setTimeout(() => { toasts = toasts.filter(t => t.id !== id) }, d.type === 'error' ? 6000 : 4000);
     "
     x-cloak>
    <template x-for="t in toasts" :key="t.id">
        <div class="pointer-events-auto flex items-start gap-3 rounded-xl border p-3 shadow-2xl animate-slide-in"
             :class="{
                 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-950 dark:text-emerald-200': t.type === 'success',
                 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-500/30 dark:bg-rose-950 dark:text-rose-200': t.type === 'error',
                 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-500/30 dark:bg-sky-950 dark:text-sky-200': t.type === 'info',
                 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-950 dark:text-amber-200': t.type === 'warning',
             }">
            <i class="mt-0.5"
               :class="{
                   'fa-solid fa-circle-check': t.type === 'success',
                   'fa-solid fa-circle-xmark': t.type === 'error',
                   'fa-solid fa-circle-info': t.type === 'info',
                   'fa-solid fa-triangle-exclamation': t.type === 'warning',
               }"></i>
            <div class="min-w-0">
                <div class="text-sm font-black" x-text="t.title"></div>
                <div class="text-xs opacity-80" x-text="t.body"></div>
            </div>
        </div>
    </template>
</div>
