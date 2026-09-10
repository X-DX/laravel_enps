<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: "DejaVu Sans", sans-serif; }
        body { margin: 0; color: #1f2937; font-size: 9px; }
        h1 { margin: 0 0 2px; font-size: 15px; }
        .muted { margin: 0 0 10px; color: #6b7280; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 4px 5px; text-align: left; }
        thead th { background: #eef2ff; color: #4338ca; font-size: 8px; text-transform: uppercase; letter-spacing: .03em; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        .r { text-align: right; }
        .empty { padding: 18px; text-align: center; color: #9ca3af; }
        .foot { margin-top: 8px; color: #9ca3af; font-size: 8px; text-align: right; }
        .blocked { color: #be123c; font-weight: bold; }
        .warn { color: #b45309; font-weight: bold; }
        .legend { margin-top: 6px; color: #6b7280; font-size: 7.5px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="muted">Generated {{ $generatedAt->format('d-m-Y H:i') }} &middot; {{ $rows->count() }} record(s)</p>

    <table>
        <thead>
            <tr>
                <th>Sl</th>
                <th>First Receipt No</th>
                <th>CR No</th>
                <th>Receipt No</th>
                <th>Treasury Location</th>
                <th>DDO</th>
                <th>Order/Letter No</th>
                <th>Order Date</th>
                <th>Draft/Receipt No</th>
                <th>Draft/Receipt Date</th>
                <th class="r">Amount</th>
                <th>Contribution</th>
                <th>Draw Bank</th>
                <th>Purpose</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $r)
                @php $cr = $r->primaryCentralReg(); @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $r->sl_no }}</td>
                    <td>{{ $cr?->sl_no ?? '—' }}@if ($r->isDoubleBooked())<span class="warn"> ×{{ $r->centralRegs->count() }}</span>@endif</td>
                    <td>{{ $cr?->receipt_no ?? '—' }}</td>
                    <td>{{ $r->ddo?->treasury?->treasury_name ?? $r->ddo?->location?->loc_name ?? '—' }}</td>
                    <td>{{ $r->ddo?->ddo_name ?? '—' }}</td>
                    <td>{{ $r->order_no ?: '—' }}</td>
                    <td>{{ $r->order_date?->format('d-m-Y') ?? '—' }}</td>
                    <td>{{ $r->draft_no }}</td>
                    <td>{{ $r->draft_date?->format('d-m-Y') ?? '—' }}</td>
                    <td class="r">{{ number_format((float) $r->amount, 2) }}</td>
                    <td>{{ $r->contribution_type === 'SC' ? 'Single' : ($r->contribution_type === 'DC' ? 'Double' : $r->contribution_type) }}</td>
                    <td>{{ $r->bank ? trim($r->bank->bank_name) . ', ' . trim($r->bank->branch_name) : '—' }}</td>
                    <td>{{ $r->purposeLabel() }}</td>
                    <td>{{ $r->statusLabel() }}@if ($cr?->isBlocked())<span class="blocked"> BLOCKED</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="15" class="empty">No entries.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="legend">
        First Receipt No = the draft as it arrived &middot;
        CR No = its serial in the Central Register &middot;
        Receipt No = the number on the DDO's acknowledgement, shared by every draft finalized in the same batch.
        &times;n marks a draft booked into the register more than once; the earliest CR No is shown.
    </p>
    <p class="foot">eNPS &middot; Central Register</p>
</body>
</html>
