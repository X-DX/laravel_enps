<div class="mx-auto max-w-6xl">
    {{-- Title --}}
    <div class="mb-6 flex items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">View All Accounts</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Every subscriber and their allotted account number.</p>
        </div>
        <a href="{{ route('accounts.issue') }}" wire:navigate
            class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-sky-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:from-indigo-400 hover:to-sky-400">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            New Account
        </a>
    </div>


    {{-- Search · Status · Show --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="relative min-w-56 flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search name or account no…"
                class="block w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
        </div>

        <div class="flex items-center gap-2">
            <label for="status" class="text-sm text-slate-500 dark:text-slate-400">Status</label>
            <select wire:model.live="status" id="status"
                class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
                <option value="">All</option>
                <option value="T">Pending</option>
                <option value="F">Finalized</option>
            </select>
        </div>

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
        @can('entrysection.issue_account')
        <button wire:click="finalize" type="button" @disabled(count($selected)===0)
            wire:confirm="Finalize {{ count($selected) }} selected subscriber(s)? This allots their account numbers and can't be undone."
            class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-sky-500 px-4 py-2.5 text-sm font-semibold text-white shadow transition hover:from-indigo-400 hover:to-sky-400 disabled:cursor-not-allowed disabled:opacity-50">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
            Finalize selected ({{ count($selected) }})
        </button>
        @endcan


    </div>

    {{-- Table --}}
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-white/10">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    @can('entrysection.issue_account')
                    <th class="px-4 py-3">
                        <input type="checkbox" wire:click="toggleSelectAll"
                            @checked(count($pagePendingIds)> 0 && count(array_diff($pagePendingIds, $selected)) === 0)
                        @disabled(count($pagePendingIds) === 0)
                        class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/30">
                    </th>
                    @endcan

                    <th class="px-4 py-3">Sl</th>
                    <th class="px-4 py-3">Account No</th>
                    <th class="px-4 py-3">Pran No</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">DOB</th>
                    <th class="px-4 py-3">Dept Code</th>
                    <th class="px-4 py-3">Department</th>
                    <th class="px-4 py-3">Designation</th>
                    <th class="px-4 py-3">DDO</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                @forelse ($subscribers as $sub)
                <tr wire:key="sub-{{ $sub->id }}" class="text-sm transition hover:bg-slate-50 dark:hover:bg-white/5">
                    @can('entrysection.issue_account')
                    <td class="px-4 py-3">
                        @if ($sub->save_flag === 'T')
                        <input type="checkbox" wire:model.live="selected" value="{{ $sub->id }}"
                            class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/30">
                        @endif
                    </td>
                    @endcan

                    <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $subscribers->firstItem() + $loop->index }}</td>
                    <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">
                        @if ($sub->save_flag === 'T')
                        <span class="rounded-md bg-amber-300 px-2 py-0.5 text-xs font-semibold text-amber-900 dark:bg-amber-500/20 dark:text-amber-300">Pending</span>
                        @else
                        {{ $sub->account_no }}
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $sub->pran ? number_format($sub->pran->pran_no, 0, '.', '') : '—' }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('accounts.show', $sub->id) }}" wire:navigate
                            class="font-medium text-indigo-600 hover:underline dark:text-indigo-300">{{ $sub->name }}</a>
                    </td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $sub->dob?->format('d-m-Y') ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ trim($sub->nameofdept) }}</td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $departments[trim($sub->nameofdept)] ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $sub->designationMaster?->designation ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $sub->ddo?->ddo_name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        @if ($sub->save_flag === 'F')
                        <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">Finalized</span>
                        @else
                        <span class="rounded-md bg-amber-300 px-2 py-0.5 text-xs font-semibold text-amber-900 dark:bg-amber-500/20 dark:text-amber-300">Pending</span>
                        @endif
                    </td>

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