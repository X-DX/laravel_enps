<div
    x-data="{
        open: false,
        sel: 0,
        items() { return Array.from($root.querySelectorAll('[data-cmd]')); },
        move(d) {
            const list = this.items();
            if (! list.length) { this.sel = 0; return; }
            this.sel = (this.sel + d + list.length) % list.length;
            this.$nextTick(() => list[this.sel]?.scrollIntoView({ block: 'nearest' }));
        },
        go() { this.items()[this.sel]?.click(); },
    }"
    @keydown.window.meta.k.prevent="open = true; sel = 0; $nextTick(() => $refs.input?.focus())"
    @keydown.window.ctrl.k.prevent="open = true; sel = 0; $nextTick(() => $refs.input?.focus())"
    @command-palette-open.window="open = true; sel = 0; $nextTick(() => $refs.input?.focus())"
    @keydown.escape.window="open = false"
    x-on:livewire:navigated.window="open = false">

    <div x-show="open" x-cloak class="fixed inset-0 z-[60]" role="dialog" aria-modal="true">
        <div x-show="open" x-transition.opacity @click="open = false" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>

        <div x-show="open" x-transition
            class="absolute left-1/2 top-24 w-[92%] max-w-xl -translate-x-1/2 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-white/10 dark:bg-slate-900">
            {{-- search input --}}
            <div class="flex items-center gap-3 border-b border-slate-100 px-4 dark:border-white/10">
                <x-icon name="magnifying-glass" class="h-5 w-5 shrink-0 text-slate-400" />
                <input x-ref="input" wire:model.live.debounce.200ms="query" @input="sel = 0"
                    @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="go()"
                    type="text" placeholder="Search screens or accounts…" autocomplete="off"
                    class="w-full border-0 bg-transparent py-3.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0 dark:text-white">
                <span wire:loading wire:target="query" class="h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-slate-200 border-t-indigo-500"></span>
                <kbd class="hidden shrink-0 rounded border border-slate-200 px-1.5 py-0.5 text-[10px] font-medium text-slate-400 sm:block dark:border-white/10">ESC</kbd>
            </div>

            {{-- results --}}
            <div class="max-h-80 overflow-y-auto p-2">
                @php $i = 0; @endphp

                @if ($navResults->isNotEmpty())
                    <p class="px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Go to</p>
                    @foreach ($navResults as $n)
                        <a data-cmd href="{{ $n['url'] }}" wire:navigate @click="open = false" @mouseenter="sel = {{ $i }}"
                            :class="sel === {{ $i }} ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-200' : 'text-slate-700 dark:text-slate-200'"
                            class="flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm">
                            <x-icon name="{{ $n['icon'] }}" class="h-4 w-4 shrink-0 text-slate-400" />
                            <span class="flex-1 truncate">{{ $n['label'] }}</span>
                            <x-icon name="chevron-right" class="h-3.5 w-3.5 shrink-0 opacity-0" ::class="sel === {{ $i }} && 'opacity-60'" />
                        </a>
                        @php $i++; @endphp
                    @endforeach
                @endif

                @if ($accounts->isNotEmpty())
                    <p class="mt-1 px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Accounts</p>
                    @foreach ($accounts as $a)
                        <a data-cmd href="{{ $a['url'] }}" wire:navigate @click="open = false" @mouseenter="sel = {{ $i }}"
                            :class="sel === {{ $i }} ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-200' : 'text-slate-700 dark:text-slate-200'"
                            class="flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm">
                            <x-icon name="users" class="h-4 w-4 shrink-0 text-slate-400" />
                            <span class="flex-1 truncate">{{ $a['label'] }}</span>
                            <span class="shrink-0 text-xs text-slate-400">{{ $a['meta'] }}</span>
                        </a>
                        @php $i++; @endphp
                    @endforeach
                @endif

                @if ($navResults->isEmpty() && $accounts->isEmpty())
                    <p class="px-3 py-8 text-center text-sm text-slate-400">No matches for &ldquo;{{ $query }}&rdquo;.</p>
                @endif
            </div>

            <div class="flex items-center gap-4 border-t border-slate-100 px-4 py-2 text-[11px] text-slate-400 dark:border-white/10">
                <span>&uarr;&darr; navigate</span>
                <span>&crarr; open</span>
                <span class="ml-auto hidden sm:block">Tip: press ⌘K anywhere</span>
            </div>
        </div>
    </div>
</div>
