<div class="mx-auto max-w-4xl">
    {{-- Back to the list --}}
    <a href="{{ route('accounts.index') }}" wire:navigate
        class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-300">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
        Back to all accounts
    </a>

    @php
        // Initials for the avatar (first letters of the first two words).
        $initials = collect(preg_split('/\s+/', trim($subscriber->name)))
            ->filter()
            ->take(2)
            ->map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)))
            ->implode('');

        // Pension type: N = NPS, U = UPS.
        $pensionType = match ($subscriber->pension_type) {
            'N' => 'NPS',
            'U' => 'UPS',
            default => '—',
        };
        // For NPS the pay figure is "Basic + DA"; for UPS it's plain "Basic Pay".
$payLabel = $subscriber->pension_type === 'N' ? 'Basic + DA' : 'Basic Pay';

// Deduction start month is stored as "01".."12" — show the month name instead.
$months = [
    '01' => 'January',
    '02' => 'February',
    '03' => 'March',
    '04' => 'April',
    '05' => 'May',
    '06' => 'June',
    '07' => 'July',
    '08' => 'August',
    '09' => 'September',
    '10' => 'October',
    '11' => 'November',
    '12' => 'December',
];
$startMonth = $months[trim((string) $subscriber->starting_month)] ?? null;

// Treasury Location comes through the DDO's treasury (empty for DDOs not linked yet).
        $treasuryLocation = $subscriber->ddo?->treasury?->treasury_name;

        $accountNo = $subscriber->save_flag === 'T' ? 'pending' : $subscriber->account_no;
        $pran = $subscriber->pran ? number_format($subscriber->pran->pran_no, 0, '.', '') : '—';

        $sections = [
            'Personal' => [
                'Employee Name' => $subscriber->name,
                "Father's Name" => $subscriber->father_name,
                "Mother's Name" => $subscriber->mother_name,
                'Single Mother' => $subscriber->single_mother_flag ? 'Yes' : 'No',
                'Date of Birth' => $subscriber->dob?->format('d-m-Y'),
            ],
            'Service' => [
                'Designation' => $subscriber->designationMaster?->designation,
                'Department' => $department?->dept_name,
                'Treasury Location' => $treasuryLocation,
                'DDO' => $subscriber->ddo?->ddo_name,
                'Appointment Order' => $subscriber->appnt_ord_no,
                'Appointment Date' => $subscriber->doapptorder?->format('d-m-Y'),
                'Date of Joining' => $subscriber->doj?->format('d-m-Y'),
                'Date of Retirement' => $subscriber->dor?->format('d-m-Y'),
            ],
            'Pension & Pay' => [
                'Pension Type' => $pensionType,
                $payLabel => $subscriber->pay ? number_format($subscriber->pay) : null,
                'Deduction Start Month' => $startMonth,
                'Deduction Start Year' => $subscriber->starting_fin_year,
            ],
            'Nominees' => [
                '1st Nominee' => $subscriber->name_nominee,
                '2nd Nominee' => $subscriber->name_nominee2,
                '3rd Nominee' => $subscriber->name_nominee3,
            ],
            'Account' => [
                'PPAN No' => $subscriber->pran?->ppan_no,
                'Active' => $subscriber->isactive ? 'Yes' : 'No',
                'Entered By' => $subscriber->user_id,
                'Entry Date' => $subscriber->entry_date?->format('d-m-Y'),
                'Finalize Date' => $subscriber->finalize_date?->format('d-m-Y'),
            ],
        ];
    @endphp

    {{-- Header card: avatar, name, status, and a quick-facts strip --}}
    <div
        class="mt-3 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
        <div
            class="flex flex-wrap items-center gap-4 border-b border-slate-100 bg-gradient-to-r from-indigo-50 to-sky-50 p-6 dark:border-white/5 dark:from-indigo-500/10 dark:to-sky-500/10">
            <div
                class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-sky-500 text-lg font-bold text-white shadow-md shadow-indigo-500/30">
                {{ $initials ?: '?' }}
            </div>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-xl font-semibold text-slate-900 dark:text-white">{{ $subscriber->name }}</h1>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ $subscriber->designationMaster?->designation ?? '—' }}</p>
            </div>
            @if ($subscriber->save_flag === 'F')
                <span
                    class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Finalized
                </span>
            @else
                <span
                    class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> Pending
                </span>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-px bg-slate-100 sm:grid-cols-3 dark:bg-white/5">
            @foreach (['Account No' => $accountNo, 'PRAN' => $pran, 'DDO' => $subscriber->ddo?->ddo_name ?? '—'] as $label => $value)
                <div class="bg-white p-4 dark:bg-slate-900">
                    <p class="text-xs uppercase tracking-wide text-slate-400">{{ $label }}</p>
                    <p class="mt-0.5 font-semibold break-words text-slate-900 dark:text-white">{{ $value }}</p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- One card per section; one row per field --}}
    <div class="mt-5 grid gap-5 sm:grid-cols-2">
        @foreach ($sections as $title => $fields)
            <div
                class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
                <h2 class="mb-4 flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                    <span class="h-4 w-1 rounded-full bg-gradient-to-b from-indigo-500 to-sky-500"></span>
                    {{ $title }}
                </h2>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3">
                    @foreach ($fields as $label => $value)
                        <div>
                            <dt class="text-xs text-slate-400">{{ $label }}</dt>
                            <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200">
                                {{ filled($value) ? $value : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endforeach
    </div>
</div>
