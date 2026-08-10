<div class="mx-auto max-w-4xl">
    {{-- Back to the list --}}
    <a href="{{ route('accounts.index') }}" wire:navigate
        class="text-sm text-indigo-600 hover:underline dark:text-indigo-300">← Back to all accounts</a>

    {{-- Top card: name, account number, PRAN, and a status badge --}}
    <div class="mt-3 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">{{ $subscriber->name }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Account:
                    <span class="font-medium text-slate-700 dark:text-slate-200">{{ $subscriber->save_flag === 'T' ? 'pending' : $subscriber->account_no }}</span>
                    @if ($subscriber->pran)
                    · PRAN: <span class="font-medium text-slate-700 dark:text-slate-200">{{ number_format($subscriber->pran->pran_no, 0, '.', '') }}</span>
                    @endif
                </p>
            </div>
            @if ($subscriber->save_flag === 'F')
            <span class="rounded-md bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">Finalized</span>
            @else
            <span class="rounded-md bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">Pending</span>
            @endif
        </div>
    </div>

    {{-- Prepare the fields to show, grouped into sections --}}
    @php
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
    '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
    '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
    '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December',
    ];
    $startMonth = $months[trim((string) $subscriber->starting_month)] ?? null;

    // No location column on the subscriber — it comes from the DDO's location.
    $officeLocation = $subscriber->ddo?->location?->loc_name;

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
    'Department' => $departments?->dept_name,
    'Office Location' => $officeLocation,
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


    {{-- Draw one card per section, and one row per field inside it --}}
    <div class="mt-5 grid gap-5 sm:grid-cols-2">
        @foreach ($sections as $title => $fields)
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $title }}</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3">
                @foreach ($fields as $label => $value)
                <div>
                    <dt class="text-xs text-slate-400">{{ $label }}</dt>
                    <dd class="mt-0.5 text-sm text-slate-800 dark:text-slate-200">{{ filled($value) ? $value : '—' }}</dd>
                </div>
                @endforeach
            </dl>
        </div>
        @endforeach
    </div>
</div>