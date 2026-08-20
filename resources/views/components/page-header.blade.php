@props(['title', 'subtitle' => null, 'crumbs' => []])
<div class="mb-6">
    @if (! empty($crumbs))
        <nav class="mb-2 flex flex-wrap items-center gap-1.5 text-xs text-slate-400">
            <a href="{{ route('dashboard') }}" wire:navigate class="transition hover:text-indigo-500">Home</a>
            @foreach ($crumbs as $label => $url)
                <x-icon name="chevron-right" class="h-3 w-3" />
                @if ($url)
                    <a href="{{ $url }}" wire:navigate class="transition hover:text-indigo-500">{{ $label }}</a>
                @else
                    <span>{{ $label }}</span>
                @endif
            @endforeach
            <x-icon name="chevron-right" class="h-3 w-3" />
            <span class="font-medium text-slate-600 dark:text-slate-300">{{ $title }}</span>
        </nav>
    @endif
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <h1 class="font-display text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
</div>
