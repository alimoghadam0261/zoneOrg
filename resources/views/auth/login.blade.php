<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ورود — {{ config('app.name') }}</title>

    <script>
        (function () {
            var stored = localStorage.getItem('theme');
            var dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-surface-100 p-4 font-sans text-slate-800 dark:bg-surface-950 dark:text-slate-200">

    <div class="w-full max-w-md">
        <div class="mb-6 flex items-center justify-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-xl text-white shadow-lg shadow-brand-600/30">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <div>
                <div class="text-xl font-black leading-tight">ZONE</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">کنترل دسترسی و محدوده جغرافیایی</div>
            </div>
        </div>

        <div class="card p-6 sm:p-8">
            <h1 class="mb-1 text-lg font-extrabold">ورود به سامانه</h1>
            <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
                برای مشاهده داشبورد زنده امنیت وارد شوید.
            </p>

            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 dark:border-rose-500/30 dark:bg-rose-950 dark:text-rose-300">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="label" for="email">ایمیل</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                           class="input" placeholder="admin@zone.local" dir="ltr">
                </div>

                <div>
                    <label class="label" for="password">گذرواژه</label>
                    <input id="password" name="password" type="password" required
                           class="input" placeholder="••••••••" dir="ltr">
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    مرا به خاطر بسپار
                </label>

                <button type="submit" class="btn-primary w-full py-2.5">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    ورود
                </button>
            </form>

            <div class="mt-6 rounded-lg border border-dashed border-slate-200 p-3 text-[11px] leading-6 text-slate-500 dark:border-slate-700 dark:text-slate-400">
                <b>کاربر پیش‌فرض نمونه:</b>
                <span dir="ltr" class="font-mono">admin@zone.local</span> / <span dir="ltr" class="font-mono">password</span>
                <br>پس از اجرای <span dir="ltr" class="font-mono">php artisan db:seed</span>
            </div>
        </div>

        <p class="mt-6 text-center text-[11px] text-slate-400">
            شرکت تعمیرات نیروگاهی ایران — سامانه ZONE
        </p>
    </div>

</body>
</html>
