@props(['icon' => 'folder', 'title' => 'Nothing here yet', 'message' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-4 py-12 text-center']) }}>
    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-slate-100 to-slate-50 text-slate-400 ring-1 ring-slate-100 dark:from-white/10 dark:to-white/5 dark:text-slate-500 dark:ring-white/10">
        <x-icon :name="$icon" class="h-7 w-7" />
    </span>
    <p class="mt-4 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $title }}</p>
    @if ($message)
        <p class="mt-1 text-sm text-slate-400">{{ $message }}</p>
    @endif
    @if (trim($slot))
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
