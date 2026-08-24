<div class="mx-auto max-w-3xl">
    @php
        $input =
            'block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white';
        $label = 'mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300';
        $card = 'rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.03]';
        $head = 'mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400';
        $months = [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ];
    @endphp

    <x-breadcrumbs class="mb-4" :crumbs="['Entry Section' => null, 'Account Register' => null]" current="Migration to UPS" />

    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Migration to UPS</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Move a finalized NPS account to the Unified Pension
            Scheme. This can't be undone.</p>
    </div>

    {{-- Search --}}
    <div class="{{ $card }}">
        <h2 class="{{ $head }}">Search account</h2>
        <div class="relative">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search account number or name…"
                class="block w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
        </div>

        @if ($results->isNotEmpty())
            <ul
                class="mt-3 divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 dark:divide-white/5 dark:border-white/10">
                @foreach ($results as $r)
                    <li>
                        <button wire:click="selectAccount('{{ $r->account_no }}')" type="button"
                            class="flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-white/5">
                            <span class="font-medium text-slate-900 dark:text-white">{{ $r->account_no }}</span>
                            <span class="flex items-center gap-2 text-slate-500 dark:text-slate-400">
                                {{ $r->name }}
                                <span
                                    class="rounded-md px-1.5 py-0.5 text-[10px] font-bold {{ $r->pension_type === 'U' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300' }}">{{ $r->pension_type === 'U' ? 'UPS' : 'NPS' }}</span>
                            </span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @elseif (trim($search) !== '' && $selectedAccountNo === '')
            <p class="mt-3 text-sm text-slate-400">No finalized accounts match “{{ $search }}”.</p>
        @endif
    </div>

    {{-- Account details & migration --}}
    @if ($account)
        <div class="mt-6 {{ $card }}">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="{{ $head }} mb-0">Account details — {{ $account->account_no }}</h2>
                <button wire:click="closeForm" type="button"
                    class="text-sm text-slate-400 transition hover:text-slate-600 dark:hover:text-slate-200">✕
                    Close</button>
            </div>

            <div class="mb-5 grid grid-cols-1 gap-4 rounded-xl bg-slate-50 p-4 sm:grid-cols-2 dark:bg-white/5">
                <div>
                    <span class="text-xs text-slate-400">Name</span>
                    <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $account->name }}</p>
                </div>
                <div>
                    <span class="text-xs text-slate-400">Current pension type</span>
                    <p class="mt-0.5">
                        <span
                            class="rounded-md px-2 py-0.5 text-xs font-bold {{ $account->pension_type === 'U' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300' }}">
                            {{ $account->pension_type === 'U' ? 'UPS' : ($account->pension_type === 'N' ? 'NPS' : $account->pension_type) }}
                        </span>
                    </p>
                </div>
            </div>

            @if ($account->pension_type === 'U')
                <div
                    class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                    <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                    This account is already migrated to UPS. No further action is needed.
                </div>
            @else
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label class="{{ $label }}">Migration Year</label>
                        <select wire:model.live="migrationYear" class="{{ $input }}">
                            <option value="">Select year…</option>
                            @foreach ($years as $y)
                                <option value="{{ $y }}">{{ $y }}</option>
                            @endforeach
                        </select>
                        @error('migrationYear')
                            <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">Migration Month</label>
                        <select wire:model.live="migrationMonth" class="{{ $input }}"
                            @disabled($migrationYear === '')>
                            <option value="">
                                {{ $migrationYear === '' ? 'Select a year first' : 'Select month…' }}</option>
                            @for ($m = 1; $m <= $maxMonth; $m++)
                                <option value="{{ $m }}">{{ $months[$m] }}</option>
                            @endfor
                        </select>
                        @error('migrationMonth')
                            <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button wire:click="migrate" type="button" @disabled($migrationYear === '' || $migrationMonth === '')
                        wire:confirm="Migrate account {{ $account->account_no }} from NPS to UPS? This can't be undone."
                        class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 px-5 py-2.5 text-sm font-semibold text-white shadow transition hover:from-emerald-400 hover:to-teal-400 disabled:cursor-not-allowed disabled:opacity-50">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                        </svg>
                        Migrate to UPS
                    </button>
                </div>
            @endif
        </div>
    @endif
</div>
