<div class="mx-auto max-w-5xl">
    {{-- Header --}}
    <div class="mb-6 flex items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">DDO Master</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Drawing &amp; Disbursing Officers, each belonging to a treasury.</p>
        </div>
        <button wire:click="create" type="button"
            class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-sky-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:from-indigo-400 hover:to-sky-400">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Add DDO
        </button>
    </div>

    {{-- Create / edit panel --}}
    @if ($showForm)
    <form wire:submit="save"
        class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
        <h2 class="mb-4 text-sm font-semibold text-slate-900 dark:text-white">
            {{ $editingCode ? 'Edit DDO' : 'New DDO' }}
        </h2>

        @if ($editingCode)
        <!-- <p class="mb-4 text-xs text-slate-400">DDO code <span class="font-semibold text-slate-500 dark:text-slate-300">#{{ $editingCode }}</span> (auto-generated, can't be changed).</p> -->
        <p class="mb-4 text-xs text-slate-400">Serial no. <span class="font-semibold text-slate-500 dark:text-slate-300">#{{ $editingCode }}</span> (assigned automatically, can't be changed).</p>
        @else
        <p class="mb-4 text-xs text-slate-400">The DDO code is assigned automatically on save.</p>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="form_district" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">District</label>
                <select wire:model.live="form_district" id="form_district"
                    class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
                    <option value="">Select a district…</option>
                    @foreach ($districts as $d)
                    <option value="{{ $d->dist_code }}">{{ $d->dist_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="treasury_code" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Treasury</label>
                <select wire:model="treasury_code" id="treasury_code" @disabled($form_district==='' )
                    class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-white/10 dark:bg-white/5 dark:text-white dark:disabled:bg-white/5">
                    <option value="">{{ $form_district === '' ? 'Select a district first' : 'Select a treasury…' }}</option>
                    @foreach ($formTreasuries as $t)
                    <option value="{{ $t->treasury_code }}">{{ $t->treasury_name }} ({{ $t->treasury_code }})</option>
                    @endforeach
                </select>
                @error('treasury_code') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="ddo_code" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">DDO code</label>
                <input wire:model="ddo_code" id="ddo_code" type="text" inputmode="numeric" maxlength="7" placeholder="7 digits, e.g. 0012345"
                    class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
                @error('ddo_code') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="ddo_name" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">DDO name</label>
                <input wire:model="ddo_name" id="ddo_name" type="text" maxlength="150"
                    class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
                @error('ddo_name') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
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

    {{-- Toolbar: cascading District → Location filter · per-page · Excel --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2">
            <label for="filterDistrict" class="whitespace-nowrap text-sm font-medium text-slate-600 dark:text-slate-300">District</label>
            <select wire:model.live="filterDistrict" id="filterDistrict"
                class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
                <option value="">All districts</option>
                @foreach ($districts as $d)
                <option value="{{ $d->dist_code }}">{{ $d->dist_name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center gap-2">
            <label for="filterTreasury" class="whitespace-nowrap text-sm font-medium text-slate-600 dark:text-slate-300">Treasury</label>
            <select wire:model.live="filterTreasury" id="filterTreasury" @disabled($filterDistrict==='' )
                class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-white/10 dark:bg-white/5 dark:text-white dark:disabled:bg-white/5">
                <option value="">{{ $filterDistrict === '' ? 'Select a district first' : 'All treasuries' }}</option>
                @foreach ($filterTreasuries as $t)
                <option value="{{ $t->treasury_code }}">{{ $t->treasury_name }} ({{ $t->treasury_code }})</option>
                @endforeach
            </select>
        </div>

        <div class="ml-auto flex items-center gap-2">
            <label for="perPage" class="text-sm text-slate-500 dark:text-slate-400">Show</label>
            <select wire:model.live="perPage" id="perPage"
                class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
                @foreach ([10, 25, 50, 100] as $size)
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
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-white/10">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <th class="px-6 py-3">DDO Sl</th>
                    <th class="px-6 py-3">DDO Code</th>
                    <th class="px-6 py-3">DDO Name</th>
                    <th class="px-6 py-3">Treasury</th>
                    <th class="px-6 py-3">District</th>
                    <th class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                @forelse ($ddos as $ddo)
                <tr wire:key="ddo-{{ $ddo->ddo_sl }}" class="text-sm transition hover:bg-slate-50 dark:hover:bg-white/5">
                    <td class="px-6 py-3 font-medium text-slate-900 dark:text-white">{{ $ddo->ddo_sl }}</td>
                    <td class="px-6 py-3 text-slate-600 dark:text-slate-300">{{ $ddo->ddo_code ?? '—' }}</td>
                    <td class="px-6 py-3 text-slate-600 dark:text-slate-300">{{ $ddo->ddo_name }}</td>
                    <td class="px-6 py-3 text-slate-600 dark:text-slate-300">
                        @if ($ddo->treasury)
                        {{ $ddo->treasury->treasury_name }}
                        @else
                        <span class="text-slate-400 dark:text-slate-500">— not set —</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-slate-600 dark:text-slate-300">{{ $ddo->treasury?->district?->dist_name ?? '—' }}</td>
                    <td class="px-6 py-3">
                        <div class="flex items-center justify-end gap-2">
                            <button wire:click="edit({{ $ddo->ddo_sl }})" type="button"
                                class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-indigo-600 transition hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-indigo-500/10">Edit</button>
                            <button type="button"
                                @click="Swal.fire({
                                        title: 'Delete DDO {{ $ddo->ddo_sl }}?',
                                        text: '{{ addslashes($ddo->ddo_name) }} will be removed. This cannot be undone.',
                                        icon: 'warning',
                                        showCancelButton: true,
                                        confirmButtonText: 'Yes, delete',
                                        confirmButtonColor: '#e11d48',
                                        cancelButtonColor: '#64748b',
                                    }).then((result) => { if (result.isConfirmed) { $wire.delete({{ $ddo->ddo_sl }}) } })"
                                class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-rose-600 transition hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">Delete</button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-400">No DDOs found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $ddos->links() }}
    </div>
</div>