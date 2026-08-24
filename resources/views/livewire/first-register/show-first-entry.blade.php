<div class="mx-auto max-w-4xl">
    @php
        $statusLabel = $entry->statusLabel();
        $isDraft = $entry->type === 'D';

        $rows = [
            [$isDraft ? 'Draft No' : 'Receipt No', $entry->draft_no],
            [$isDraft ? 'Draft Date' : 'Receipt Date', $entry->draft_date?->format('d-m-Y')],
            ['Order / Letter No', $entry->order_no],
            ['Order / Letter Date', $entry->order_date?->format('d-m-Y')],
            ['DDO', $entry->ddo?->ddo_name],
            ['Draw Bank', $entry->bank ? trim($entry->bank->bank_name) . ' — ' . trim($entry->bank->branch_name) : null],
            ['Purpose', $entry->purposeCode?->purpose ?? $entry->purpose],
            ['Contribution Type', $entry->contribution_type === 'SC' ? 'Single Contribution' : ($entry->contribution_type === 'DC' ? 'Double Contribution' : $entry->contribution_type)],
            ['Pension Type', $entry->pension_type === 'U' ? 'UPS' : 'NPS'],
            ['Type', $isDraft ? 'Draft' : 'Receipt'],
            ['Date of Entry', $entry->date_of_entry?->format('d-m-Y')],
            ['Finalize Date', $entry->finalize_date?->format('d-m-Y')],
            ['Entered by', $entry->user_id],
        ];
    @endphp

    <x-breadcrumbs class="mb-4" :crumbs="['Entry Section' => null, 'First Register' => null, 'View All First Entries' => route('first-entries.index')]" :current="'Receipt #' . $entry->sl_no" />

    <a href="{{ route('first-entries.index') }}" wire:navigate
        class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-300">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
        Back to first entries
    </a>

    {{-- Hero --}}
    <div class="mt-3 overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 via-violet-600 to-sky-600 p-6 text-white shadow-xl shadow-indigo-500/25 sm:p-7">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-indigo-100">Receipt No</p>
                <p class="font-display text-3xl font-bold tracking-tight">#{{ $entry->sl_no }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold ring-1 ring-inset ring-white/25">{{ $statusLabel }}</span>
                    <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold ring-1 ring-inset ring-white/25">{{ $isDraft ? 'Draft' : 'Receipt' }}</span>
                    <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold ring-1 ring-inset ring-white/25">{{ $entry->pension_type === 'U' ? 'UPS' : 'NPS' }}</span>
                </div>
            </div>
            <div class="text-right">
                <p class="text-sm font-medium text-indigo-100">Amount</p>
                <p class="font-display text-3xl font-bold tracking-tight">₹{{ number_format((float) $entry->amount, 2) }}</p>
                @can('entrysection.entry_first_register')
                    <a href="{{ route('first-entries.edit', $entry->sl_no) }}" wire:navigate
                        class="mt-3 inline-flex items-center gap-1.5 rounded-xl bg-white px-3.5 py-2 text-sm font-semibold text-indigo-600 shadow transition hover:-translate-y-0.5 hover:shadow-lg">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" /></svg>
                        Edit
                    </a>
                @endcan
            </div>
        </div>
    </div>

    {{-- Details --}}
    <div class="mt-6 rounded-2xl border border-slate-200/70 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/[0.04]">
        <h2 class="mb-4 font-display text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Details</h2>
        <dl class="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
            @foreach ($rows as [$k, $v])
                <div class="flex flex-col border-b border-slate-100 pb-3 dark:border-white/5">
                    <dt class="text-xs font-medium text-slate-400">{{ $k }}</dt>
                    <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100">{{ filled($v) ? $v : '—' }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</div>
