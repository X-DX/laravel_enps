@props(['crumbs' => [], 'current' => null])
<nav {{ $attributes->merge(['class' => 'mb-2 flex flex-wrap items-center gap-1.5 text-xs text-slate-400']) }} aria-label="Breadcrumb">
    <a href="{{ route('dashboard') }}" wire:navigate class="transition hover:text-indigo-500">Home</a>
    @foreach ($crumbs as $label => $url)
        <x-icon name="chevron-right" class="h-3 w-3" />
        @if ($url)
            <a href="{{ $url }}" wire:navigate class="transition hover:text-indigo-500">{{ $label }}</a>
        @else
            <span>{{ $label }}</span>
        @endif
    @endforeach
    @if ($current)
        <x-icon name="chevron-right" class="h-3 w-3" />
        <span class="font-medium text-slate-600 dark:text-slate-300">{{ $current }}</span>
    @endif
</nav>
