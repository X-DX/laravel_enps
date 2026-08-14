<div class="mx-auto max-w-7xl">
    @php
        $screens = [
            'all' => ['View All Accounts', 'Every subscriber and their allotted account number.'],
            'pending' => ['Pending Issue Accounts', 'Drafts waiting to be finalized — tick rows, then Finalize to allot account numbers.'],
            'finalized' => ['Finalized Issued Account', 'Subscribers whose account number has been allotted.'],
        ];
        [$title, $subtitle] = $screens[$mode] ?? $screens['all'];
    @endphp

    {{-- Title --}}
    <div class="mb-6 flex items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $title }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
        </div>
        @can('entrysection.issue_account')
        <a href="{{ route('accounts.issue') }}" wire:navigate
            class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-sky-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:from-indigo-400 hover:to-sky-400">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            New Account
        </a>
        @endcan
    </div>

    {{-- Toolbar: search · status (all only) · show · Excel · PDF · Finalize/Delete (pending only) --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="relative min-w-56 flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search name or account no…"
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

        {{-- Excel --}}
        <button wire:click="export" type="button"
            class="inline-flex items-center gap-2 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            Excel
        </button>

        {{-- PDF --}}
        <button wire:click="pdf" type="button"
            class="inline-flex items-center gap-2 rounded-xl border border-rose-300 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-700 transition hover:bg-rose-100 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
            </svg>
            PDF
        </button>

        @if ($mode === 'pending')
        {{-- Finalize --}}
        <button wire:click="finalize" type="button" @disabled(count($selected)===0)
            wire:confirm="Finalize {{ count($selected) }} selected subscriber(s)? This allots their account numbers and can't be undone."
            class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-sky-500 px-4 py-2.5 text-sm font-semibold text-white shadow transition hover:from-indigo-400 hover:to-sky-400 disabled:cursor-not-allowed disabled:opacity-50">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
            Finalize selected ({{ count($selected) }})
        </button>

        {{-- Delete --}}
        <button wire:click="deleteSelected" type="button" @disabled(count($selected)===0)
            wire:confirm="Delete {{ count($selected) }} selected pending draft(s)? This cannot be undone."
            class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow transition hover:bg-rose-500 disabled:cursor-not-allowed disabled:opacity-50">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
            </svg>
            Delete ({{ count($selected) }})
        </button>
        @endif
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-white/10">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    @if ($mode === 'pending')
                    <th class="px-3 py-2">
                        <input type="checkbox" wire:click="toggleSelectAll"
                            @checked(count($pagePendingIds) > 0 && count(array_diff($pagePendingIds, $selected)) === 0)
                            @disabled(count($pagePendingIds) === 0)
                            class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/30">
                    </th>
                    @endif

                    <th class="px-3 py-2">Sl</th>
                    <th class="px-3 py-2">Account No</th>
                    <th class="px-3 py-2">Pran No</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">DOB</th>
                    <th class="px-3 py-2">Department</th>
                    <th class="px-3 py-2">Designation</th>
                    <th class="px-3 py-2">DDO</th>
                    <th class="px-3 py-2">Status</th>
                    @if (in_array($mode, ['pending', 'finalized']))
                    <th class="px-3 py-2 text-right">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                @forelse ($subscribers as $sub)
                <tr wire:key="sub-{{ $sub->id }}" class="text-sm transition hover:bg-slate-50 dark:hover:bg-white/5">
                    @if ($mode === 'pending')
                    <td class="px-3 py-2">
                        @if ($sub->save_flag === 'T')
                        <input type="checkbox" wire:model.live="selected" value="{{ $sub->id }}"
                            class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/30">
                        @endif
                    </td>
                    @endif

                    <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ $subscribers->firstItem() + $loop->index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900 dark:text-white">
                        @if ($sub->save_flag === 'T')
                        <span class="rounded-md bg-amber-300 px-2 py-0.5 text-xs font-semibold text-amber-900 dark:bg-amber-500/20 dark:text-amber-300">Pending</span>
                        @else
                        {{ $sub->account_no }}
                        @endif
                    </td>
                    <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $sub->pran ? number_format($sub->pran->pran_no, 0, '.', '') : '—' }}</td>
                    <td class="px-3 py-2">
                        <a href="{{ route('accounts.show', $sub->id) }}" wire:navigate
                            class="font-medium text-indigo-600 hover:underline dark:text-indigo-300">{{ $sub->name }}</a>
                    </td>
                    <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $sub->dob?->format('d-m-Y') ?? '—' }}</td>
                    <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $departments[trim($sub->nameofdept)] ?? trim($sub->nameofdept) }}</td>
                    <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $sub->designationMaster?->designation ?? '—' }}</td>
                    <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $sub->ddo?->ddo_name ?? '—' }}</td>
                    <td class="px-3 py-2">
                        @if ($sub->save_flag === 'F')
                        <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">Finalized</span>
                        @else
                        <span class="rounded-md bg-amber-300 px-2 py-0.5 text-xs font-semibold text-amber-900 dark:bg-amber-500/20 dark:text-amber-300">Pending</span>
                        @endif
                    </td>
                    @if (in_array($mode, ['pending', 'finalized']))
                    <td class="px-3 py-2 text-right">
                        @can('entrysection.edit_issued_account')
                        <a href="{{ route('accounts.edit', $sub->id) }}" wire:navigate
                            class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                            </svg>
                            Edit
                        </a>
                        @endcan
                    </td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="11" class="px-4 py-10 text-center text-sm text-slate-400">No subscribers found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Page links --}}
    <div class="mt-4">
        {{ $subscribers->links() }}
    </div>
</div>
