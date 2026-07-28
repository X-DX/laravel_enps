<div class="mx-auto max-w-md">
    <div class="mb-6 text-center">
        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-sky-500 text-white shadow-lg shadow-indigo-500/30">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
        </div>
        <h1 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-white">Update your password</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">For your security, please set a new password before continuing.</p>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
        <form wire:submit="update" class="space-y-5">
            {{-- Current password --}}
            <div>
                <label for="current_password" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Current password</label>
                <input
                    wire:model="current_password"
                    id="current_password" type="password" autocomplete="current-password"
                    class="block w-full rounded-xl border border-slate-300 px-3 py-2.5 text-slate-900 placeholder-slate-400 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 @error('current_password') border-rose-400 @enderror"
                    placeholder="Enter your current password">
                @error('current_password') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>

            {{-- New password --}}
            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">New password</label>
                <input
                    wire:model="password"
                    id="password" type="password" autocomplete="new-password"
                    class="block w-full rounded-xl border border-slate-300 px-3 py-2.5 text-slate-900 placeholder-slate-400 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 @error('password') border-rose-400 @enderror"
                    placeholder="At least 8 characters">
                @error('password') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>

            {{-- Confirm --}}
            <div>
                <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Confirm new password</label>
                <input
                    wire:model="password_confirmation"
                    id="password_confirmation" type="password" autocomplete="new-password"
                    class="block w-full rounded-xl border border-slate-300 px-3 py-2.5 text-slate-900 placeholder-slate-400 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400"
                    placeholder="Re-enter the new password">
            </div>

            <button
                type="submit"
                wire:loading.attr="disabled" wire:target="update"
                class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-sky-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:from-indigo-400 hover:to-sky-400 focus:outline-none focus:ring-2 focus:ring-indigo-400/50 disabled:cursor-not-allowed disabled:opacity-70">
                <span wire:loading.remove wire:target="update">Update password</span>
                <span wire:loading wire:target="update">Updating…</span>
            </button>
        </form>
    </div>
</div>
