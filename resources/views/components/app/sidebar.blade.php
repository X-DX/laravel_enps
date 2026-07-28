@php($user = auth()->user())
<aside class="hidden w-72 shrink-0 flex-col border-r border-slate-200 bg-white lg:flex dark:border-white/10 dark:bg-slate-900">
    {{-- Brand --}}
    <a href="{{ route('home') }}" class="flex h-16 shrink-0 items-center gap-2.5 border-b border-slate-200 px-5 dark:border-white/10">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-sky-500 text-sm font-bold text-white">eN</span>
        <span class="text-base font-semibold">eNPS</span>
    </a>

    <nav class="flex-1 space-y-0.5 overflow-y-auto p-3">
        {{-- Dashboard --}}
        <a href="{{ route('dashboard') }}"
            @class([
                'flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium',
                'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' => request()->routeIs('dashboard'),
                'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5' => ! request()->routeIs('dashboard'),
            ])>
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" /></svg>
            Dashboard
        </a>

        @if ($user)
            @foreach (app(\App\Support\Navigation\SidebarMenu::class)->forUser($user) as $section)
                {{-- Section — open state gets an indigo tint + left accent so it's obvious which is expanded --}}
                <details class="group/sec rounded-lg open:bg-slate-50 dark:open:bg-white/5" @if ($section['open']) open @endif>
                    <summary class="flex cursor-pointer select-none items-center justify-between rounded-lg border-l-2 border-transparent px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 transition hover:bg-slate-100 group-open/sec:border-indigo-500 group-open/sec:text-indigo-700 dark:text-slate-400 dark:hover:bg-white/5 dark:group-open/sec:border-indigo-400 dark:group-open/sec:text-indigo-300">
                        <span>{{ $section['title'] }}</span>
                        <svg class="h-4 w-4 shrink-0 transition group-open/sec:rotate-90 group-open/sec:text-indigo-500 dark:group-open/sec:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                    </summary>

                    <div class="mt-0.5 space-y-0.5 pb-1 pl-1.5">
                        @foreach ($section['subs'] as $sub)
                            {{-- Sub-section — open state turns the header indigo too --}}
                            <details class="group/sub" @if ($sub['open']) open @endif>
                                <summary class="flex cursor-pointer select-none items-center justify-between rounded-lg px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100 group-open/sub:bg-indigo-50 group-open/sub:text-indigo-700 dark:text-slate-300 dark:hover:bg-white/5 dark:group-open/sub:bg-indigo-500/10 dark:group-open/sub:text-indigo-300">
                                    <span class="truncate">{{ $sub['title'] }}</span>
                                    <svg class="h-4 w-4 shrink-0 transition group-open/sub:rotate-90 group-open/sub:text-indigo-500 dark:group-open/sub:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                </summary>

                                <div class="ml-3 mt-0.5 space-y-0.5 border-l border-slate-200 pl-2 dark:border-white/10">
                                    @foreach ($sub['items'] as $item)
                                        <a href="{{ $item['url'] ?? '#' }}" title="{{ $item['label'] }}"
                                            @class([
                                                'block truncate rounded-lg px-3 py-1.5 text-sm transition',
                                                'bg-indigo-50 font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' => $item['active'],
                                                'text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white' => ! $item['active'],
                                            ])>
                                            {{ $item['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </details>
                        @endforeach
                    </div>
                </details>
            @endforeach
        @endif
    </nav>

    <div class="border-t border-slate-200 p-3 dark:border-white/10">
        <p class="px-3 text-xs text-slate-400">Modules light up as they're built.</p>
    </div>
</aside>
