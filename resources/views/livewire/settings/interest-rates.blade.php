<div class="mx-auto max-w-2xl">
    {{-- Header --}}
    <div class="mb-6 flex items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Interest Rate</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">The interest rate declared for each financial year.</p>
        </div>
        <button wire:click="create" type="button"
            class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-sky-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:from-indigo-400 hover:to-sky-400">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            Add rate
        </button>
    </div>

    {{-- Create / edit panel --}}
    @if ($showForm)
        <form wire:submit="save"
            class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
            <h2 class="mb-4 text-sm font-semibold text-slate-900 dark:text-white">
                {{ $editingId ? 'Edit rate' : 'New rate' }}
            </h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="fin_year" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Financial year</label>
                    <input wire:model="fin_year" id="fin_year" type="text" maxlength="10" placeholder="2015-2016" @if ($editingId) readonly @endif
                        class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 read-only:bg-slate-100 read-only:text-slate-500 dark:border-white/10 dark:bg-white/5 dark:text-white dark:read-only:bg-white/10">
                    @error('fin_year') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="rate" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Rate (%)</label>
                    <input wire:model="rate" id="rate" type="number" step="0.01" min="0"
                        class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
                    @error('rate') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
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

    {{-- Table --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-white/10">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <th class="px-6 py-3">Financial Year</th>
                    <th class="px-6 py-3">Rate</th>
                    <th class="px-6 py-3 text-right">Edit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                @forelse ($rates as $r)
                    <tr wire:key="rate-{{ $r->id }}" class="text-sm transition hover:bg-slate-50 dark:hover:bg-white/5">
                        <td class="px-6 py-3 font-medium text-slate-900 dark:text-white">{{ $r->fin_year }}</td>
                        <td class="px-6 py-3 text-slate-600 dark:text-slate-300">{{ rtrim(rtrim(number_format($r->rate, 2), '0'), '.') }}%</td>
                        <td class="px-6 py-3 text-right">
                            <button wire:click="edit({{ $r->id }})" type="button"
                                class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-indigo-600 transition hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-indigo-500/10">Edit</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-10 text-center text-sm text-slate-400">No rates yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
