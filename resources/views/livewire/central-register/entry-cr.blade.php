<div class="mx-auto max-w-7xl">
    <x-breadcrumbs class="mb-4" :crumbs="['Entry Section' => null, 'Central Register' => null]" current="Entry CR" />

    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Entry CR</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Tick the receipts waiting at “Pending at CR Generation” and generate their Central Register numbers.
        </p>
    </div>

    {{-- Toolbar: search (Receipt No only) + page size + export --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="relative min-w-56 flex-1">
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search by Receipt No…"
                class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
        </div>
        <div class="flex items-center gap-2">
            <label for="perPage" class="text-sm text-slate-500 dark:text-slate-400">Show</label>
            <select wire:model.live="perPage" id="perPage"
                class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
                @foreach ([25, 50, 100] as $size)
                    <option value="{{ $size }}">{{ $size }}</option>
                @endforeach
            </select>
        </div>

        <button wire:click="export" type="button"
            class="inline-flex items-center gap-2 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            Excel
        </button>
        <button wire:click="pdf" type="button"
            class="inline-flex items-center gap-2 rounded-xl border border-rose-300 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-700 transition hover:bg-rose-100 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
            </svg>
            PDF
        </button>
    </div>

    {{-- Pending-CR table --}}
    <div wire:loading.class.delay="opacity-50" wire:target="search,perPage"
        class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
        <table class="min-w-full divide-y divide-slate-200 text-xs dark:divide-white/10">
            <thead>
                <tr
                    class="whitespace-nowrap text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <th class="px-2 py-2">
                        <input type="checkbox" wire:click="toggleSelectAll"
                            @checked(count($pageKeys) > 0 && count(array_diff($pageKeys, $selected)) === 0)
                            @disabled(count($pageKeys) === 0)
                            class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/30">
                    </th>
                    <th class="px-2 py-2">Sl no</th>
                    <th class="px-2 py-2">Receipt No</th>
                    <th class="px-2 py-2">Treasury Location</th>
                    <th class="px-2 py-2">DDO</th>
                    <th class="px-2 py-2">Order/Letter No</th>
                    <th class="px-2 py-2">Order Date</th>
                    <th class="px-2 py-2">Draft/Receipt No</th>
                    <th class="px-2 py-2">Draft/Receipt Date</th>
                    <th class="px-2 py-2 text-right">Amount</th>
                    <th class="px-2 py-2">Contribution</th>
                    <th class="px-2 py-2">Draw Bank</th>
                    <th class="px-2 py-2">Purpose</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                @forelse ($entries as $entry)
                    @php
                        $treasuryLocation = $entry->ddo?->treasury?->treasury_name ?? $entry->ddo?->location?->loc_name;
                        $bankName = $entry->bank
                            ? trim($entry->bank->bank_name) . ', ' . trim($entry->bank->branch_name)
                            : null;
                    @endphp
                    <tr wire:key="cr-{{ $entry->sl_no }}"
                        class="whitespace-nowrap align-middle transition hover:bg-slate-50 dark:hover:bg-white/5">
                        <td class="px-2 py-1.5">
                            <input type="checkbox" wire:model.live="selected" value="{{ $entry->sl_no }}"
                                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/30">
                        </td>
                        <td class="px-2 py-1.5 text-slate-500 dark:text-slate-400">
                            {{ $entries->firstItem() + $loop->index }}</td>
                        <td class="px-2 py-1.5 font-medium">
                            <a href="{{ route('first-entries.show', $entry->sl_no) }}" wire:navigate
                                class="text-indigo-600 hover:underline dark:text-indigo-300">{{ $entry->sl_no }}</a>
                        </td>
                        <td class="px-2 py-1.5 text-slate-600 dark:text-slate-300">{{ $treasuryLocation ?? '—' }}</td>
                        <td class="px-2 py-1.5 text-slate-600 dark:text-slate-300">{{ $entry->ddo?->ddo_name ?? '—' }}
                        </td>
                        <td class="px-2 py-1.5 text-slate-600 dark:text-slate-300">{{ $entry->order_no ?: '—' }}</td>
                        <td class="px-2 py-1.5 text-slate-600 dark:text-slate-300">
                            {{ $entry->order_date?->format('d-m-Y') ?? '—' }}</td>
                        <td class="px-2 py-1.5 text-slate-700 dark:text-slate-200">{{ $entry->draft_no }}</td>
                        <td class="px-2 py-1.5 text-slate-600 dark:text-slate-300">
                            {{ $entry->draft_date?->format('d-m-Y') ?? '—' }}</td>
                        <td class="px-2 py-1.5 text-right font-medium text-slate-800 dark:text-slate-100">
                            {{ number_format((float) $entry->amount, 2) }}</td>
                        <td class="px-2 py-1.5 text-slate-600 dark:text-slate-300">
                            {{ $entry->contribution_type === 'SC' ? 'Single' : ($entry->contribution_type === 'DC' ? 'Double' : $entry->contribution_type) }}
                        </td>
                        <td class="px-2 py-1.5 text-slate-600 dark:text-slate-300">{{ $bankName ?? '—' }}</td>
                        <td class="px-2 py-1.5 text-slate-600 dark:text-slate-300">{{ $entry->purposeLabel() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="13"><x-empty-state icon="banknotes" title="Nothing waiting for CR generation"
                                message="Finalize entries in First Register and they will appear here." /></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $entries->links() }}</div>
</div>
