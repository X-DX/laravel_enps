<div class="mx-auto max-w-4xl">
    @php
        $input = 'block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white';
        $label = 'mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300';
        $err = 'mt-1.5 text-sm text-rose-600 dark:text-rose-400';
        $card = 'rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.03]';
        $head = 'mb-4 flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white';
        $accent = '<span class="h-4 w-1 rounded-full bg-gradient-to-b from-indigo-500 to-sky-500"></span>';

        $treasuryOptions = $treasuries->map(fn ($t) => ['value' => $t->treasury_code, 'label' => $t->treasury_name . ' (' . $t->treasury_code . ')']);
        $bankOptions = $banks->map(fn ($b) => ['value' => $b->bank_code, 'label' => trim($b->bank_name) . ' — ' . trim($b->branch_name)]);
        $purposeOptions = $purposes->map(fn ($p) => ['value' => $p->pid, 'label' => trim($p->purpose)]);
        $ddoOptions = $ddos->map(fn ($d) => ['value' => $d->ddo_sl, 'label' => $d->ddo_name . ($d->ddo_code ? ' (' . $d->ddo_code . ')' : '')]);
    @endphp

    <x-breadcrumbs class="mb-4" :crumbs="['Entry Section' => null, 'First Register' => null]" current="Entry First Register" />

    <div class="mb-5">
        <h1 class="font-display text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Entry First Register</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Record an incoming receipt / draft. Saved as pending — finalize it later.</p>
    </div>

    <form wire:submit="save" class="space-y-5">
        {{-- Source --}}
        <div class="{{ $card }}">
            <h2 class="{{ $head }}">{!! $accent !!} Source</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="{{ $label }}">Treasury Location</label>
                    <x-searchable-select model="treasuryCode" :options="$treasuryOptions" placeholder="Select treasury…" :live="true" />
                    @error('treasuryCode') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $label }}">DDO</label>
                    <x-searchable-select model="ddocode" :options="$ddoOptions" :placeholder="$treasuryCode === '' ? 'Select a treasury first' : 'Select DDO…'" :disabled="$treasuryCode === ''" wire:key="ddo-{{ $treasuryCode }}" />
                    @error('ddocode') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="{{ $label }}">Draw Bank</label>
                    <x-searchable-select model="drawBankCode" :options="$bankOptions" placeholder="Select bank…" />
                    @error('drawBankCode') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Order & Draft --}}
        <div class="{{ $card }}">
            <h2 class="{{ $head }}">{!! $accent !!} Order &amp; Draft / Receipt</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="orderNo" class="{{ $label }}">Order / Letter No</label>
                    <input wire:model="orderNo" id="orderNo" type="text" class="{{ $input }}">
                    @error('orderNo') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="orderDate" class="{{ $label }}">Order / Letter Date</label>
                    <input wire:model="orderDate" id="orderDate" type="date" class="{{ $input }}">
                    @error('orderDate') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2 flex items-center gap-2">
                    <input wire:model.live="isDraft" id="isDraft" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/30">
                    <label for="isDraft" class="text-sm text-slate-700 dark:text-slate-300">This is a <span class="font-semibold">Draft</span> (leave unticked for a <span class="font-semibold">Receipt</span>)</label>
                </div>
                <div>
                    <label for="draftNo" class="{{ $label }}">{{ $isDraft ? 'Draft No' : 'Receipt No' }}</label>
                    <input wire:model="draftNo" id="draftNo" type="text" inputmode="numeric" class="{{ $input }}">
                    @error('draftNo') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="draftDate" class="{{ $label }}">{{ $isDraft ? 'Draft Date' : 'Receipt Date' }}</label>
                    <input wire:model="draftDate" id="draftDate" type="date" class="{{ $input }}">
                    @error('draftDate') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Amount & Classification --}}
        <div class="{{ $card }}">
            <h2 class="{{ $head }}">{!! $accent !!} Amount &amp; Classification</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="amount" class="{{ $label }}">Amount (₹)</label>
                    <input wire:model="amount" id="amount" type="text" inputmode="numeric" class="{{ $input }}">
                    @error('amount') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="contributionType" class="{{ $label }}">Contribution Type</label>
                    <select wire:model="contributionType" id="contributionType" class="{{ $input }}">
                        <option value="">Select…</option>
                        <option value="SC">Single Contribution</option>
                        <option value="DC">Double Contribution</option>
                    </select>
                    @error('contributionType') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $label }}">Pension Type</label>
                    <div class="flex items-center gap-6 pt-1">
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input wire:model="pensionType" type="radio" value="N" class="text-indigo-600 focus:ring-indigo-500/30"> NPS
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input wire:model="pensionType" type="radio" value="U" class="text-indigo-600 focus:ring-indigo-500/30"> UPS
                        </label>
                    </div>
                    @error('pensionType') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $label }}">Purpose</label>
                    <x-searchable-select model="purpose" :options="$purposeOptions" placeholder="Select purpose…" />
                    @error('purpose') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Submit --}}
        <div class="{{ $card }} flex flex-wrap items-center justify-end gap-3">
            @if ($showForceSave)
                <p class="mr-auto text-sm text-amber-600 dark:text-amber-400">A matching draft/receipt already exists.</p>
                <button type="button" wire:click="forceSave" wire:confirm="Save anyway? A receipt with this number and date already exists."
                    class="rounded-xl border border-amber-300 bg-amber-50 px-5 py-2.5 text-sm font-semibold text-amber-700 transition hover:bg-amber-100 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                    Save anyway
                </button>
            @endif
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                class="rounded-xl bg-gradient-to-r from-indigo-500 to-sky-500 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:from-indigo-400 hover:to-sky-400 disabled:cursor-not-allowed disabled:opacity-70">
                <span wire:loading.remove wire:target="save">Save entry</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </form>
</div>
