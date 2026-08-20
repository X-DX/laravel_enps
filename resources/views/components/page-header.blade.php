@props(['title', 'subtitle' => null, 'crumbs' => []])
<div class="mb-6">
    @if (! empty($crumbs))
        <x-breadcrumbs :crumbs="$crumbs" :current="$title" class="mb-2" />
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
