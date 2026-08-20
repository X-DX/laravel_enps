@php($user = auth()->user())

{{-- Mobile backdrop (drawer overlay) --}}
<div x-show="mobileOpen" x-cloak @click="mobileOpen = false" x-transition.opacity
    class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm lg:hidden"></div>

<aside x-cloak
    :class="{
        'lg:w-[74px]': collapsed,
        'translate-x-0': mobileOpen,
        '-translate-x-full lg:translate-x-0': !mobileOpen,
    }"
    class="fixed inset-y-0 left-0 z-40 flex w-72 shrink-0 flex-col overflow-hidden border-r border-slate-200/70 bg-white/95 backdrop-blur-xl transition-[width,transform] duration-300 ease-[cubic-bezier(.4,0,.2,1)] lg:sticky lg:top-0 lg:z-auto lg:h-screen lg:translate-x-0 dark:border-white/10 dark:bg-slate-950/85">

    {{-- soft gradient glow behind the brand --}}
    <div class="pointer-events-none absolute -top-24 left-1/2 h-56 w-56 -translate-x-1/2 rounded-full bg-indigo-500/20 blur-3xl dark:bg-indigo-500/15"></div>

    {{-- Brand + collapse toggle --}}
    <div class="relative z-10 flex h-16 shrink-0 items-center gap-2.5 border-b border-slate-200/70 px-4 dark:border-white/10">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5 overflow-hidden">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 via-violet-500 to-sky-500 text-sm font-bold text-white shadow-lg shadow-indigo-500/40">eN</span>
            <span class="font-display text-lg font-bold tracking-tight text-slate-900 dark:text-white" :class="collapsed && 'lg:hidden'">eNPS</span>
        </a>
        <button @click="collapsed = !collapsed" type="button" aria-label="Toggle sidebar"
            class="ml-auto hidden h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-indigo-600 lg:flex dark:hover:bg-white/5 dark:hover:text-indigo-300"
            :class="collapsed && 'lg:mx-auto'">
            <x-icon name="chevrons-left" class="h-5 w-5 transition-transform duration-300" ::class="collapsed && 'rotate-180'" />
        </button>
    </div>

    <nav class="relative z-10 flex-1 space-y-1 overflow-y-auto overflow-x-hidden p-3">
        {{-- Dashboard --}}
        <a href="{{ route('dashboard') }}" @click="mobileOpen = false" title="Dashboard"
            :class="collapsed && 'lg:justify-center'"
            @class([
                'group flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium transition',
                'bg-gradient-to-r from-indigo-500 to-violet-500 font-semibold text-white shadow-md shadow-indigo-500/30' => request()->routeIs('dashboard'),
                'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5' => ! request()->routeIs('dashboard'),
            ])>
            <x-icon name="grid" class="h-5 w-5 shrink-0" />
            <span :class="collapsed && 'lg:hidden'">Dashboard</span>
        </a>

        @if ($user)
            {{-- Full tree (hidden on desktop when collapsed) --}}
            <div class="space-y-1" :class="collapsed && 'lg:hidden'">
                @foreach (app(\App\Support\Navigation\SidebarMenu::class)->forUser($user) as $section)
                    <details class="group/sec rounded-xl open:bg-slate-50/70 dark:open:bg-white/[0.03]" @if ($section['open']) open @endif>
                        <summary class="flex cursor-pointer select-none items-center justify-between gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-slate-500 transition hover:bg-slate-100 group-open/sec:text-indigo-600 dark:text-slate-400 dark:hover:bg-white/5 dark:group-open/sec:text-indigo-300">
                            <span class="flex items-center gap-2.5 truncate">
                                <x-icon name="{{ $section['icon'] }}" class="h-5 w-5 shrink-0 text-slate-400 transition group-open/sec:text-indigo-500 dark:group-open/sec:text-indigo-400" />
                                <span class="truncate uppercase tracking-wide text-xs">{{ $section['title'] }}</span>
                            </span>
                            <span class="flex shrink-0 items-center gap-1.5">
                                @if ($section['badge'] ?? 0)
                                    <span class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-bold text-indigo-600 group-open/sec:bg-indigo-500 group-open/sec:text-white dark:bg-indigo-500/20 dark:text-indigo-300">{{ $section['badge'] }}</span>
                                @endif
                                <x-icon name="chevron-right" class="h-4 w-4 text-slate-400 transition group-open/sec:rotate-90 group-open/sec:text-indigo-500 dark:group-open/sec:text-indigo-400" />
                            </span>
                        </summary>

                        <div class="mt-0.5 space-y-0.5 pb-1 pl-1.5">
                            @foreach ($section['subs'] as $sub)
                                <details class="group/sub" @if ($sub['open']) open @endif>
                                    <summary class="flex cursor-pointer select-none items-center justify-between gap-2 rounded-lg px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 group-open/sub:text-indigo-600 dark:text-slate-300 dark:hover:bg-white/5 dark:group-open/sub:text-indigo-300">
                                        <span class="flex items-center gap-2 truncate">
                                            <x-icon name="{{ $sub['icon'] }}" class="h-4 w-4 shrink-0 opacity-70" />
                                            <span class="truncate">{{ $sub['title'] }}</span>
                                        </span>
                                        <span class="flex shrink-0 items-center gap-1.5">
                                            @if ($sub['badge'] ?? 0)
                                                <span class="inline-flex min-w-[1.125rem] items-center justify-center rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-bold text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300">{{ $sub['badge'] }}</span>
                                            @endif
                                            <x-icon name="chevron-right" class="h-3.5 w-3.5 text-slate-400 transition group-open/sub:rotate-90 group-open/sub:text-indigo-500 dark:group-open/sub:text-indigo-400" />
                                        </span>
                                    </summary>

                                    <div class="ml-4 mt-0.5 space-y-0.5 border-l border-slate-200 pl-2 dark:border-white/10">
                                        @foreach ($sub['items'] as $item)
                                            <a href="{{ $item['url'] ?? '#' }}" @click="mobileOpen = false" title="{{ $item['label'] }}"
                                                @class([
                                                    'group/item flex items-center gap-2.5 truncate rounded-lg px-3 py-1.5 text-sm transition',
                                                    'bg-gradient-to-r from-indigo-500 to-violet-500 font-semibold text-white shadow-sm shadow-indigo-500/30' => $item['active'],
                                                    'text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white' => ! $item['active'],
                                                ])>
                                                <x-icon name="{{ $item['icon'] }}" @class([
                                                    'h-4 w-4 shrink-0',
                                                    'text-slate-400 group-hover/item:text-indigo-500 dark:group-hover/item:text-indigo-400' => ! $item['active'],
                                                ]) />
                                                <span class="truncate">{{ $item['label'] }}</span>
                                                @if ($item['badge'] ?? 0)
                                                    <span @class([
                                                        'ml-auto inline-flex min-w-[1.25rem] shrink-0 items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-bold',
                                                        'bg-white/25 text-white' => $item['active'],
                                                        'bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300' => ! $item['active'],
                                                    ])>{{ $item['badge'] }}</span>
                                                @endif
                                            </a>
                                        @endforeach
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </div>

            {{-- Icon rail (desktop, collapsed only) — click any icon to expand --}}
            <div class="hidden space-y-1" :class="collapsed && 'lg:block'">
                @foreach (app(\App\Support\Navigation\SidebarMenu::class)->forUser($user) as $section)
                    <button @click="collapsed = false" type="button" title="{{ $section['title'] }}"
                        @class([
                            'relative flex w-full items-center justify-center rounded-xl p-2.5 transition',
                            'text-indigo-600 bg-indigo-50 dark:bg-indigo-500/15 dark:text-indigo-300' => $section['open'],
                            'text-slate-500 hover:bg-slate-100 hover:text-indigo-600 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-indigo-300' => ! $section['open'],
                        ])>
                        <x-icon name="{{ $section['icon'] }}" class="h-5 w-5" />
                        @if ($section['badge'] ?? 0)<span class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-rose-500 ring-2 ring-white dark:ring-slate-950"></span>@endif
                    </button>
                @endforeach
            </div>
        @endif
    </nav>

    <div class="relative z-10 border-t border-slate-200/70 p-3 dark:border-white/10" :class="collapsed && 'lg:hidden'">
        <p class="px-3 text-xs text-slate-400">Modules light up as they're built.</p>
    </div>
</aside>
