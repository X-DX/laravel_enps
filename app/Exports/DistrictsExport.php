<?php

namespace App\Exports;

use App\Models\District;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exports districts to Excel, respecting the current search term so the file
 * matches what the user is looking at.
 *
 *  - FromQuery   → stream rows straight from a query (memory-friendly for big tables)
 *  - WithHeadings→ the first row is a header
 *  - WithMapping → shape each row into the columns we want
 */
class DistrictsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private readonly string $search = '')
    {
    }

    public function query(): Builder
    {
        return District::query()
            ->with('state')           // eager-load so map() doesn't fire one query per row
            ->search($this->search)   // same scope the table uses
            ->orderBy('dist_code');
    }

    public function headings(): array
    {
        return ['State', 'District Code', 'District Name'];
    }

    /**
     * @param  District  $district
     */
    public function map($district): array
    {
        return [
            $district->state?->state_name,   // blank for legacy rows with no state yet
            $district->dist_code,
            $district->dist_name,
        ];
    }
}
