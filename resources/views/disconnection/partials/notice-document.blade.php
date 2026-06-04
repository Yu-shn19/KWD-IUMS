@include('disconnection.partials.notice-document-styles')

@php
    $noticeDate = $disconnectionDate instanceof \Carbon\Carbon
        ? $disconnectionDate
        : \Carbon\Carbon::parse($disconnectionDate);
    $compact = filter_var($compact ?? false, FILTER_VALIDATE_BOOLEAN);
    $forPrint = filter_var($forPrint ?? false, FILTER_VALIDATE_BOOLEAN);
@endphp

<div class="notice-document{{ $compact ? ' notice-document--compact' : '' }}{{ $forPrint ? ' notice-document--a4-print' : '' }}">
    <header class="nd-header">
        <div class="nd-logo-box">
            <img src="{{ url('WDMS/img/logo/logo.png') }}" alt="Hagonoy Water District Logo">
        </div>
        <h2 class="nd-title">NOTICE OF DISCONNECTION</h2>
        <span class="nd-header-spacer" aria-hidden="true"></span>
    </header>

    <p class="nd-intro">
      <!-- Please be informed that your <strong>WATER BILL</strong> has remained unpaid for {{ (int) ($consumer->notice_delinquent_months ?? 2) }} months already. You are hereby given three (3) days to settle your account on or before <strong>{{ $noticeDate->format('F j, Y') }}</strong>. Failure to pay within the specified period will constrain us to disconnect your water service connection. -->
     Please be informed that your <strong>WATER BILL</strong> has remained unpaid for 2 months already. You are hereby given three (3) days to settle your account on or before <strong>{{ $noticeDate->format('F j, Y') }}</strong>. Failure to pay within the specified period will constrain us to disconnect your water service connection.
    </p>

    <h3 class="nd-soa-heading">STATEMENT OF ACCOUNT</h3>

    <div class="nd-soa-section">
        <div class="nd-soa-grid">
            <div class="nd-soa-col nd-soa-col--left">
                <div class="nd-soa-row">
                    <span class="nd-soa-label">Account No.:</span>
                    <span class="nd-soa-value">{{ $consumer->account_no }}</span>
                </div>
                <div class="nd-soa-row">
                    <span class="nd-soa-label">Account Name:</span>
                    <span class="nd-soa-value">{{ $consumer->account_name }}</span>
                </div>
                <div class="nd-soa-row nd-address-row">
                    <span class="nd-soa-label">Address:</span>
                    <span class="nd-soa-value">{{ $consumer->address1 }}</span>
                </div>
                <div class="nd-soa-row">
                    <span class="nd-soa-label">Zone:</span>
                    <span class="nd-soa-value">{{ $consumer->zone_code ?? '—' }}</span>
                </div>
            </div>
            <div class="nd-soa-col nd-soa-col--right">
                <div class="nd-soa-row">
                    <span class="nd-soa-label">This Month / Arrears:</span>
                    <span class="nd-soa-value">₱{{ number_format($consumer->this_month_arrears ?? 0, 2) }}</span>
                </div>
                <div class="nd-soa-row">
                    <span class="nd-soa-label">Last Month / Arrears CY:</span>
                    <span class="nd-soa-value">₱{{ number_format($consumer->last_month_arrears ?? 0, 2) }}</span>
                </div>
                <div class="nd-soa-row">
                    <span class="nd-soa-label">Other / A/R:</span>
                    <span class="nd-soa-value">₱{{ number_format($consumer->others_ar ?? 0, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="nd-important">
        <div class="nd-notes-summary-row">
            <div class="nd-notes-summary-left">
                <strong>Important Notes:</strong>
            </div>
            <div class="nd-notes-summary-right">
                <div class="nd-total-line">
                    <span class="nd-total-line-label">Total Amount Due:</span>
                    <span class="nd-total-line-value">₱{{ number_format($consumer->total_outstanding, 2) }}</span>
                </div>
            </div>
        </div>
        <ol class="nd-important-list">
            <li>If the service is disconnected, payment of the total amount due plus a <strong>Php 200.00 reconnection fee</strong> shall be required before reconnection of water service.</li>
            <li>Please disregard this notice if payment has already been made in full.</li>
            <li>Partial payment does not exempt the account from disconnection.</li>
        </ol>
    </div>

    <div class="nd-sign-row">
        <div class="nd-sign-col">
            <p class="nd-sign-label">Prepared by:</p>
            <div class="nd-sign-name-stack">
                <div class="nd-sign-img-holder">
                    <img class="nd-sign-img" src="{{ asset('images/signatures/marlo-sign.png') }}" alt="Signature of Marlo B. Porras">
                </div>
                <p class="nd-sign-name">Marlo B. Porras</p>
                <p class="nd-sign-title">UCSA-E</p>
            </div>
        </div>
        <div class="nd-sign-col">
            <p class="nd-sign-label">Noted by:</p>
            <div class="nd-sign-name-stack">
                <div class="nd-sign-img-holder">
                    <img class="nd-sign-img" src="{{ asset('images/signatures/gm-sign.png') }}" alt="Signature of Engr. Joemar G. Raut">
                </div>
                <p class="nd-sign-name">Engr. Joemar G. Raut</p>
                <p class="nd-sign-title">General Manager</p>
            </div>
        </div>
        <div class="nd-sign-col">
            <p class="nd-sign-label">Implemented thru:</p>
            <div class="nd-sign-img-holder nd-sign-img-holder--spacer" aria-hidden="true"></div>
            <p class="nd-bod">BOD Resolution No. 42, s. 2024</p>
            <p class="nd-sign-title nd-sign-title--phantom" aria-hidden="true">&nbsp;</p>
        </div>
    </div>

    <div class="nd-received">
        <span class="nd-received-label">RECEIVED BY / DATE:</span>
        <span class="nd-received-line"></span>
    </div>
</div>
