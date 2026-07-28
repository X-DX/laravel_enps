<div class="mx-auto max-w-4xl">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Manage user permissions</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Grant or revoke what each operator can access.</p>
    </div>

    {{-- User picker --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
        <label for="userId" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">User</label>
        <select wire:model.live="userId" id="userId"
            class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
            <option value="">Select a user…</option>
            @foreach ($users as $u)
                <option value="{{ $u->user_id }}">{{ $u->username }} ({{ $u->user_id }})</option>
            @endforeach
        </select>
    </div>

    @if ($selectedUser)
        <form wire:submit="save" class="mt-6 space-y-6">
            @if ($selectedUser->isAdmin())
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                    This user is an <strong>Administrator</strong> and bypasses all permission checks — grants below aren't required for them.
                </div>
            @endif

            @foreach ($groups as $groupKey => $perms)
                <fieldset class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
                    <legend class="px-2 text-sm font-semibold text-slate-900 dark:text-white">
                        {{ \App\Support\Navigation\SidebarMenu::titleFor((string) $groupKey) }}
                    </legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($perms as $perm)
                            <label class="flex items-center gap-3 rounded-lg px-2 py-1.5 transition hover:bg-slate-50 dark:hover:bg-white/5">
                                <input type="checkbox" wire:model="selected" value="{{ $perm->id }}"
                                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-2 focus:ring-indigo-500/40 dark:border-white/20 dark:bg-white/5">
                                <span class="text-sm text-slate-700 dark:text-slate-300">{{ $perm->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach

            <div class="flex items-center gap-4">
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="rounded-xl bg-gradient-to-r from-indigo-500 to-sky-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:from-indigo-400 hover:to-sky-400 disabled:cursor-not-allowed disabled:opacity-70">
                    <span wire:loading.remove wire:target="save">Save permissions</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
                @if ($saved)
                    <span class="text-sm font-medium text-emerald-600 dark:text-emerald-400">Saved.</span>
                @endif
            </div>
        </form>
    @endif
</div>
