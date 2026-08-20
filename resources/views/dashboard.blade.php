<div>
    @php
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

        $cards = [
            ['label' => 'Total Subscribers', 'value' => $subscribers, 'icon' => 'users', 'grad' => 'from-indigo-500 to-violet-500'],
            ['label' => 'Finalized Accounts', 'value' => $finalized, 'icon' => 'check-circle', 'grad' => 'from-emerald-500 to-teal-500'],
            ['label' => 'Pending Accounts', 'value' => $pending, 'icon' => 'clock', 'grad' => 'from-amber-500 to-orange-500'],
            ['label' => 'PRANs Assigned', 'value' => $prans, 'icon' => 'identification', 'grad' => 'from-sky-500 to-cyan-500'],
            ['label' => 'Pending PRANs', 'value' => $pendingPrans, 'icon' => 'clock', 'grad' => 'from-violet-500 to-fuchsia-500'],
            ['label' => 'Closed Accounts', 'value' => $closed, 'icon' => 'x-circle', 'grad' => 'from-rose-500 to-pink-500'],
        ];
    @endphp

    {{-- Hero --}}
    <div class="relative mb-8 overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 via-violet-600 to-sky-600 p-6 text-white shadow-xl shadow-indigo-500/25 sm:p-8">
        <div class="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-white/10 blur-2xl"></div>
        <div class="pointer-events-none absolute -bottom-24 left-1/4 h-64 w-64 rounded-full bg-sky-300/20 blur-3xl"></div>
        <div class="relative flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="flex items-center gap-2 text-sm font-medium text-indigo-100">
                    <x-icon name="sparkles" class="h-4 w-4" /> {{ now()->format('l, d F Y') }}
                </p>
                <h1 class="mt-2 font-display text-3xl font-bold tracking-tight sm:text-4xl">{{ $greeting }}, {{ auth()->user()->username }}</h1>
                <p class="mt-2 max-w-xl text-sm text-indigo-100/90">Here's what's happening across the National Pension System back office today.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('entrysection.issue_account')
                    <a href="{{ route('accounts.issue') }}" wire:navigate
                        class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-indigo-600 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl">
                        <x-icon name="user-plus" class="h-4 w-4" /> New Account
                    </a>
                @endcan
                @can('entrysection.assign_pran_against_accounts')
                    <a href="{{ route('accounts.pran') }}" wire:navigate
                        class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-inset ring-white/30 backdrop-blur transition hover:bg-white/25">
                        <x-icon name="identification" class="h-4 w-4" /> Assign PRAN
                    </a>
                @endcan
            </div>
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($cards as $i => $c)
            <div class="group relative overflow-hidden rounded-2xl border border-slate-200/70 bg-white/80 p-5 shadow-sm backdrop-blur transition duration-300 animate-pop-in hover:-translate-y-1 hover:shadow-xl hover:shadow-indigo-500/10 dark:border-white/10 dark:bg-white/[0.04]"
                style="animation-delay: {{ $i * 60 }}ms">
                <div class="pointer-events-none absolute -right-6 -top-6 h-24 w-24 rounded-full bg-gradient-to-br {{ $c['grad'] }} opacity-10 blur-xl transition group-hover:opacity-25"></div>
                <div class="relative flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $c['label'] }}</p>
                        <p class="mt-2 font-display text-3xl font-bold tracking-tight text-slate-900 dark:text-white" x-data="countUp({{ $c['value'] }})" x-text="display">{{ number_format($c['value']) }}</p>
                    </div>
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br {{ $c['grad'] }} text-white shadow-lg">
                        <x-icon name="{{ $c['icon'] }}" class="h-6 w-6" />
                    </span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Charts row --}}
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Top departments --}}
        <div class="rounded-2xl border border-slate-200/70 bg-white/80 p-6 shadow-sm backdrop-blur lg:col-span-2 dark:border-white/10 dark:bg-white/[0.04]">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-display text-lg font-semibold text-slate-900 dark:text-white">Top Departments</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">By number of subscribers</p>
                </div>
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-500 dark:bg-indigo-500/15 dark:text-indigo-300"><x-icon name="chart-bar" class="h-5 w-5" /></span>
            </div>
            <div class="mt-5 space-y-4">
                @php $max = collect($topDepartments)->max('total') ?: 1; @endphp
                @forelse ($topDepartments as $i => $d)
                    <div>
                        <div class="mb-1.5 flex items-center justify-between text-sm">
                            <span class="truncate pr-3 font-medium text-slate-700 dark:text-slate-200">{{ $d['label'] }}</span>
                            <span class="shrink-0 font-semibold text-slate-900 dark:text-white">{{ number_format($d['total']) }}</span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-white/5">
                            <div class="h-full origin-left rounded-full bg-gradient-to-r from-indigo-500 via-violet-500 to-sky-500"
                                style="width: {{ max(4, round($d['total'] / $max * 100)) }}%; animation: count-bar .8s cubic-bezier(.2,.7,.2,1) both; animation-delay: {{ $i * 90 }}ms"></div>
                        </div>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-slate-400">No subscriber data yet.</p>
                @endforelse
            </div>
        </div>

        {{-- PRAN coverage + pension split --}}
        <div class="rounded-2xl border border-slate-200/70 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/[0.04]">
            <h2 class="font-display text-lg font-semibold text-slate-900 dark:text-white">PRAN Coverage</h2>
            <div class="mt-4 flex items-center gap-5">
                @php $circ = 2 * pi() * 42; @endphp
                <div class="relative h-28 w-28 shrink-0">
                    <svg class="h-28 w-28 -rotate-90" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="42" fill="none" stroke="currentColor" stroke-width="9" class="text-slate-100 dark:text-white/10" />
                        <circle cx="50" cy="50" r="42" fill="none" stroke="url(#pranGrad)" stroke-width="9" stroke-linecap="round"
                            stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $circ * (1 - $pranCoverage / 100) }}" />
                        <defs>
                            <linearGradient id="pranGrad" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0%" stop-color="#6366f1" />
                                <stop offset="100%" stop-color="#0ea5e9" />
                            </linearGradient>
                        </defs>
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="font-display text-2xl font-bold text-slate-900 dark:text-white">{{ $pranCoverage }}%</span>
                    </div>
                </div>
                <div class="min-w-0">
                    <p class="text-sm text-slate-500 dark:text-slate-400">of finalized accounts have a PRAN</p>
                    <p class="mt-2 text-sm text-slate-700 dark:text-slate-200"><span class="font-semibold">{{ number_format($prans) }}</span> assigned</p>
                    <p class="text-sm text-slate-700 dark:text-slate-200"><span class="font-semibold">{{ number_format($finalized) }}</span> finalized</p>
                </div>
            </div>

            @php $tot = max($nps + $ups, 1); @endphp
            <div class="mt-6 border-t border-slate-100 pt-5 dark:border-white/5">
                <p class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-200">Pension type (finalized)</p>
                <div class="flex h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-white/5">
                    <div class="bg-gradient-to-r from-indigo-500 to-violet-500" style="width: {{ round($nps / $tot * 100) }}%"></div>
                    <div class="bg-gradient-to-r from-sky-400 to-cyan-400" style="width: {{ round($ups / $tot * 100) }}%"></div>
                </div>
                <div class="mt-2 flex items-center justify-between text-xs">
                    <span class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300"><span class="h-2 w-2 rounded-full bg-indigo-500"></span> NPS · {{ number_format($nps) }}</span>
                    <span class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300">UPS · {{ number_format($ups) }} <span class="h-2 w-2 rounded-full bg-sky-400"></span></span>
                </div>
            </div>
        </div>
    </div>

    {{-- Master data + quick actions --}}
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Master data mini-stats --}}
        @php
            $masters = [
                ['label' => 'Departments', 'value' => $departments, 'icon' => 'building-office', 'color' => 'text-indigo-500 bg-indigo-50 dark:bg-indigo-500/15 dark:text-indigo-300'],
                ['label' => 'DDOs', 'value' => $ddos, 'icon' => 'building-library', 'color' => 'text-sky-500 bg-sky-50 dark:bg-sky-500/15 dark:text-sky-300'],
                ['label' => 'Treasuries', 'value' => $treasuries, 'icon' => 'building-library', 'color' => 'text-emerald-500 bg-emerald-50 dark:bg-emerald-500/15 dark:text-emerald-300'],
            ];
        @endphp
        <div class="grid grid-cols-3 gap-4 lg:col-span-2">
            @foreach ($masters as $m)
                <div class="flex flex-col items-center justify-center rounded-2xl border border-slate-200/70 bg-white/80 p-5 text-center shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/[0.04]">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $m['color'] }}"><x-icon name="{{ $m['icon'] }}" class="h-5 w-5" /></span>
                    <p class="mt-3 font-display text-2xl font-bold text-slate-900 dark:text-white" x-data="countUp({{ $m['value'] }})" x-text="display">{{ number_format($m['value']) }}</p>
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $m['label'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Quick actions --}}
        <div class="rounded-2xl border border-slate-200/70 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/[0.04]">
            <div class="flex items-center gap-2">
                <x-icon name="bolt" class="h-5 w-5 text-amber-500" />
                <h2 class="font-display text-lg font-semibold text-slate-900 dark:text-white">Quick actions</h2>
            </div>
            <div class="mt-4 space-y-2">
                @php
                    $actions = [
                        ['can' => 'entrysection.issue_account', 'route' => 'accounts.issue', 'label' => 'Issue a new account', 'icon' => 'user-plus'],
                        ['can' => 'entrysection.assign_pran_against_accounts', 'route' => 'accounts.pran', 'label' => 'Assign a PRAN', 'icon' => 'identification'],
                        ['can' => 'entrysection.view_all_accounts', 'route' => 'accounts.index', 'label' => 'Browse all accounts', 'icon' => 'list-bullet'],
                        ['can' => 'entrysection.close_account', 'route' => 'accounts.close', 'label' => 'Close an account', 'icon' => 'x-circle'],
                    ];
                @endphp
                @foreach ($actions as $a)
                    @can($a['can'])
                        <a href="{{ route($a['route']) }}" wire:navigate
                            class="group flex items-center gap-3 rounded-xl border border-slate-200/70 px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:border-indigo-300 hover:bg-indigo-50/60 hover:text-indigo-700 dark:border-white/10 dark:text-slate-200 dark:hover:border-indigo-500/30 dark:hover:bg-indigo-500/10 dark:hover:text-indigo-300">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-500 transition group-hover:bg-white group-hover:text-indigo-600 dark:bg-white/5 dark:text-slate-300"><x-icon name="{{ $a['icon'] }}" class="h-4 w-4" /></span>
                            <span class="flex-1">{{ $a['label'] }}</span>
                            <x-icon name="chevron-right" class="h-4 w-4 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-indigo-500" />
                        </a>
                    @endcan
                @endforeach
            </div>
        </div>
    </div>
</div>
