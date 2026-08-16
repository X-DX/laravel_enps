<?php

namespace App\Exports;

use App\Models\AccountClosure;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exports the closed-accounts register (account_closure), using the same search as the screen.
 */
class ClosedAccountsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private readonly string $search = '') {}

    public function query(): Builder
    {
        return AccountClosure::query()
            ->with(['reason', 'subscriber'])
            ->search($this->search)
            ->orderByDesc('closing_date');
    }

    public function headings(): array
    {
        return ['Account No', 'Name', 'Closure Reason', 'Closing Date', 'Last Contribution Month', 'Last Contribution Year'];
    }

    /**
     * @param  AccountClosure  $closure
     */
    public function map($closure): array
    {
        return [
            $closure->account_no,
            $closure->subscriber?->name,
            $closure->reason?->reason,
            $closure->closing_date?->format('d-m-Y'),
            $closure->last_contribution_month ? date('F', mktime(0, 0, 0, $closure->last_contribution_month, 1)) : '',
            $closure->last_contribution_year,
        ];
    }
}
