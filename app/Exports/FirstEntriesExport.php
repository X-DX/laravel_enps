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
            ->with(['ddo.treasury', 'ddo.location', 'bank', 'purposeCode'])
            ->filter($this->search, $this->status)
            ->orderByDesc('sl_no');
    }

    public function headings(): array
    {
        return ['Receipt No', 'Treasury Location', 'DDO', 'Order/Letter No', 'Order Date', 'Draft/Receipt No', 'Type', 'Draft/Receipt Date', 'Amount', 'Contribution', 'Draw Bank', 'Purpose', 'Status'];
    }

    /**
     * @param  FirstReceipt  $r
     */
    public function map($r): array
    {
        return [
            $r->sl_no,
            $r->ddo?->treasury?->treasury_name ?? $r->ddo?->location?->loc_name,
            $r->ddo?->ddo_name,
            $r->order_no,
            $r->order_date?->format('d-m-Y'),
            $r->draft_no,
            $r->type === 'D' ? 'Draft' : 'Receipt',
            $r->draft_date?->format('d-m-Y'),
            number_format((float) $r->amount, 2, '.', ''),
            $r->contribution_type === 'SC' ? 'Single' : ($r->contribution_type === 'DC' ? 'Double' : $r->contribution_type),
            $r->bank ? trim($r->bank->bank_name) . ' - ' . trim($r->bank->branch_name) : null,
            $r->purposeLabel(),
            $r->statusLabel(),
        ];
    }
}
