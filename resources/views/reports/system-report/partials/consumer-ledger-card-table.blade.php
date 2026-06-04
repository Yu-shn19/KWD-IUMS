@php
    use App\Services\ConsumerLedgerCardBuilder;

    $ledgerRows = $ledgerRows ?? collect();
    $fmt = fn ($v) => ConsumerLedgerCardBuilder::formatMoney(is_numeric($v) ? (float) $v : 0);
    $fmtBal = fn ($v) => ConsumerLedgerCardBuilder::formatMoney(is_numeric($v) ? (float) $v : 0, false);
@endphp

<table class="table table-sm table-bordered ledger-card-table mb-0">
    <thead class="thead-light">
        <tr>
            <th rowspan="2" class="align-middle">DATe</th>
            <th rowspan="2" class="align-middle">O.R/Billde</th>
            <th rowspan="2" class="align-middle">Reading</th>
            <th rowspan="2" class="align-middle">Cuns.</th>
            <th colspan="3" class="text-center font-weight-bold">BILLINGS</th>
            <th colspan="3" class="text-center font-weight-bold">PAYMENT</th>
            <th colspan="3" class="text-center font-weight-bold">BALANCE</th>
            <th rowspan="2" class="align-middle text-center">Total Amount</th>
        </tr>
        <tr>
            <th class="text-center">w/Sales</th>
            <th class="text-center">Penalties</th>
            <th class="text-center">M.R</th>
            <th class="text-center">w/Sales</th>
            <th class="text-center">Penalties</th>
            <th class="text-center">M.R</th>
            <th class="text-center">w/Sales</th>
            <th class="text-center">Penalties</th>
            <th class="text-center">M.R</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($ledgerRows as $row)
            <tr>
                <td>{{ $row['date'] ?? '' }}</td>
                <td>{{ $row['reference'] ?? '' }}</td>
                <td class="num text-center">{{ $row['reading'] ?? '' }}</td>
                <td class="num text-center">{{ $row['consumption'] ?? '' }}</td>
                <td class="num">{{ $fmt($row['bill_sales'] ?? 0) }}</td>
                <td class="num">{{ $fmt($row['bill_penalty'] ?? 0) }}</td>
                <td class="num">{{ $fmt($row['bill_mr'] ?? 0) }}</td>
                <td class="num">{{ $fmt($row['pay_sales'] ?? 0) }}</td>
                <td class="num">{{ $fmt($row['pay_penalty'] ?? 0) }}</td>
                <td class="num">{{ $fmt($row['pay_mr'] ?? 0) }}</td>
                <td class="num">{{ $fmtBal($row['bal_sales'] ?? 0) }}</td>
                <td class="num">{{ $fmtBal($row['bal_penalty'] ?? 0) }}</td>
                <td class="num">{{ $fmtBal($row['bal_mr'] ?? 0) }}</td>
                <td class="num font-weight-bold">{{ $fmtBal($row['total_balance'] ?? 0) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="14" class="text-center text-muted py-4">No ledger transactions for the selected period.</td>
            </tr>
        @endforelse
    </tbody>
</table>
