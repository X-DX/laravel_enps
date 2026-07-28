<?php

namespace App\Exports;

use App\Models\Designation;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DesignationsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private readonly string $search = '')
    {
    }

    public function query(): Builder
    {
        return Designation::query()->search($this->search)->orderBy('designation_id');
    }

    public function headings(): array
    {
        return ['ID', 'Designation'];
    }

    /**
     * @param  Designation  $designation
     */
    public function map($designation): array
    {
        return [$designation->designation_id, $designation->designation];
    }
}
