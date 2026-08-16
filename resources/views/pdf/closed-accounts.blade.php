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
            background: #fef2f2;
            color: #b91c1c;
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
    <h1>Closed Accounts</h1>
    <p class="muted">Generated {{ $generatedAt->format('d-m-Y H:i') }} &middot; {{ $closures->count() }} record(s)</p>

    <table>
        <thead>
            <tr>
                <th>Sl</th>
                <th>Account No</th>
                <th>Name</th>
                <th>Closure Reason</th>
                <th>Closing Date</th>
                <th>Last Contribution Month</th>
                <th>Last Contribution Year</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($closures as $i => $closure)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $closure->account_no }}</td>
                    <td>{{ $closure->subscriber?->name ?? '—' }}</td>
                    <td>{{ $closure->reason?->reason ?? '—' }}</td>
                    <td>{{ $closure->closing_date?->format('d-m-Y') ?? '—' }}</td>
                    <td>{{ $closure->last_contribution_month ? date('F', mktime(0, 0, 0, $closure->last_contribution_month, 1)) : '—' }}
                    </td>
                    <td>{{ $closure->last_contribution_year ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="empty">No closed accounts.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="foot">eNPS &middot; Account Register</p>
</body>

</html>
