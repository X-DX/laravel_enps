<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'eNPS' }}</title>
    <x-theme.init />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-full bg-slate-100 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <div
        x-data="{
            collapsed: JSON.parse(localStorage.getItem('sb.collapsed') ?? 'false'),
            mobileOpen: false,
        }"
        x-init="$watch('collapsed', v => localStorage.setItem('sb.collapsed', JSON.stringify(v)))"
        class="relative flex min-h-screen">

        {{-- vibrant page backdrop --}}
        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
            <div class="absolute -top-40 -right-32 h-96 w-96 rounded-full bg-indigo-400/20 blur-3xl dark:bg-indigo-600/10"></div>
            <div class="absolute top-1/3 -left-40 h-[28rem] w-[28rem] rounded-full bg-sky-400/20 blur-3xl dark:bg-sky-600/10"></div>
            <div class="absolute -bottom-40 right-1/4 h-96 w-96 rounded-full bg-violet-400/20 blur-3xl dark:bg-violet-600/10"></div>
        </div>

        <x-app.sidebar />

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/70 backdrop-blur-xl dark:border-white/10 dark:bg-slate-900/60">
                <div class="flex h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3">
                        <button @click="mobileOpen = true" type="button" aria-label="Open menu"
                            class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 lg:hidden dark:text-slate-300 dark:hover:bg-white/5 dark:hover:text-white">
                            <x-icon name="bars-3" class="h-6 w-6" />
                        </button>
                        <a href="{{ route('home') }}" class="flex items-center gap-2 lg:hidden">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-sky-500 text-xs font-bold text-white">eN</span>
                            <span class="font-display font-bold">eNPS</span>
                        </a>
                    </div>

                    <div class="flex items-center gap-2 sm:gap-3">
                        <x-theme.toggle />

                        @auth
                            <div class="hidden text-right sm:block">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ auth()->user()->username }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->user_id }}</p>
                            </div>
                            <span class="hidden h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-500 text-sm font-semibold text-white shadow-md shadow-indigo-500/30 sm:flex">
                                {{ strtoupper(substr(auth()->user()->username, 0, 1)) }}
                            </span>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:border-rose-300 hover:bg-rose-50 hover:text-rose-600 dark:border-white/10 dark:text-slate-300 dark:hover:border-rose-500/30 dark:hover:bg-rose-500/10 dark:hover:text-rose-300">
                                    Sign out
                                </button>
                            </form>
                        @endauth
                    </div>
                </div>
            </header>

            <main class="flex-1 px-4 py-8 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-7xl animate-fade-in-up">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
</body>
</html>
