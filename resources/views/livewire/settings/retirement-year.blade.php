<div class="mx-auto max-w-lg">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Retirement Year</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">The retirement age used across the system.</p>
    </div>

    <form wire:submit="save"
        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
        <div>
            <label for="year" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Retirement age (years)</label>
            <input wire:model="year" id="year" type="number" min="40" max="80"
                class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
            @error('year') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
        </div>

        <div class="mt-5">
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                class="rounded-xl bg-gradient-to-r from-indigo-500 to-sky-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:from-indigo-400 hover:to-sky-400 disabled:cursor-not-allowed disabled:opacity-70">
                <span wire:loading.remove wire:target="save">Save changes</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </form>
</div>
