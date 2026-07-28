<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'eNPS' }}</title>
    <x-theme.init />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-100 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <div class="flex min-h-screen">
        <x-app.sidebar />

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/80 backdrop-blur dark:border-white/10 dark:bg-slate-900/80">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                    {{-- Brand shown only on mobile (the sidebar carries it on desktop) --}}
                    <a href="{{ route('home') }}" class="flex items-center gap-2.5 lg:hidden">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-sky-500 text-xs font-bold text-white">eN</span>
                        <span class="font-semibold">eNPS</span>
                    </a>
                    <div class="hidden lg:block"></div>

                    <div class="flex items-center gap-3">
                        <x-theme.toggle />

                        @auth
                            <div class="hidden text-right sm:block">
                                <p class="text-sm font-medium text-slate-900 dark:text-white">{{ auth()->user()->username }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->user_id }}</p>
                            </div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 hover:text-slate-900 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5 dark:hover:text-white">
                                    Sign out
                                </button>
                            </form>
                        @endauth
                    </div>
                </div>
            </header>

            <main class="flex-1 px-4 py-8 sm:px-6 lg:px-8">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
