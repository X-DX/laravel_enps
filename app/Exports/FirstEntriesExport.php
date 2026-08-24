<?php

namespace App\Exports;

use App\Models\FirstReceipt;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exports the first-register entries, using the same search + status filter as the screen.
 */
class FirstEntriesExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        private readonly string $search = '',
        private readonly string $status = '',
    ) {
    }

    public function query(): Builder
    {
        return FirstReceipt::query()
            ->with(['ddo', 'bank', 'purposeCode'])
            ->filter($this->search, $this->status)
            ->orderByDesc('sl_no');
    }

    public function headings(): array
    {
        return ['Receipt No', 'Draft/Receipt No', 'Type', 'Draft Date', 'Order No', 'Amount', 'DDO', 'Bank', 'Purpose', 'Contribution', 'Pension', 'Status'];
    }

    /**
     * @param  FirstReceipt  $r
     */
    public function map($r): array
    {
        return [
            $r->sl_no,
            $r->draft_no,
            $r->type === 'D' ? 'Draft' : 'Receipt',
            $r->draft_date?->format('d-m-Y'),
            $r->order_no,
            number_format((float) $r->amount, 2, '.', ''),
            $r->ddo?->ddo_name,
            $r->bank ? trim($r->bank->bank_name) . ' - ' . trim($r->bank->branch_name) : null,
            $r->purposeCode?->purpose,
            $r->contribution_type === 'SC' ? 'Single' : ($r->contribution_type === 'DC' ? 'Double' : $r->contribution_type),
            $r->pension_type === 'U' ? 'UPS' : 'NPS',
            $r->flag === 'T' ? 'Pending' : (in_array($r->flag, ['FZ', 'CR']) ? 'Finalized' : $r->flag),
        ];
    }
}
