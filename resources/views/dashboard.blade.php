<x-layouts.app :title="'eNPS — Dashboard'">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Welcome back, {{ auth()->user()->username }}.</p>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
            </span>
            <div>
                <p class="text-sm font-medium text-slate-900 dark:text-white">You are signed in.</p>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Role: <span class="font-medium">{{ auth()->user()->role_flag }}</span>
                    &middot; User ID: <span class="font-medium">{{ auth()->user()->user_id }}</span>
                </p>
            </div>
        </div>
        <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">
            This is a placeholder. Role-based dashboards and modules arrive in later milestones (M2+).
        </p>
    </div>
</x-layouts.app>
