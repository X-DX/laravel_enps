<?php

namespace App\Exports;

use App\Models\Treasury;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exports treasuries to Excel, respecting the current search term so the file matches
 * what the user is looking at.
 */
class TreasuriesExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private readonly string $search = '')
    {
    }

    public function query(): Builder
    {
        return Treasury::query()
            ->with('district')        // eager-load so map() doesn't fire one query per row (N+1)
            ->search($this->search)   // same scope the table uses
            ->orderBy('treasury_code');
    }

    public function headings(): array
    {
        return ['Treasury Code', 'Treasury Name', 'District Code', 'District Name'];
    }

    /**
     * @param  Treasury  $treasury
     */
    public function map($treasury): array
    {
        return [
            $treasury->treasury_code,
            $treasury->treasury_name,
            $treasury->dist_code,
            $treasury->district?->dist_name,
        ];
    }
}
