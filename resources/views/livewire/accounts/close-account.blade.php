<div class="mx-auto max-w-5xl">
    {{-- Title --}}
    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Close Account</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Pick a department and account, add the closure
            details, then close it. This can't be undone.</p>
    </div>

    {{-- Search account for closure --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
        <h2 class="mb-5 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Search account
            for closure</h2>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            {{-- Department --}}
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Department Name</label>
                <x-searchable-select model="departmentCode" :options="$departmentOptions" placeholder="Select department…"
                    :live="true" />
                @error('departmentCode')
                    <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Account No --}}
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Account No.</label>
                <x-searchable-select model="accountNo" :options="$accountOptions" placeholder="Select account no…"
                    :live="true" :disabled="$departmentCode === ''" wire:key="account-{{ $departmentCode }}" />
                @error('accountNo')
                    <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Name (read-only) --}}
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Name</label>
                <div
                    class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-900 dark:border-white/10 dark:bg-white/5 dark:text-white">
                    {{ $name ?: '—' }}</div>
            </div>

            {{-- PRAN (read-only) --}}
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">PRAN Number</label>
                <div
                    class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-900 dark:border-white/10 dark:bg-white/5 dark:text-white">
                    {{ $pranNo ?: '—' }}</div>
            </div>

            {{-- Closure reason --}}
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Closure Reason</label>
                <select wire:model.live="closeReason"
                    class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
                    <option value="">-- Select reason --</option>
                    @foreach ($reasons as $reason)
                        <option value="{{ $reason->id }}">{{ $reason->reason }}</option>
                    @endforeach
                </select>
                @error('closeReason')
                    <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Closing date --}}
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Closing Date</label>
                <input wire:model="closingDate" type="date"
                    class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
                @error('closingDate')
                    <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Deduction month --}}
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Deduction Month</label>
                <select wire:model="deductionMonth"
                    class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
                    <option value="">-- Select month --</option>
                    @foreach (range(1, 12) as $m)
                        <option value="{{ $m }}">{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                    @endforeach
                </select>
                @error('deductionMonth')
                    <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Deduction year --}}
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Deduction Year</label>
                <select wire:model="deductionYear"
                    class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">

                    <option value="">-- Select year --</option>

                    @foreach (range(2008, now()->year) as $year)
                    @endforeach

                    @foreach (range(now()->year, 2008) as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach

                </select>
                @error('deductionYear')
                    <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <button wire:click="close" type="button" @disabled($accountNo === '')
                wire:confirm="Close account {{ $accountNo }}? This can't be undone."
                class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white shadow transition hover:bg-rose-500 disabled:cursor-not-allowed disabled:opacity-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 6 6 18M6 6l12 12" />
                </svg>
                Close Account
            </button>
        </div>
    </div>

    {{-- Closed accounts register --}}
    <div class="mt-8">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Closed accounts
            </h2>
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative min-w-52">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                        fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <input wire:model.live.debounce.300ms="closedSearch" type="search" placeholder="Search closed…"
                        class="block w-full rounded-xl border border-slate-300 bg-white py-2 pl-10 pr-3 text-sm text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
                </div>
                <button wire:click="exportClosed" type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-emerald-300 bg-emerald-50 px-3.5 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Excel
                </button>
                <button wire:click="pdfClosed" type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-rose-300 bg-rose-50 px-3.5 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    PDF
                </button>
            </div>
        </div>

        <div
            class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-white/10">
                <thead>
                    <tr
                        class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <th class="px-4 py-3">Sl</th>
                        <th class="px-4 py-3">Account No</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Closure Reason</th>
                        <th class="px-4 py-3">Closing Date</th>
                        <th class="px-4 py-3">Deduction</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                    @forelse ($closedAccounts as $closure)
                        <tr wire:key="closed-{{ $closure->account_no }}" class="text-sm">
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                {{ $closedAccounts->firstItem() + $loop->index }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">
                                {{ $closure->account_no }}</td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-200">
                                {{ $closure->subscriber?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-md bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700 dark:bg-rose-500/15 dark:text-rose-300">{{ $closure->reason?->reason ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                                {{ $closure->closing_date?->format('d-m-Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                                {{ $closure->deduction_month ? date('F', mktime(0, 0, 0, $closure->deduction_month, 1)) : '—' }}
                                {{ $closure->deduction_year }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400">No closed
                                accounts yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $closedAccounts->links() }}</div>
    </div>
</div>
