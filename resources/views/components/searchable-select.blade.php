@props([
    'model', // the Livewire property to bind to
    'options' => [], // list of ['value' => ..., 'label' => ...]
    'placeholder' => 'Select…',
    'disabled' => false,
    'live' => false, // true = update the server immediately (needed for cascades)
])

@php
    // Normalise every option to a plain {value, label} with string values.
    $items = collect($options)
        ->map(function ($o) {
            $o = (array) $o;
            return ['value' => (string) ($o['value'] ?? ''), 'label' => (string) ($o['label'] ?? '')];
        })
        ->values();
@endphp

<div {{ $attributes->only('wire:key') }} x-data="{
    open: false,
    search: '',
    items: @js($items),
    selected: $wire.entangle('{{ $model }}') {{ $live ? '.live' : '' }},
    get selectedLabel() {
        const f = this.items.find(i => i.value === String(this.selected ?? ''));
        return f ? f.label : '';
    },
    get filtered() {
        const s = this.search.toLowerCase().trim();
        return s === '' ? this.items : this.items.filter(i => i.label.toLowerCase().includes(s));
    },
    choose(v) {
        this.selected = v;
        this.open = false;
        this.search = '';
    },
}" @click.outside="open = false"
    x-on:keydown.escape="open = false" class="relative">
    <button type="button" @if ($disabled) disabled @endif
        @click="open = !open; if (open) $nextTick(() => $refs.search?.focus())"
        class="flex w-full items-center justify-between gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-left text-sm text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-white/10 dark:bg-white/5 dark:text-white dark:disabled:bg-white/5">
        <span class="truncate" x-text="selectedLabel || '{{ $placeholder }}'"
            :class="selectedLabel ? '' : 'text-slate-400'"></span>
        <svg class="h-4 w-4 shrink-0 text-slate-400 transition" :class="open ? 'rotate-180' : ''" fill="none"
            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
    </button>

    <div x-show="open" x-cloak x-transition.origin.top
        class="absolute z-30 mt-1 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg dark:border-white/10 dark:bg-slate-800">
        <div class="border-b border-slate-100 p-2 dark:border-white/10">
            <input x-model="search" x-ref="search" type="text" placeholder="Search…"
                class="block w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
        </div>
        <ul class="max-h-60 overflow-y-auto py-1 text-sm">
            <template x-for="item in filtered" :key="item.value">
                <li @click="choose(item.value)" x-text="item.label"
                    :class="String(selected ?? '') === item.value ?
                        'bg-indigo-600 text-white' :
                        'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-white/5'"
                    class="cursor-pointer truncate px-3 py-2"></li>
            </template>
            <li x-show="filtered.length === 0" class="px-3 py-2 text-slate-400">No matches</li>
        </ul>
    </div>
</div>
