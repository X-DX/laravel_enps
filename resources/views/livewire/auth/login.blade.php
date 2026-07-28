<div class="w-full max-w-md">
    {{-- Brand --}}
    <div class="mb-8 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-sky-500 shadow-lg shadow-indigo-500/30">
            <a href="{{ route('home') }}" class="text-lg font-bold text-white">eN</a>
        </div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">eNPS</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">National Pension System</p>
    </div>

    {{-- Card --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-xl dark:border-white/10 dark:bg-white/5 dark:shadow-2xl dark:backdrop-blur-xl">
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Sign in</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Welcome back. Please enter your credentials.</p>
        </div>

        <form wire:submit="login" class="space-y-5">
            {{-- User ID --}}
            <div>
                <label for="userId" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">User ID</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 dark:text-slate-500">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                    </span>
                    <input
                        wire:model="userId"
                        id="userId" type="text" autocomplete="username" autofocus
                        class="block w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-3 text-slate-900 placeholder-slate-400 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-white/10 dark:focus:ring-indigo-500/40 @error('userId') border-rose-400 dark:border-rose-500/60 @enderror"
                        placeholder="Enter your user ID">
                </div>
                @error('userId') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>

            {{-- Password --}}
            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Password</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 dark:text-slate-500">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                    </span>
                    <input
                        wire:model="password"
                        id="password" type="password" autocomplete="current-password"
                        class="block w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-3 text-slate-900 placeholder-slate-400 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-white/10 dark:focus:ring-indigo-500/40 @error('password') border-rose-400 dark:border-rose-500/60 @enderror"
                        placeholder="Enter your password">
                </div>
                @error('password') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>

            {{-- Captcha --}}
            <div x-data="{ reload() { $refs.captchaImg.src = '{{ route('captcha') }}?r=' + Date.now(); $wire.set('captcha', ''); } }">
                <label for="captcha" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Captcha</label>
                <div class="mb-2 flex items-center gap-3">
                    <div wire:ignore class="overflow-hidden rounded-xl border border-slate-200 bg-slate-900 dark:border-white/10">
                        <img x-ref="captchaImg" src="{{ route('captcha') }}" alt="CAPTCHA challenge" class="h-14 w-[200px]">
                    </div>
                    <button type="button" @click="reload"
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-300 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white"
                        title="Get a new captcha" aria-label="Refresh captcha">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992V4.356M2.985 19.644v-4.992h4.992m11.336-6.32a8.25 8.25 0 0 0-14.15-2.28m-.09 8.6a8.25 8.25 0 0 0 14.15 2.28" /></svg>
                    </button>
                </div>
                <input
                    wire:model="captcha"
                    id="captcha" type="text" autocomplete="off" autocapitalize="characters"
                    class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 placeholder-slate-400 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-white/10 dark:focus:ring-indigo-500/40 @error('captcha') border-rose-400 dark:border-rose-500/60 @enderror"
                    placeholder="Type the characters above">
                @error('captcha') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>

            {{-- Submit --}}
            <button
                type="submit"
                wire:loading.attr="disabled" wire:target="login"
                class="group relative flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-sky-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:from-indigo-400 hover:to-sky-400 focus:outline-none focus:ring-2 focus:ring-indigo-400/50 disabled:cursor-not-allowed disabled:opacity-70">
                <span wire:loading.remove wire:target="login">Sign in</span>
                <span wire:loading wire:target="login" class="flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Signing in…
                </span>
            </button>
        </form>
    </div>

    <p class="mt-6 text-center text-xs text-slate-500 dark:text-slate-500">
        &copy; {{ date('Y') }} eNPS &middot; National Pension System
    </p>
</div>
