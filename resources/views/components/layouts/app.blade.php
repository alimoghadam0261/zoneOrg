<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@isset($title){{ $title }} — @endif{{ config('app.name') }}</title>

    <script>
        (function () {
            var stored = localStorage.getItem('theme');
            var dark = stored ? stored === 'dark'
                : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/css/map.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-surface-100 font-sans text-slate-800 dark:bg-surface-950 dark:text-slate-200"
      x-data="{
          dark: document.documentElement.classList.contains('dark'),
          open: window.innerWidth >= 1024,
          toggleTheme() {
              this.dark = !this.dark;
              document.documentElement.classList.toggle('dark', this.dark);
              localStorage.setItem('theme', this.dark ? 'dark' : 'light');
          }
      }">

    <div class="flex min-h-screen">
        <x-sidebar />

        <div class="flex min-w-0 flex-1 flex-col">
            <x-topbar />

            <main class="flex-1 p-3 lg:p-5">
                {{ $slot }}
            </main>
        </div>
    </div>

    <x-toast />

    @livewireScripts
    @stack('scripts')
</body>
</html>
