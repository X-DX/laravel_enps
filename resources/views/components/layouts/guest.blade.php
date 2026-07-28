<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'eNPS — Sign in' }}</title>
    <x-theme.init />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <div class="relative flex min-h-full items-center justify-center overflow-hidden px-4 py-12">
        {{-- Theme toggle --}}
        <div class="absolute right-4 top-4 z-10">
            <x-theme.toggle />
        </div>

        {{-- Decorative gradient backdrop --}}
        <div class="pointer-events-none absolute inset-0 -z-10">
            <div class="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-indigo-500/20 blur-3xl dark:bg-indigo-600/30"></div>
            <div class="absolute -right-32 top-1/3 h-96 w-96 rounded-full bg-sky-400/20 blur-3xl dark:bg-sky-500/20"></div>
            <div class="absolute bottom-0 left-1/4 h-80 w-80 rounded-full bg-violet-500/15 blur-3xl dark:bg-violet-600/20"></div>
        </div>

        {{ $slot }}
    </div>
</body>
</html>
