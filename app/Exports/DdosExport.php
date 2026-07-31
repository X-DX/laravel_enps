<?php

namespace App\Exports;

use App\Models\Ddo;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DdosExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        private readonly string $distCode = '',
        private readonly string $treasuryCode = '',
    ) {}

    public function query(): Builder
    {
        return Ddo::query()
            ->with('treasury.district')
            ->forTreasuryFilter($this->distCode, $this->treasuryCode)
            ->orderBy('ddo_sl');
    }

    public function headings(): array
    {
        return ['DDO Sl', 'DDO Code', 'DDO Name', 'Treasury Code', 'Treasury', 'District'];
    }

    /**
     * @param  Ddo  $ddo
     */
    public function map($ddo): array
    {
        return [
            $ddo->ddo_sl,
            $ddo->ddo_code,
            $ddo->ddo_name,
            $ddo->treasury_code,
            $ddo->treasury?->treasury_name,
            $ddo->treasury?->district?->dist_name,
        ];
    }
}
