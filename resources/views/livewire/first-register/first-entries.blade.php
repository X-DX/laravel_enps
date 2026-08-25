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

        @if (in_array($mode, ['all', 'finalized']))
            <div class="flex items-center gap-2">
                <label for="status" class="text-sm text-slate-500 dark:text-slate-400">Status</label>
                <select wire:model.live="status" id="status"
                    class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
                    <option value="">All</option>
                    @if ($mode === 'all')
                        <option value="T">Pending at First Receipt</option>
                    @endif
                    <option value="CR">Pending at CR Generation</option>
                    <option value="FZ">Finalized (CR Generated)</option>
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

        @if ($mode === 'pending')
            <button wire:click="finalizeSelected" type="button" @disabled(count($selected) === 0)
                wire:confirm="Finalize {{ count($selected) }} selected entry(ies)? This can't be undone."
                class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-sky-500 px-4 py-2.5 text-sm font-semibold text-white shadow transition hover:from-indigo-400 hover:to-sky-400 disabled:cursor-not-allowed disabled:opacity-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                Finalize ({{ count($selected) }})
            </button>
            <button wire:click="deleteSelected" type="button" @disabled(count($selected) === 0)
                wire:confirm="Delete {{ count($selected) }} draft entry(ies)? This can't be undone."
                class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow transition hover:bg-rose-500 disabled:cursor-not-allowed disabled:opacity-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                Delete ({{ count($selected) }})
            </button>
        @endif
    </div>

    {{-- Table (compact). Tone → CSS classes for the 3-state status badge. --}}
    @php
        $tones = [
            'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
            'sky' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
            'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
            'slate' => 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300',
        ];
        $colspan = 14 + ($mode === 'pending' ? 1 : 0) + (in_array($mode, ['pending', 'finalized']) ? 1 : 0);
    @endphp
    <div wire:loading.class.delay="opacity-50" wire:target="search,status,perPage" class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm transition dark:border-white/10 dark:bg-white/[0.03]">
        <table class="min-w-full divide-y divide-slate-200 text-xs dark:divide-white/10">
            <thead>
                <tr class="whitespace-nowrap text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    @if ($mode === 'pending')
                        <th class="px-2 py-2">
                            <input type="checkbox" wire:click="toggleSelectAll"
                                @checked(count($pagePendingKeys) > 0 && count(array_diff($pagePendingKeys, $selected)) === 0)
                                @disabled(count($pagePendingKeys) === 0)
                                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/30">
                        </th>
                    @endif
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
                    <th class="px-2 py-2">Status</th>
                    @if (in_array($mode, ['pending', 'finalized']))
                        <th class="px-2 py-2 text-right">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                @forelse ($entries as $entry)
                    @php
                        $isDraft = $entry->type === 'D';
                        $drBadge = $isDraft
                            ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'
                            : 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300';
                        $treasuryLocation = $entry->ddo?->treasury?->treasury_name ?? $entry->ddo?->location?->loc_name;
                        $bankName = $entry->bank ? trim($entry->bank->bank_name) . ', ' . trim($entry->bank->branch_name) : null;
                    @endphp
                    <tr wire:key="fr-{{ $entry->sl_no }}" class="whitespace-nowrap align-middle transition hover:bg-slate-50 dark:hover:bg-white/5">
                        @if ($mode === 'pending')
                            <td class="px-2 py-1.5">
                                <input type="checkbox" wire:model.live="selected" value="{{ $entry->sl_no }}"
                                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/30">
                            </td>
                        @endif
                        <td class="px-2 py-1.5 text-slate-500 dark:text-slate-400">{{ $entries->firstItem() + $loop->index }}</td>
                        <td class="px-2 py-1.5 font-medium">
                            <a href="{{ route('first-entries.show', $entry->sl_no) }}" wire:navigate class="text-indigo-600 hover:underline dark:text-indigo-300">{{ $entry->sl_no }}</a>
                        </td>
                        <td class="px-2 py-1.5 text-slate-600 dark:text-slate-300">{{ $treasuryLocation ?? '—' }}</td>
                        <td class="px-2 py-1.5 text-slate-600 dark:text-slate-300">{{ $entry->ddo?->ddo_name ?? '—' }}</td>
                        <td class="px-2 py-1.5 text-slate-600 dark:text-slate-300">{{ $entry->order_no ?: '—' }}</td>
                        <td class="px-2 py-1.5 text-slate-600 dark:text-slate-300">{{ $entry->order_date?->format('d-m-Y') ?? '—' }}</td>
                        <td class="px-2 py-1.5 text-slate-700 dark:text-slate-200">
                            {{ $entry->draft_no }}
                            <span class="ml-1 rounded px-1 py-0.5 text-[10px] font-bold {{ $drBadge }}">{{ $isDraft ? 'D' : 'R' }}</span>
                        </td>
                        <td class="px-2 py-1.5 text-slate-600 dark:text-slate-300">
                            {{ $entry->draft_date?->format('d-m-Y') ?? '—' }}
                            <span class="ml-1 rounded px-1 py-0.5 text-[10px] font-bold {{ $drBadge }}">{{ $isDraft ? 'D' : 'R' }}</span>
                        </td>
                        <td class="px-2 py-1.5 text-right font-medium text-slate-800 dark:text-slate-100">{{ number_format((float) $entry->amount, 2) }}</td>
                        <td class="px-2 py-1.5 text-slate-600 dark:text-slate-300">{{ $entry->contribution_type === 'SC' ? 'Single' : ($entry->contribution_type === 'DC' ? 'Double' : $entry->contribution_type) }}</td>
                        <td class="px-2 py-1.5 text-slate-600 dark:text-slate-300">{{ $bankName ?? '—' }}</td>
                        <td class="px-2 py-1.5 text-slate-600 dark:text-slate-300">{{ $entry->purposeLabel() }}</td>
                        <td class="px-2 py-1.5">
                            <span class="inline-block rounded-md px-2 py-0.5 text-[11px] font-semibold {{ $tones[$entry->statusTone()] }}">{{ $entry->statusLabel() }}</span>
                        </td>
                        @if (in_array($mode, ['pending', 'finalized']))
                            <td class="px-2 py-1.5 text-right">
                                @can('entrysection.entry_first_register')
                                    <a href="{{ route('first-entries.edit', $entry->sl_no) }}" wire:navigate
                                        class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-2.5 py-1 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" /></svg>
                                        Edit
                                    </a>
                                @endcan
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $colspan }}"><x-empty-state icon="banknotes" title="No first-register entries found" message="Try a different search or status filter." /></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $entries->links() }}</div>
</div>
