<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: "DejaVu Sans", sans-serif; }
        body { margin: 0; color: #1f2937; font-size: 10px; }
        h1 { margin: 0 0 2px; font-size: 16px; }
        .muted { margin: 0 0 12px; color: #6b7280; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 5px 6px; text-align: left; }
        thead th { background: #eef2ff; color: #4338ca; font-size: 9px; text-transform: uppercase; letter-spacing: .04em; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        .badge { padding: 1px 5px; border-radius: 4px; font-size: 9px; font-weight: bold; }
        .pending { background: #fde68a; color: #92400e; }
        .final { background: #d1fae5; color: #065f46; }
        .empty { padding: 20px; text-align: center; color: #9ca3af; }
        .foot { margin-top: 10px; color: #9ca3af; font-size: 9px; text-align: right; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="muted">Generated {{ $generatedAt->format('d-m-Y H:i') }} &middot; {{ $subscribers->count() }} record(s)</p>

    <table>
        <thead>
            <tr>
                <th>Sl</th>
                <th>Account No</th>
                <th>PRAN No</th>
                <th>Name</th>
                <th>DOB</th>
                <th>Dept</th>
                <th>Department</th>
                <th>Designation</th>
                <th>DDO</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($subscribers as $i => $sub)
                @php $deptCode = trim((string) $sub->nameofdept); @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $sub->save_flag === 'T' ? '—' : $sub->account_no }}</td>
                    <td>{{ $sub->pran ? number_format($sub->pran->pran_no, 0, '.', '') : '—' }}</td>
                    <td>{{ $sub->name }}</td>
                    <td>{{ $sub->dob?->format('d-m-Y') ?? '—' }}</td>
                    <td>{{ $deptCode }}</td>
                    <td>{{ $departments[$deptCode] ?? '—' }}</td>
                    <td>{{ $sub->designationMaster?->designation ?? '—' }}</td>
                    <td>{{ $sub->ddo?->ddo_name ?? '—' }}</td>
                    <td>
                        @if ($sub->save_flag === 'F')
                            <span class="badge final">Finalized</span>
                        @else
                            <span class="badge pending">Pending</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="empty">No records.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="foot">eNPS &middot; Account Register</p>
</body>
</html>
