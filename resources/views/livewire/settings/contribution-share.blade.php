<div class="mx-auto max-w-lg">
    <x-breadcrumbs class="mb-4" :crumbs="['Admin Section' => null, 'Others' => null]" current="Contribution Share" />

    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Contribution Share</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">The employee vs. government contribution split (%).</p>
    </div>

    <form wire:submit="save"
        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="emp_share" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Employee share (%)</label>
                <input wire:model.live="emp_share" id="emp_share" type="number" min="0" max="100"
                    class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
                @error('emp_share') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="govt_share" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Government share (%)</label>
                <input wire:model.live="govt_share" id="govt_share" type="number" min="0" max="100"
                    class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
                @error('govt_share') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>
        </div>

        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
            Combined contribution:
            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ (is_numeric($emp_share) ? (int) $emp_share : 0) + (is_numeric($govt_share) ? (int) $govt_share : 0) }}%</span>
        </p>

        <div class="mt-5">
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                class="rounded-xl bg-gradient-to-r from-indigo-500 to-sky-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:from-indigo-400 hover:to-sky-400 disabled:cursor-not-allowed disabled:opacity-70">
                <span wire:loading.remove wire:target="save">Save changes</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </form>
</div>
