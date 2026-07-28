<?php

namespace App\Exports;

use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LocationsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private readonly string $distCode = '')
    {
    }

    public function query(): Builder
    {
        // Eager-load the district so map() doesn't fire one query per row (N+1).
        return Location::query()
            ->with('district')
            ->when($this->distCode !== '', fn (Builder $q) => $q->where('dist_code', $this->distCode))
            ->orderBy('loc_code');
    }

    public function headings(): array
    {
        return ['Location Code', 'Location Name', 'District Code', 'District Name'];
    }

    /**
     * @param  Location  $location
     */
    public function map($location): array
    {
        return [
            $location->loc_code,
            $location->loc_name,
            $location->dist_code,
            $location->district?->dist_name,
        ];
    }
}
