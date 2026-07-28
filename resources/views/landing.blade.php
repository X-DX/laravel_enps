<x-layouts.marketing>
    {{-- ============================ NAV ============================ --}}
    <header class="fixed inset-x-0 top-0 z-50 border-b border-slate-200/60 bg-white/70 backdrop-blur-xl dark:border-white/10 dark:bg-slate-950/60">
        <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <a href="#top" class="flex items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-sky-500 text-sm font-bold text-white shadow-lg shadow-indigo-500/30">eN</span>
                <span class="text-base font-semibold tracking-tight">eNPS</span>
            </a>

            <div class="hidden items-center gap-8 text-sm font-medium text-slate-600 md:flex dark:text-slate-300">
                <a href="#features" class="transition hover:text-slate-900 dark:hover:text-white">Features</a>
                <a href="#security" class="transition hover:text-slate-900 dark:hover:text-white">Security</a>
                <a href="#about" class="transition hover:text-slate-900 dark:hover:text-white">About</a>
            </div>

            <div class="flex items-center gap-3">
                {{-- Theme toggle --}}
                <button id="theme-toggle" type="button" aria-label="Toggle dark mode"
                    class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-100 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">
                    <svg class="block h-5 w-5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" /></svg>
                    <svg class="hidden h-5 w-5 dark:block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" /></svg>
                </button>

                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg bg-gradient-to-r from-indigo-500 to-sky-500 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:from-indigo-400 hover:to-sky-400">Sign in</a>
                @endauth
            </div>
        </nav>
    </header>

    {{-- ============================ HERO ============================ --}}
    <section id="top" class="relative overflow-hidden pt-16">
        {{-- Backdrop --}}
        <div class="pointer-events-none absolute inset-0 -z-10">
            <div class="grid-backdrop absolute inset-0"></div>
            <div data-parallax data-depth="26" class="absolute -left-24 top-24 h-80 w-80">
                <div class="anim-float h-full w-full rounded-full bg-indigo-500/30 blur-3xl dark:bg-indigo-500/25"></div>
            </div>
            <div data-parallax data-depth="44" class="absolute -right-16 top-40 h-96 w-96">
                <div class="anim-float-2 h-full w-full rounded-full bg-sky-400/25 blur-3xl dark:bg-sky-500/20"></div>
            </div>
            <div data-parallax data-depth="18" class="absolute bottom-0 left-1/3 h-72 w-72">
                <div class="anim-float h-full w-full rounded-full bg-violet-500/20 blur-3xl"></div>
            </div>
        </div>

        <div class="mx-auto grid max-w-7xl items-center gap-14 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:py-28 lg:px-8">
            {{-- Copy --}}
            <div>
                <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/60 px-3 py-1 text-xs font-medium text-slate-600 backdrop-blur dark:border-white/10 dark:bg-white/5 dark:text-slate-300">
                    <span class="flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    Secure government pension platform
                </span>

                <h1 class="mt-6 text-4xl font-bold leading-[1.1] tracking-tight sm:text-5xl lg:text-6xl">
                    The modern home for the
                    <span class="gradient-text bg-gradient-to-r from-indigo-500 via-sky-500 to-violet-500 bg-clip-text text-transparent">National Pension System</span>
                </h1>

                <p class="mt-6 max-w-lg text-lg leading-relaxed text-slate-600 dark:text-slate-400">
                    Manage subscribers, PRAN allotment, contributions, interest and reporting —
                    end to end. Fast, auditable, and built for the people who keep pensions running.
                </p>

                <div class="mt-9 flex flex-wrap items-center gap-4">
                    @auth
                        <a href="{{ route('dashboard') }}" class="group inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-sky-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:from-indigo-400 hover:to-sky-400">
                            Go to dashboard
                            <svg class="h-4 w-4 transition group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="group inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-sky-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:from-indigo-400 hover:to-sky-400">
                            Sign in to continue
                            <svg class="h-4 w-4 transition group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                        </a>
                    @endauth
                    <a href="#features" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-6 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-white/15 dark:text-slate-200 dark:hover:bg-white/5">
                        Explore features
                    </a>
                </div>
            </div>

            {{-- Glass app-preview card (parallax) --}}
            <div data-parallax data-depth="-30" class="relative">
                <div class="rounded-3xl border border-slate-200/70 bg-white/70 p-2 shadow-2xl backdrop-blur-xl dark:border-white/10 dark:bg-white/5">
                    <div class="rounded-2xl bg-gradient-to-br from-slate-900 to-slate-800 p-6">
                        <div class="mb-5 flex items-center gap-1.5">
                            <span class="h-3 w-3 rounded-full bg-rose-400"></span>
                            <span class="h-3 w-3 rounded-full bg-amber-400"></span>
                            <span class="h-3 w-3 rounded-full bg-emerald-400"></span>
                        </div>
                        <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Contribution summary</p>
                        <div class="mt-4 grid grid-cols-3 gap-3">
                            <div class="rounded-xl bg-white/5 p-3">
                                <p class="text-[10px] text-slate-400">Employee</p>
                                <p class="mt-1 text-lg font-bold text-white">10%</p>
                            </div>
                            <div class="rounded-xl bg-white/5 p-3">
                                <p class="text-[10px] text-slate-400">Government</p>
                                <p class="mt-1 text-lg font-bold text-white">14%</p>
                            </div>
                            <div class="rounded-xl bg-gradient-to-br from-indigo-500/30 to-sky-500/30 p-3">
                                <p class="text-[10px] text-slate-300">Interest</p>
                                <p class="mt-1 text-lg font-bold text-white">8.8%</p>
                            </div>
                        </div>
                        <div class="mt-4 space-y-2">
                            @foreach (['PRAN allotted', 'Central register updated', 'Balance sheet finalised'] as $i => $row)
                                <div class="flex items-center gap-3 rounded-lg bg-white/5 px-3 py-2">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-400">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                    </span>
                                    <span class="text-sm text-slate-200">{{ $row }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="absolute -bottom-5 -left-5 rounded-2xl border border-slate-200/70 bg-white px-4 py-3 shadow-xl dark:border-white/10 dark:bg-slate-900">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Records secured</p>
                    <p class="text-lg font-bold">50M+</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================ STATS ============================ --}}
    <section class="border-y border-slate-200 bg-slate-50/60 dark:border-white/5 dark:bg-white/[0.02]">
        <div class="mx-auto grid max-w-7xl grid-cols-2 gap-8 px-4 py-12 sm:px-6 md:grid-cols-4 lg:px-8">
            @foreach ([['50M+', 'Contribution records'], ['46', 'Data modules'], ['3,000+', 'DDO offices'], ['100%', 'Auditable actions']] as $stat)
                <div class="reveal text-center">
                    <p class="bg-gradient-to-r from-indigo-500 to-sky-500 bg-clip-text text-3xl font-bold text-transparent sm:text-4xl">{{ $stat[0] }}</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $stat[1] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ============================ FEATURES ============================ --}}
    <section id="features" class="mx-auto max-w-7xl px-4 py-24 sm:px-6 lg:px-8">
        <div class="reveal mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Everything the directorate needs</h2>
            <p class="mt-4 text-slate-600 dark:text-slate-400">One platform for the full pension lifecycle — from the first draft received to the final balance sheet.</p>
        </div>

        <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @php
                $features = [
                    ['Subscriber &amp; PRAN', 'Register subscribers, allot account numbers and PRANs, and issue letters.', 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z'],
                    ['Contribution Ledger', 'Post monthly employee and government shares with full traceability.', 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z'],
                    ['Interest &amp; Balances', 'Compute yearly interest and roll forward opening and closing balances.', 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z'],
                    ['Reports &amp; CRA Exports', 'Generate Excel and PDF reports and export to the national recordkeeping agency.', 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z'],
                    ['Role-based Access', 'Granular, per-user permissions decide exactly what each operator can do.', 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z'],
                    ['Audit Trail', 'Every login and change is recorded — accountability by default.', 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                ];
            @endphp
            @foreach ($features as $f)
                <div data-cursor-grow class="reveal group rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition duration-300 hover:-translate-y-1 hover:border-indigo-300 hover:shadow-xl dark:border-white/10 dark:bg-white/[0.03] dark:hover:border-indigo-500/40">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500/15 to-sky-500/15 text-indigo-500 dark:text-indigo-400">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $f[2] }}" /></svg>
                    </div>
                    <h3 class="mt-5 text-lg font-semibold">{!! $f[0] !!}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{!! $f[1] !!}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ============================ SECURITY ============================ --}}
    <section id="security" class="border-y border-slate-200 bg-slate-50/60 dark:border-white/5 dark:bg-white/[0.02]">
        <div class="mx-auto grid max-w-7xl items-center gap-14 px-4 py-24 sm:px-6 lg:grid-cols-2 lg:px-8">
            <div class="reveal">
                <span class="text-sm font-semibold uppercase tracking-wider text-indigo-500 dark:text-indigo-400">Security first</span>
                <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Protecting public money is the job</h2>
                <p class="mt-4 text-slate-600 dark:text-slate-400">
                    Authentication is rebuilt from the ground up on modern standards — while every existing
                    operator keeps their credentials.
                </p>
                <a href="{{ route('login') }}" class="mt-8 inline-flex items-center gap-2 rounded-xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200">
                    Sign in securely
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                </a>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ([['Bcrypt passwords', 'Legacy hashes upgrade automatically on first login.'], ['CSRF protected', 'Every state change is verified.'], ['Rate limited', 'Server-side lockout stops brute force.'], ['CAPTCHA + IP lock', 'Bots blocked; accounts pinned to trusted networks.']] as $s)
                    <div class="reveal rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/[0.03]">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-500 dark:text-emerald-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        </div>
                        <h3 class="mt-4 font-semibold">{{ $s[0] }}</h3>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $s[1] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================ CTA ============================ --}}
    <section id="about" class="mx-auto max-w-7xl px-4 py-24 sm:px-6 lg:px-8">
        <div class="reveal relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 to-sky-600 px-8 py-16 text-center shadow-2xl sm:px-16">
            <div class="pointer-events-none absolute inset-0 grid-backdrop opacity-40"></div>
            <div class="relative">
                <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">Ready to get to work?</h2>
                <p class="mx-auto mt-4 max-w-xl text-indigo-100">Sign in with your existing user ID. Your account and history came across with you.</p>
                <a href="{{ route('login') }}" class="mt-8 inline-flex items-center gap-2 rounded-xl bg-white px-7 py-3 text-sm font-semibold text-indigo-600 shadow-lg transition hover:bg-indigo-50">
                    Sign in
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                </a>
            </div>
        </div>
    </section>

    {{-- ============================ FOOTER ============================ --}}
    <footer class="border-t border-slate-200 dark:border-white/10">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 py-8 sm:flex-row sm:px-6 lg:px-8">
            <div class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-sky-500 text-xs font-bold text-white">eN</span>
                <span class="text-sm font-semibold">eNPS</span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">&copy; {{ date('Y') }} eNPS — National Pension System. For authorised use only.</p>
        </div>
    </footer>
</x-layouts.marketing>
