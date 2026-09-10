<div class="mx-auto max-w-7xl">
    <x-breadcrumbs class="mb-4" :crumbs="['Entry Section' => null, 'Central Register' => null]" :current="$title" />

    @php $blocked = $mode === 'blocked'; @endphp

    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $title }}</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            {{ $blocked
                ? 'Entries currently frozen. Unblock one, or edit its reason.'
                : 'Freeze a finalized Central Register entry with a reason. Blocking never deletes — it holds the entry from downstream processing.' }}
        </p>
    </div>

    {{-- Toolbar --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="relative min-w-64 flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search CR No, Receipt No, First Receipt No, or draft/order no…"
                class="block w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
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
    </div>

    {{-- Table --}}
    <div wire:loading.class.delay="opacity-50" wire:target="search,perPage"
        class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
        <table class="min-w-full divide-y divide-slate-200 text-xs dark:divide-white/10">
            <thead>
                <tr class="whitespace-nowrap text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <th class="px-2 py-2">Sl no</th>
                    <th class="px-2 py-2">CR No</th>
                    <th class="px-2 py-2">Receipt No</th>
                    <th class="px-2 py-2">First Receipt No</th>
                    <th class="px-2 py-2">DDO</th>
                    <th class="px-2 py-2">Draft/Receipt No</th>
                    <th class="px-2 py-2 text-right">Amount</th>
                    @if ($blocked)
                        <th class="px-2 py-2">Blocked On</th>
                        <th class="px-2 py-2">Reason</th>
                    @endif
                    <th class="px-2 py-2 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                @forelse ($entries as $entry)
                    @php
                        $fr = $entry->firstReceipt;
                        $treasuryLocation = $fr?->ddo?->treasury?->treasury_name ?? $fr?->ddo?->location?->loc_name;
                        $amount = $entry->amount ?? $fr?->amount;
                    @endphp
                    <tr wire:key="bcr-{{ $entry->sl_no }}" class="whitespace-nowrap align-middle transition hover:bg-slate-50 dark:hover:bg-white/5">
                        <td class="px-2 py-1.5 text-slate-500 dark:text-slate-400">{{ $entries->firstItem() + $loop->index }}</td>
                        <td class="px-2 py-1.5 font-semibold text-slate-800 dark:text-slate-100">{{ $entry->sl_no }}</td>
                        <td class="px-2 py-1.5 text-slate-700 dark:text-slate-200">{{ $entry->receipt_no ?? '—' }}</td>
                        <td class="px-2 py-1.5 font-medium">
                            @if ($entry->first_receipt_sl_no)
                                <a href="{{ route('first-entries.show', $entry->first_receipt_sl_no) }}" wire:navigate class="text-indigo-600 hover:underline dark:text-indigo-300">{{ $entry->first_receipt_sl_no }}</a>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-2 py-1.5 text-slate-600 dark:text-slate-300" title="{{ $treasuryLocation }}">{{ $fr?->ddo?->ddo_name ?? '—' }}</td>
                        <td class="px-2 py-1.5 text-slate-600 dark:text-slate-300">{{ $entry->draft_no ?: ($fr?->draft_no ?? '—') }}</td>
                        <td class="px-2 py-1.5 text-right font-medium text-slate-800 dark:text-slate-100">{{ $amount !== null ? number_format((float) $amount, 2) : '—' }}</td>
                        @if ($blocked)
                            <td class="px-2 py-1.5 text-slate-600 dark:text-slate-300">{{ $entry->blocked_date?->format('d-m-Y') ?? '—' }}</td>
                            <td class="max-w-xs truncate px-2 py-1.5 text-slate-600 dark:text-slate-300" title="{{ $entry->blocked_reason }}">{{ $entry->blocked_reason ?? '—' }}</td>
                        @endif
                        <td class="px-2 py-1.5 text-right">
                            @if ($blocked)
                                <div class="inline-flex items-center gap-1.5">
                                    <button wire:click="openEdit({{ $entry->sl_no }})" type="button"
                                        class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-2.5 py-1 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">
                                        Edit reason
                                    </button>
                                    <button wire:click="unblock({{ $entry->sl_no }})" type="button"
                                        wire:confirm="Unblock CR No {{ $entry->sl_no }}?"
                                        class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-2.5 py-1 text-[11px] font-semibold text-white transition hover:bg-emerald-500">
                                        Unblock
                                    </button>
                                </div>
                            @else
                                <button wire:click="openBlock({{ $entry->sl_no }})" type="button"
                                    class="inline-flex items-center gap-1 rounded-lg bg-rose-600 px-2.5 py-1 text-[11px] font-semibold text-white transition hover:bg-rose-500">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                    Block
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $blocked ? 10 : 8 }}">
                            <x-empty-state icon="banknotes"
                                :title="$blocked ? 'No blocked CR entries' : 'Nothing to block'"
                                :message="$blocked ? 'Blocked entries will appear here.' : 'All your finalized entries are clear.'" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $entries->links() }}</div>

    {{-- Reason modal (block / edit) --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.closeModal()">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="closeModal"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-white/10 dark:bg-slate-900">
                <h2 class="font-display text-lg font-bold text-slate-900 dark:text-white">
                    {{ $modalAction === 'block' ? 'Block CR No ' . $modalCrNo : 'Edit reason — CR No ' . $modalCrNo }}
                </h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ $modalAction === 'block' ? 'This entry will be frozen until it is unblocked.' : 'Update the note explaining why this entry is held.' }}
                </p>

                <div class="mt-4">
                    <label for="reason" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Reason</label>
                    <textarea wire:model="reason" id="reason" rows="3" autofocus
                        class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white"></textarea>
                    @error('reason')
                        <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-5 flex items-center justify-end gap-3">
                    <button wire:click="closeModal" type="button" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Cancel</button>
                    <button wire:click="save" type="button"
                        class="rounded-xl bg-gradient-to-r from-indigo-500 to-sky-500 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:from-indigo-400 hover:to-sky-400">
                        {{ $modalAction === 'block' ? 'Block entry' : 'Save reason' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
