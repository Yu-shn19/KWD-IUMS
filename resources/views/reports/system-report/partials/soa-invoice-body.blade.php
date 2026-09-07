@php
    $invoiceRows = $invoiceRows ?? collect();
    $interest = (float) ($interest ?? ($totals['interest'] ?? 0));
    $totals = $totals ?? ['subtotal' => 0, 'interest' => 0, 'total_due' => 0];
    $padTo = 9;
    $emptyPad = max(0, $padTo - max(1, $invoiceRows->count()));
    $hasRows = $invoiceRows->isNotEmpty();
@endphp

<table class="soa-invoice-table">
    <thead>
        <tr>
            <th class="col-date">Date</th>
            <th class="col-desc">Description</th>
            <th class="col-qty"></th>
            <th class="col-x"></th>
            <th class="col-rate"></th>
            <th class="col-amt">Amount</th>
        </tr>
    </thead>
    <tbody>
        @if ($hasRows)
            @foreach ($invoiceRows as $row)
                <tr>
                    <td class="col-date">{{ $row->date_label }}</td>
                    <td class="col-desc">{{ $row->description }}</td>
                    <td class="col-qty">{{ number_format((float) $row->quantity, 2) }}</td>
                    <td class="col-x">x</td>
                    <td class="col-rate">{{ number_format((float) $row->rate, 2) }}</td>
                    <td class="col-amt">{{ number_format((float) $row->amount, 2) }}</td>
                </tr>
            @endforeach
        @else
            <tr>
                <td class="col-date">&nbsp;</td>
                <td class="col-desc"></td>
                <td class="col-qty"></td>
                <td class="col-x"></td>
                <td class="col-rate"></td>
                <td class="col-amt"></td>
            </tr>
        @endif
        @for ($i = 0; $i < $emptyPad; $i++)
            <tr class="soa-pad-row">
                <td class="col-date">&nbsp;</td>
                <td class="col-desc"></td>
                <td class="col-qty"></td>
                <td class="col-x"></td>
                <td class="col-rate"></td>
                <td class="col-amt"></td>
            </tr>
        @endfor
    </tbody>
</table>

<div class="soa-invoice-footer">
    <div class="soa-thanks">Thank you for your business!</div>
    <div class="soa-totals-wrap">
        <div class="soa-total-line">
            <span class="soa-total-label">Subtotal :</span>
            <span class="soa-total-value">{{ number_format((float) ($totals['subtotal'] ?? 0), 2) }}</span>
        </div>
        <div class="soa-total-line">
            <span class="soa-total-label">Interest :</span>
            <span class="soa-total-value">
                @if ($interest > 0)
                    {{ number_format($interest, 2) }}
                @endif
            </span>
        </div>
        <div class="soa-total-due-box">
            <span class="soa-total-label">Total Due</span>
            <span class="soa-total-value">{{ number_format((float) ($totals['total_due'] ?? 0), 2) }}</span>
        </div>
    </div>
</div>
