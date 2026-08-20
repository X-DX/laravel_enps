<div class="mx-auto max-w-4xl">
    @php
        $input =
            'block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white';
        $label = 'mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300';
        $err = 'mt-1.5 text-sm text-rose-600 dark:text-rose-400';
        $card = 'rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.03]';
        $head = 'mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400';
    @endphp

    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Assign PRAN Against Accounts
        </h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Find a finalized account and record its PRAN. Saved as
            a draft — finalize it in the list below.</p>
    </div>

    {{-- ① Search account --}}
    <div class="{{ $card }}">
        <h2 class="{{ $head }}">Search account for PRAN update</h2>
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
                            <span class="text-slate-500 dark:text-slate-400">{{ $r->name }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @elseif (trim($search) !== '' && $selectedAccountNo === '')
            <p class="mt-3 text-sm text-slate-400">No finalized accounts match “{{ $search }}”.</p>
        @endif
    </div>

    {{-- ② Add/Update PRAN form --}}
    @if ($account)
        <div class="mt-6 {{ $card }}">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="{{ $head }} mb-0">{{ $mode === 'update' ? 'Update' : 'Add' }} PRAN —
                    {{ $account->account_no }}</h2>
                <button wire:click="closeForm" type="button"
                    class="text-sm text-slate-400 transition hover:text-slate-600 dark:hover:text-slate-200">✕
                    Close</button>
            </div>

            {{-- read-only account details --}}
            <div class="mb-5 grid grid-cols-1 gap-4 rounded-xl bg-slate-50 p-4 sm:grid-cols-2 dark:bg-white/5">
                <div><span class="text-xs text-slate-400">Name</span>
                    <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $account->name }}</p>
                </div>
                <div><span class="text-xs text-slate-400">Designation</span>
                    <p class="text-sm text-slate-700 dark:text-slate-200">
                        {{ $account->designationMaster?->designation ?? '—' }}</p>
                </div>
                <div><span class="text-xs text-slate-400">Treasury Location</span>
                    <p class="text-sm text-slate-700 dark:text-slate-200">{{ $treasuryName ?: '—' }}</p>
                </div>
                <div><span class="text-xs text-slate-400">DDO</span>
                    <p class="text-sm text-slate-700 dark:text-slate-200">{{ $account->ddo?->ddo_name ?? '—' }}</p>
                </div>
            </div>

            {{-- PRAN fields --}}
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="{{ $label }}">PRAN No</label>
                    <input wire:model="pranNo" type="password" maxlength="12" inputmode="numeric" autocomplete="off"
                        class="{{ $input }}">
                    @error('pranNo')
                        <p class="{{ $err }}">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="{{ $label }}">Confirm PRAN No</label>
                    <input wire:model="confirmPranNo" type="text" maxlength="12" inputmode="numeric"
                        autocomplete="off" class="{{ $input }}">
                    @error('confirmPranNo')
                        <p class="{{ $err }}">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="{{ $label }}">PRAN Appointment Date</label>
                    <input wire:model="pranAllotmentDate" type="date" class="{{ $input }}">
                    @error('pranAllotmentDate')
                        <p class="{{ $err }}">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex items-center gap-2 pt-7">
                    <input wire:model="niraAccount" id="nira" type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/30">
                    <label for="nira" class="text-sm text-slate-700 dark:text-slate-300">NIRA Account</label>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button wire:click="closeForm" type="button"
                    class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Close</button>
                <button wire:click="save" type="button"
                    class="rounded-xl bg-gradient-to-r from-indigo-500 to-sky-500 px-6 py-2.5 text-sm font-semibold text-white shadow transition hover:from-indigo-400 hover:to-sky-400">
                    {{ $mode === 'update' ? 'Update' : 'Add' }}
                </button>
            </div>
        </div>
    @endif

    {{-- ③ Pending PRANs list — coming in slice 5.1b --}}
    {{-- ③ Pending PRANs for finalize --}}
    <div class="mt-8">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Pending
                assigned PRANs for finalize</h2>
            <div class="flex flex-wrap items-center gap-2">
                <button wire:click="exportPending" type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-300 bg-emerald-50 px-3.5 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20">Excel</button>
                <button wire:click="pdfPending" type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-rose-300 bg-rose-50 px-3.5 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20">PDF</button>
                <button wire:click="finalizeSelected" type="button" @disabled(count($selected) === 0)
                    wire:confirm="Finalize {{ count($selected) }} PRAN(s)? This can't be undone."
                    class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-indigo-500 to-sky-500 px-4 py-2 text-sm font-semibold text-white shadow transition hover:from-indigo-400 hover:to-sky-400 disabled:cursor-not-allowed disabled:opacity-50">
                    Finalize ({{ count($selected) }})
                </button>
                <button wire:click="deleteSelected" type="button" @disabled(count($selected) === 0)
                    wire:confirm="Delete {{ count($selected) }} draft PRAN(s)? This can't be undone."
                    class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow transition hover:bg-rose-500 disabled:cursor-not-allowed disabled:opacity-50">
                    Delete ({{ count($selected) }})
                </button>
            </div>
        </div>

        <div
            class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-white/10">
                <thead>
                    <tr
                        class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <th class="px-3 py-2">
                            <input type="checkbox" wire:click="toggleSelectAll" @checked(count($pagePendingKeys) > 0 && count(array_diff($pagePendingKeys, $selected)) === 0)
                                @disabled(count($pagePendingKeys) === 0)
                                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/30">
                        </th>
                        <th class="px-3 py-2">Sl</th>
                        <th class="px-3 py-2">Account No</th>
                        <th class="px-3 py-2">PRAN No</th>
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">DOB</th>
                        <th class="px-3 py-2">Department</th>
                        <th class="px-3 py-2">Designation</th>
                        <th class="px-3 py-2">DDO</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                    @forelse ($pending as $pran)
                        <tr wire:key="pp-{{ $pran->account_no }}"
                            class="text-sm transition hover:bg-slate-50 dark:hover:bg-white/5">
                            <td class="px-3 py-2">
                                <input type="checkbox" wire:model.live="selected" value="{{ $pran->account_no }}"
                                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/30">
                            </td>
                            <td class="px-3 py-2 text-slate-500 dark:text-slate-400">
                                {{ $pending->firstItem() + $loop->index }}</td>
                            <td class="px-3 py-2 font-medium text-slate-900 dark:text-white">{{ $pran->account_no }}
                            </td>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-300">
                                {{ $pran->pran_no ? number_format($pran->pran_no, 0, '.', '') : '—' }}</td>
                            <td class="px-3 py-2 text-slate-700 dark:text-slate-200">
                                {{ $pran->subscriber?->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-300">
                                {{ $pran->subscriber?->dob?->format('d-m-Y') ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-300">
                                {{ $pran->subscriber ? $departments[trim($pran->subscriber->nameofdept)] ?? trim($pran->subscriber->nameofdept) : '—' }}
                            </td>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-300">
                                {{ $pran->subscriber?->designationMaster?->designation ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-300">
                                {{ $pran->subscriber?->ddo?->ddo_name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9"><x-empty-state icon="identification" title="No pending PRANs" message="Assign a PRAN above — drafts appear here to finalize." /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $pending->links() }}</div>
    </div>

</div>
