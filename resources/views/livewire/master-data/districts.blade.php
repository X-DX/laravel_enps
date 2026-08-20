<div class="mx-auto max-w-4xl">
    {{-- Header --}}
    <x-breadcrumbs class="mb-4" :crumbs="['Admin Section' => null, 'Master Entry' => null]" current="District Master" />

    <div class="mb-6 flex items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">District Master</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Reference list of districts used across the system.</p>
        </div>
        <button wire:click="create" type="button"
            class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-sky-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:from-indigo-400 hover:to-sky-400">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Add district
        </button>
    </div>

    {{-- Create / edit panel --}}
    @if ($showForm)
    <form wire:submit="save"
        class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
        <h2 class="mb-4 text-sm font-semibold text-slate-900 dark:text-white">
            {{ $editingCode ? 'Edit district' : 'New district' }}
        </h2>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="state_code" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">State</label>
                <select wire:model="state_code" id="state_code"
                    class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
                    <option value="">— Select state —</option>
                    @foreach ($states as $state)
                    <option value="{{ $state->state_code }}">{{ $state->state_name }}</option>
                    @endforeach
                </select>
                @error('state_code') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="dist_code" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">District code</label>
                <input wire:model="dist_code" id="dist_code" type="number" @if ($editingCode) readonly @endif
                    class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 read-only:bg-slate-100 read-only:text-slate-500 dark:border-white/10 dark:bg-white/5 dark:text-white dark:read-only:bg-white/10">
                @error('dist_code') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                @if ($editingCode) <p class="mt-1.5 text-xs text-slate-400">The code identifies the district and can't be changed.</p> @endif
            </div>
            <div>
                <label for="dist_name" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">District name</label>
                <input wire:model="dist_name" id="dist_name" type="text"
                    class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
                @error('dist_name') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-5 flex items-center gap-3">
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                class="rounded-xl bg-gradient-to-r from-indigo-500 to-sky-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:from-indigo-400 hover:to-sky-400 disabled:cursor-not-allowed disabled:opacity-70">
                <span wire:loading.remove wire:target="save">Save</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
            <button wire:click="$set('showForm', false)" type="button"
                class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">
                Cancel
            </button>
        </div>
    </form>
    @endif

    {{-- Toolbar: search · per-page · Excel --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="relative min-w-56 flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search code or name…"
                class="block w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
        </div>

        {{-- Per-page --}}
        <div class="flex items-center gap-2">
            <label for="perPage" class="text-sm text-slate-500 dark:text-slate-400">Show</label>
            <select wire:model.live="perPage" id="perPage"
                class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
                @foreach ([10, 25, 50, 100] as $size)
                <option value="{{ $size }}">{{ $size }}</option>
                @endforeach
            </select>
        </div>

        {{-- Excel export --}}
        <button wire:click="export" type="button"
            class="inline-flex items-center gap-2 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            Excel
        </button>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-white/10">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <th class="px-6 py-3">State</th>
                    <th class="px-6 py-3">Code</th>
                    <th class="px-6 py-3">Name</th>
                    <th class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                @forelse ($districts as $district)
                <tr wire:key="dist-{{ $district->dist_code }}" class="text-sm transition hover:bg-slate-50 dark:hover:bg-white/5">
                    <td class="px-6 py-3 text-slate-600 dark:text-slate-300">
                        @if ($district->state)
                            {{ $district->state->state_name }}
                        @else
                            <span class="text-slate-400 dark:text-slate-500">— not set —</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 font-medium text-slate-900 dark:text-white">{{ $district->dist_code }}</td>
                    <td class="px-6 py-3 text-slate-600 dark:text-slate-300">{{ $district->dist_name }}</td>
                    <td class="px-6 py-3">
                        <div class="flex items-center justify-end gap-2">
                            <button wire:click="edit({{ $district->dist_code }})" type="button"
                                class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-indigo-600 transition hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-indigo-500/10">Edit</button>
                            <button type="button"
                                @click="Swal.fire({
                                        title: 'Delete district {{ $district->dist_code }}?',
                                        text: '{{ addslashes($district->dist_name) }} will be removed. This cannot be undone.',
                                        icon: 'warning',
                                        showCancelButton: true,
                                        confirmButtonText: 'Yes, delete',
                                        confirmButtonColor: '#e11d48',
                                        cancelButtonColor: '#64748b',
                                    }).then((result) => { if (result.isConfirmed) { $wire.delete({{ $district->dist_code }}) } })"
                                class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-rose-600 transition hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">Delete</button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-10 text-center text-sm text-slate-400">
                        No districts found{{ $search ? ' for "'.$search.'"' : '' }}.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $districts->links() }}
    </div>
</div>