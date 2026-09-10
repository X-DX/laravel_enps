<?php

namespace App\Exports;

use App\Models\FirstReceipt;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exports the Central Register list, matching the on-screen filter and columns exactly — the
 * three identifiers kept apart (First Receipt No / CR No / Receipt No), plus the blocked flag.
 * If the screen and the spreadsheet disagree, the spreadsheet is the one that ends up in a file
 * note, so they are generated from the same query and the same accessors.
 */
class CrEntriesExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private readonly Builder $query)
    {
    }

    public function query(): Builder
    {
        return $this->query
            ->with(['ddo.treasury', 'ddo.location', 'bank', 'purposeCode', 'centralRegs'])
            ->orderByDesc('sl_no');
    }

    public function headings(): array
    {
        return [
            'First Receipt No', 'CR No', 'Receipt No', 'Treasury Location', 'DDO', 'Order/Letter No',
            'Order Date', 'Draft/Receipt No', 'Draft/Receipt Date', 'Amount', 'Contribution',
            'Draw Bank', 'Purpose', 'Status', 'Blocked', 'Blocked Reason', 'Duplicate CR Entries',
        ];
    }

    /**
     * @param  FirstReceipt  $r
     */
    public function map($r): array
    {
        $cr = $r->primaryCentralReg();

        return [
            $r->sl_no,
            $cr?->sl_no,
            $cr?->receipt_no,
            $r->ddo?->treasury?->treasury_name ?? $r->ddo?->location?->loc_name,
            $r->ddo?->ddo_name,
            $r->order_no,
            $r->order_date?->format('d-m-Y'),
            $r->draft_no,
            $r->draft_date?->format('d-m-Y'),
            number_format((float) $r->amount, 2, '.', ''),
            $r->contribution_type === 'SC' ? 'Single' : ($r->contribution_type === 'DC' ? 'Double' : $r->contribution_type),
            $r->bank ? trim($r->bank->bank_name) . ' - ' . trim($r->bank->branch_name) : null,
            $r->purposeLabel(),
            $r->statusLabel(),
            $cr?->isBlocked() ? 'YES' : '',
            $cr?->isBlocked() ? $cr->blocked_reason : null,
            // Blank unless the draft was double-booked, then list every CR No so it can be traced.
            $r->isDoubleBooked() ? $r->centralRegs->pluck('sl_no')->join(', ') : null,
        ];
    }
}
