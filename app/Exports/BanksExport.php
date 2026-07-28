<?php

namespace App\Exports;

use App\Models\Bank;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BanksExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private readonly string $search = '')
    {
    }

    public function query(): Builder
    {
        return Bank::query()->search($this->search)->orderBy('bank_code');
    }

    public function headings(): array
    {
        return ['Bank Code', 'Bank Name', 'Branch Name'];
    }

    /**
     * @param  Bank  $bank
     */
    public function map($bank): array
    {
        return [$bank->bank_code, $bank->bank_name, $bank->branch_name];
    }
}
