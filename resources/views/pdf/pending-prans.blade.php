<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        * {
            font-family: "DejaVu Sans", sans-serif;
        }

        body {
            margin: 0;
            color: #1f2937;
            font-size: 10px;
        }

        h1 {
            margin: 0 0 2px;
            font-size: 16px;
        }

        .muted {
            margin: 0 0 12px;
            color: #6b7280;
            font-size: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 5px 6px;
            text-align: left;
        }

        thead th {
            background: #eef2ff;
            color: #4338ca;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        .empty {
            padding: 20px;
            text-align: center;
            color: #9ca3af;
        }

        .foot {
            margin-top: 10px;
            color: #9ca3af;
            font-size: 9px;
            text-align: right;
        }
    </style>
</head>

<body>
    <h1>Pending Assigned PRANs</h1>
    <p class="muted">Generated {{ $generatedAt->format('d-m-Y H:i') }} &middot; {{ $prans->count() }} record(s)</p>

    <table>
        <thead>
            <tr>
                <th>Sl</th>
                <th>Account No</th>
                <th>PRAN No</th>
                <th>Name</th>
                <th>DOB</th>
                <th>Designation</th>
                <th>DDO</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($prans as $i => $pran)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $pran->account_no }}</td>
                    <td>{{ $pran->pran_no ? number_format($pran->pran_no, 0, '.', '') : '—' }}</td>
                    <td>{{ $pran->subscriber?->name ?? '—' }}</td>
                    <td>{{ $pran->subscriber?->dob?->format('d-m-Y') ?? '—' }}</td>
                    <td>{{ $pran->subscriber?->designationMaster?->designation ?? '—' }}</td>
                    <td>{{ $pran->subscriber?->ddo?->ddo_name ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="empty">No pending PRANs.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="foot">eNPS &middot; Assign PRAN</p>
</body>

</html>
