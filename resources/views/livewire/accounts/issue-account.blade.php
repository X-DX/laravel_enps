<div class="mx-auto max-w-4xl">
    @php $backRoute = $editingId ? ($isFinalized ? 'accounts.finalized' : 'accounts.pending') : 'accounts.index'; @endphp

    {{-- Back to the list --}}
    <a href="{{ route($backRoute) }}" wire:navigate
        class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-300">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
        Back to accounts
    </a>

    <div class="mt-2 mb-5">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $editingId ? 'Edit Account' : 'Issue Account' }}</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            {{ $editingId ? 'Update this subscriber’s details.' : 'Enter a new subscriber. Saved as a draft — the account number is generated at finalize.' }}
        </p>
    </div>

    @php
        // Reusable class strings, so each field below stays short and consistent.
        $input =
            'block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-white/10 dark:bg-white/5 dark:text-white dark:disabled:bg-white/5';
        $label = 'mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300';
        $err = 'mt-1.5 text-sm text-rose-600 dark:text-rose-400';
        $card = 'rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.03]';
        $head = 'mb-4 flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white';
        $accent = '<span class="h-4 w-1 rounded-full bg-gradient-to-b from-indigo-500 to-sky-500"></span>';

        // Options for the searchable dropdowns, pre-formatted as ['value' => ..., 'label' => ...].
        $desigOptions = $designations->map(fn($d) => ['value' => $d->designation_id, 'label' => $d->designation]);
        $deptOptions = $departments->map(fn($d) => ['value' => $d->dept_code, 'label' => $d->dept_name]);
        $treasuryOptions = $treasuries->map(
            fn($t) => ['value' => $t->treasury_code, 'label' => $t->treasury_name . ' (' . $t->treasury_code . ')'],
        );
        $ddoOptions = $ddos->map(
            fn($d) => [
                'value' => $d->ddo_sl,
                'label' => $d->ddo_name . ($d->ddo_code ? ' (' . $d->ddo_code . ')' : ''),
            ],
        );
    @endphp

    <form wire:submit="save" class="space-y-5">

        {{-- Personal --}}
        <div class="{{ $card }}">
            <h2 class="{{ $head }}">{!! $accent !!} Personal</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="name" class="{{ $label }}">Employee Name</label>
                    <input wire:model="name" id="name" type="text" class="{{ $input }}">
                    @error('name')
                        <p class="{{ $err }}">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="father_name" class="{{ $label }}">Father Name</label>
                    <input wire:model="father_name" id="father_name" type="text" @disabled($single_mother_flag)
                        class="{{ $input }}">
                    @error('father_name')
                        <p class="{{ $err }}">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="mother_name" class="{{ $label }}">Mother Name</label>
                    <input wire:model="mother_name" id="mother_name" type="text" class="{{ $input }}">
                </div>
                <div class="flex items-center gap-2">
                    <input wire:model.live="single_mother_flag" id="single_mother_flag" type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/30">
                    <label for="single_mother_flag" class="text-sm text-slate-700 dark:text-slate-300">Single
                        Mother</label>
                </div>
                <div>
                    <label for="dob" class="{{ $label }}">Date of Birth</label>
                    <input wire:model.live="dob" id="dob" type="date" class="{{ $input }}">
                    @error('dob')
                        <p class="{{ $err }}">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Service --}}
        <div class="{{ $card }}">
            <h2 class="{{ $head }}">{!! $accent !!} Service</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="appnt_ord_no" class="{{ $label }}">Appointment Order</label>
                    <input wire:model="appnt_ord_no" id="appnt_ord_no" type="text" class="{{ $input }}">
                    @error('appnt_ord_no')
                        <p class="{{ $err }}">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="doapptorder" class="{{ $label }}">Appointment Date</label>
                    <input wire:model="doapptorder" id="doapptorder" type="date" class="{{ $input }}">
                    @error('doapptorder')
                        <p class="{{ $err }}">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="doj" class="{{ $label }}">Date of Joining</label>
                    <input wire:model="doj" id="doj" type="date" class="{{ $input }}">
                    @error('doj')
                        <p class="{{ $err }}">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="dor" class="{{ $label }}">Retirement Date <span
                            class="text-xs text-slate-400">(auto-filled)</span></label>
                    <input wire:model="dor" id="dor" type="date" class="{{ $input }}">
                    @error('dor')
                        <p class="{{ $err }}">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="{{ $label }}">Designation</label>
                    <x-searchable-select model="designation" :options="$desigOptions" placeholder="Select designation…" />
                    @error('designation')
                        <p class="{{ $err }}">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="{{ $label }}">Department @if ($isFinalized) <span class="text-xs font-normal text-slate-400">(locked)</span> @endif</label>
                    <x-searchable-select model="nameofdept" :options="$deptOptions" placeholder="Select department…" :disabled="$isFinalized" />
                    @error('nameofdept')
                        <p class="{{ $err }}">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="{{ $label }}">Treasury Location</label>
                    <x-searchable-select model="treasury_code" :options="$treasuryOptions" placeholder="Select treasury…"
                        :live="true" />
                    @error('treasury_code')
                        <p class="{{ $err }}">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="{{ $label }}">DDO</label>
                    <x-searchable-select model="ddocode" :options="$ddoOptions" :placeholder="$treasury_code === '' ? 'Select a treasury first' : 'Select DDO…'" :disabled="$treasury_code === ''"
                        wire:key="ddo-{{ $treasury_code }}" />
                    @error('ddocode')
                        <p class="{{ $err }}">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Pension & Pay --}}
        <div class="{{ $card }}">
            <h2 class="{{ $head }}">{!! $accent !!} Pension &amp; Pay</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="{{ $label }}">Pension Type @if ($isFinalized) <span class="text-xs font-normal text-slate-400">(locked)</span> @endif</label>
                    <div class="flex items-center gap-6 pt-1">
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input wire:model.live="pension_type" type="radio" value="N" @disabled($isFinalized)
                                class="text-indigo-600 focus:ring-indigo-500/30"> NPS
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input wire:model.live="pension_type" type="radio" value="U" @disabled($isFinalized)
                                class="text-indigo-600 focus:ring-indigo-500/30"> UPS
                        </label>
                    </div>
                    @error('pension_type')
                        <p class="{{ $err }}">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="pay"
                        class="{{ $label }}">{{ $pension_type === 'N' ? 'Basic + DA' : 'Basic Pay' }}</label>
                    <input wire:model="pay" id="pay" type="text" inputmode="numeric"
                        class="{{ $input }}">
                    @error('pay')
                        <p class="{{ $err }}">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="starting_month" class="{{ $label }}">Deduction Start Month</label>
                    <select wire:model="starting_month" id="starting_month" class="{{ $input }}">
                        <option value="">Select month…</option>
                        @foreach (['01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April', '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August', '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'] as $num => $mon)
                            <option value="{{ $num }}">{{ $mon }}</option>
                        @endforeach
                    </select>
                    @error('starting_month')
                        <p class="{{ $err }}">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="starting_fin_year" class="{{ $label }}">Deduction Start Year</label>
                    <select wire:model="starting_fin_year" id="starting_fin_year" class="{{ $input }}">
                        <option value="">Select year…</option>
                        @for ($y = (int) date('Y'); $y >= 2008; $y--)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endfor
                    </select>
                    @error('starting_fin_year')
                        <p class="{{ $err }}">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Nominees --}}
        <div class="{{ $card }}">
            <h2 class="{{ $head }}">{!! $accent !!} Nominees</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="name_nominee" class="{{ $label }}">1st Nominee</label>
                    <input wire:model="name_nominee" id="name_nominee" type="text" class="{{ $input }}">
                    @error('name_nominee')
                        <p class="{{ $err }}">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="name_nominee2" class="{{ $label }}">2nd Nominee</label>
                    <input wire:model="name_nominee2" id="name_nominee2" type="text"
                        class="{{ $input }}">
                </div>
                <div>
                    <label for="name_nominee3" class="{{ $label }}">3rd Nominee</label>
                    <input wire:model="name_nominee3" id="name_nominee3" type="text"
                        class="{{ $input }}">
                </div>
            </div>
        </div>

        {{-- Account number + submit --}}
        <div class="{{ $card }}">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <label class="{{ $label }}">Account No @if ($editingId && $isFinalized) <span class="text-xs font-normal text-slate-400">(locked)</span> @endif</label>
                    <p class="rounded-xl bg-slate-100 px-3 py-2.5 text-sm font-semibold text-slate-500 dark:bg-white/5 dark:text-slate-400">
                        {{ $editingId && $isFinalized ? $account_no : 'PENDING' }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $editingId && $isFinalized ? 'Frozen after finalize.' : 'Generated automatically on finalize.' }}</p>
                </div>
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="rounded-xl bg-gradient-to-r from-indigo-500 to-sky-500 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:from-indigo-400 hover:to-sky-400 disabled:cursor-not-allowed disabled:opacity-70">
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update' : 'Save draft' }}</span>
                    <span wire:loading wire:target="save">{{ $editingId ? 'Updating…' : 'Saving…' }}</span>
                </button>
            </div>
        </div>
    </form>
</div>
