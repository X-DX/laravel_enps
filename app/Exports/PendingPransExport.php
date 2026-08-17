<?php

namespace App\Exports;

use App\Models\Department;
use App\Models\PranNo;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/** Exports the pending (draft) PRAN assignments, joined to the account for its details. */
class PendingPransExport implements FromQuery, WithHeadings, WithMapping
{
    /** @var array<string,string> [dept_code => dept_name] */
    private array $departments;

    public function __construct()
    {
        $this->departments = Department::pluck('dept_name', 'dept_code')->all();
    }

    public function query(): Builder
    {
        return PranNo::query()
            ->with(['subscriber', 'subscriber.designationMaster', 'subscriber.ddo'])
            ->where('save_flag', 'T')
            ->orderByDesc('entry_date');
    }

    public function headings(): array
    {
        return ['Account No', 'PRAN No', 'Name', 'DOB', 'Department', 'Designation', 'DDO'];
    }

    /**
     * @param  PranNo  $pran
     */
    public function map($pran): array
    {
        $sub = $pran->subscriber;
        $deptCode = trim((string) $sub?->nameofdept);

        return [
            $pran->account_no,
            $pran->pran_no ? number_format($pran->pran_no, 0, '.', '') : '',
            $sub?->name,
            $sub?->dob?->format('d-m-Y'),
            $this->departments[$deptCode] ?? $deptCode,
            $sub?->designationMaster?->designation,
            $sub?->ddo?->ddo_name,
        ];
    }
}
