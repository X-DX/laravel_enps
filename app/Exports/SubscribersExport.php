<?php

namespace App\Exports;

use App\Models\Department;
use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exports the subscriber list to Excel, using the same search + status filter as the screen.
 */
class SubscribersExport implements FromQuery, WithHeadings, WithMapping
{
    /** @var array<string,string> [dept_code => dept_name] */
    private array $departments;

    public function __construct(
        private readonly string $search = '',
        private readonly string $status = '',
    ) {
        $this->departments = Department::pluck('dept_name', 'dept_code')->all();
    }

    public function query(): Builder
    {
        return Subscriber::query()
            ->with(['ddo', 'designationMaster', 'pran'])
            ->filter($this->search, $this->status)
            ->orderBy('id');
    }

    public function headings(): array
    {
        return ['Account No', 'PRAN No', 'Name', 'DOB', 'Dept Code', 'Department', 'Designation', 'DDO', 'Status'];
    }

    /**
     * @param  Subscriber  $sub
     */
    public function map($sub): array
    {
        $deptCode = trim($sub->nameofdept);

        return [
            $sub->save_flag === 'T' ? 'pending' : $sub->account_no,
            $sub->pran ? number_format($sub->pran->pran_no, 0, '.', '') : '',
            $sub->name,
            $sub->dob?->format('d-m-Y'),
            $deptCode,
            $this->departments[$deptCode] ?? '',
            $sub->designationMaster?->designation,
            $sub->ddo?->ddo_name,
            $sub->save_flag === 'F' ? 'Finalized' : 'Pending',
        ];
    }
}
