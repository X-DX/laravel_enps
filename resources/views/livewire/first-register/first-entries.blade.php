<div class="mx-auto max-w-7xl">
    @php
        $screens = [
            'all' => ['View All First Entries', 'Every first-register receipt and draft.'],
            'pending' => ['Pending First Entry', 'Draft entries waiting to be finalized.'],
            'finalized' => ['Finalized First Entry', 'Entries that have been finalized.'],
        ];
        [$title, $subtitle] = $screens[$mode] ?? $screens['all'];
    @endphp

    <x-breadcrumbs class="mb-4" :crumbs="['Entry Section' => null, 'First Register' => null]" :current="$title" />

    <div class="mb-6 flex items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $title }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
        </div>
        @can('entrysection.entry_first_register')
            <a href="{{ route('first-entries.entry') }}" wire:navigate
                class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-sky-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:from-indigo-400 hover:to-sky-400">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                New Entry
            </a>
        @endcan
    </div>

    {{-- Toolbar --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="relative min-w-56 flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search receipt no, draft no, order no…"
                class="block w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
        </div>

        @if ($mode === 'all')
            <div class="flex items-center gap-2">
                <label for="status" class="text-sm text-slate-500 dark:text-slate-400">Status</label>
                <select wire:model.live="status" id="status"
                    class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
                    <option value="">All</option>
                    <option value="T">Pending</option>
                    <option value="F">Finalized</option>
                </select>
            </div>
        @endif

        <div class="flex items-center gap-2">
            <label for="perPage" class="text-sm text-slate-500 dark:text-slate-400">Show</label>
            <select wire:model.live="perPage" id="perPage"
                class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
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

    {{-- Table --}}
    <div wire:loading.class.delay="opacity-50" wire:target="search,status,perPage" class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm transition dark:border-white/10 dark:bg-white/[0.03]">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-white/10">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <th class="px-3 py-2">Sl</th>
                    <th class="px-3 py-2">Receipt No</th>
                    <th class="px-3 py-2">Draft/Receipt No</th>
                    <th class="px-3 py-2">Date</th>
                    <th class="px-3 py-2">Amount</th>
                    <th class="px-3 py-2">DDO</th>
                    <th class="px-3 py-2">Purpose</th>
                    <th class="px-3 py-2">Contribution</th>
                    <th class="px-3 py-2">Pension</th>
                    <th class="px-3 py-2">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                @forelse ($entries as $entry)
                    <tr wire:key="fr-{{ $entry->sl_no }}" class="text-sm transition hover:bg-slate-50 dark:hover:bg-white/5">
                        <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ $entries->firstItem() + $loop->index }}</td>
                        <td class="px-3 py-2 font-medium text-slate-900 dark:text-white">{{ $entry->sl_no }}</td>
                        <td class="px-3 py-2 text-slate-700 dark:text-slate-200">
                            {{ $entry->draft_no }}
                            <span class="ml-1 rounded px-1 py-0.5 text-[10px] font-bold {{ $entry->type === 'D' ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' : 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300' }}">{{ $entry->type === 'D' ? 'D' : 'R' }}</span>
                        </td>
                        <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $entry->draft_date?->format('d-m-Y') ?? '—' }}</td>
                        <td class="px-3 py-2 font-medium text-slate-800 dark:text-slate-100">{{ number_format((float) $entry->amount, 2) }}</td>
                        <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $entry->ddo?->ddo_name ?? '—' }}</td>
                        <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $entry->purposeCode?->purpose ?? $entry->purpose }}</td>
                        <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $entry->contribution_type === 'SC' ? 'Single' : ($entry->contribution_type === 'DC' ? 'Double' : $entry->contribution_type) }}</td>
                        <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $entry->pension_type === 'U' ? 'UPS' : 'NPS' }}</td>
                        <td class="px-3 py-2">
                            @if ($entry->flag === 'T')
                                <span class="rounded-md bg-amber-300 px-2 py-0.5 text-xs font-semibold text-amber-900 dark:bg-amber-500/20 dark:text-amber-300">Pending</span>
                            @elseif (in_array($entry->flag, ['FZ', 'CR']))
                                <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">Finalized</span>
                            @else
                                <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-white/10 dark:text-slate-300">{{ $entry->flag }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10"><x-empty-state icon="banknotes" title="No first-register entries found" message="Try a different search or status filter." /></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $entries->links() }}</div>
</div>
