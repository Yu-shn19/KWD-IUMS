<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notice of Disconnection</title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 0.5in;
            }
            body { margin: 0; padding: 0; }
            .no-print { display: none; }
            .page-break { page-break-after: always; }
            .notice { 
                border: none; 
                margin: 0; 
                padding: 20px 15px; 
                page-break-inside: avoid;
                height: 50%;
                box-sizing: border-box;
            }
            .notice-divider {
                border: none;
                margin: 0;
                height: 10px;
                page-break-after: never;
            }
        }
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            font-size: 11px;
            background: white;
        }
        .notice {
            max-width: 8.27in;
            margin: 0 auto 15px;
            padding: 20px 15px;
            background: white;
            min-height: 5.3in;
            box-sizing: border-box;
        }
        .header-section {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .logo-container {
            width: 70px;
            height: 70px;
            margin-right: 12px;
        }
        .logo-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .header-text {
            flex: 1;
            text-align: center;
        }
        .header-title {
            font-size: 1.1rem;
            font-weight: bold;
            margin: 0;
            letter-spacing: 0.3px;
        }
        .header-subtitle {
            font-size: 0.8rem;
            margin: 1px 0 0 0;
        }
        .notice-title {
            text-align: center;
            font-weight: bold;
            font-size: 1rem;
            margin: 10px 0;
            text-decoration: underline;
        }
        .account-info {
            margin: 12px 0;
            display: flex;
            justify-content: space-between;
        }
        .account-left, .account-right {
            width: 48%;
        }
        .account-item {
            margin-bottom: 4px;
            font-size: 10px;
        }
        .account-label {
            display: inline-block;
            width: 100px;
            font-weight: normal;
        }
        .account-right .account-label {
            width: 120px;
        }
        .statement-section {
            margin: 12px 0;
        }
        .statement-title {
            font-weight: bold;
            font-size: 0.9rem;
            text-align: center;
            text-decoration: underline;
            margin-bottom: 10px;
        }
        .statement-grid {
            display: flex;
            justify-content: space-between;
            text-align: center;
        }
        .statement-column {
            flex: 1;
            padding: 0 10px;
        }
        .statement-label {
            margin-bottom: 4px;
            font-size: 0.75rem;
        }
        .statement-line {
            border-top: 2px solid #000;
            margin: 3px 0 6px 0;
        }
        .statement-amount {
            font-weight: bold;
            font-size: 0.85rem;
            text-align: center;
            padding: 0;
        }
        .statement-total {
            font-size: 0.95rem;
            color: #dc3545;
        }
        .important-section {
            margin: 12px 0;
        }
        .important-title {
            font-weight: bold;
            font-size: 0.9rem;
            text-align: center;
            text-decoration: underline;
            margin-bottom: 8px;
        }
        .important-list {
            margin: 0;
            padding-left: 20px;
        }
        .important-list li {
            margin-bottom: 4px;
            font-size: 10px;
            line-height: 1.4;
            text-align: justify;
        }
        .signature-section {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
        }
        .signature-left {
            width: 45%;
        }
        .signature-right {
            width: 45%;
            text-align: right;
        }
        .signature-label {
            font-size: 10px;
            margin-bottom: 3px;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 150px;
            margin-top: 20px;
            margin-bottom: 3px;
        }
        .signature-right .signature-line {
            margin-left: auto;
        }
        .signature-name {
            font-weight: bold;
            font-size: 10px;
            margin-top: 3px;
            text-decoration: underline;
        }
        .signature-position {
            font-size: 0.75rem;
            margin-top: 1px;
        }
        .copy-received {
            margin-top: 15px;
        }
        .copy-item {
            margin-bottom: 8px;
        }
        .copy-label {
            font-size: 10px;
            margin-bottom: 3px;
        }
        .copy-line {
            border-top: 1px solid #000;
            width: 100%;
            max-width: 250px;
            margin-top: 15px;
        }
        .btn-print {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .btn-print button, .btn-print a {
            padding: 8px 15px;
            margin: 5px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-print .btn-secondary {
            background: #6c757d;
        }
        .notice-divider {
            border-top: 2px dashed #000;
            margin: 10px 0;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="no-print btn-print">
        <button onclick="window.print()">Print</button>
        <a href="{{ route('disconnection.index') }}" class="btn-secondary">Back</a>
    </div>

    @foreach($consumersWithOutstanding as $index => $consumer)
        @if($index > 0 && $index % 2 == 0)
            <div class="notice-divider page-break"></div>
        @elseif($index > 0)
            <div class="notice-divider"></div>
        @endif
        <div class="notice">
            <!-- Header Section with Logo -->
            <div class="header-section">
                <div class="logo-container">
                    <img src="{{ url('WDMS/img/logo/logo.png') }}" alt="Hagonoy Water District Logo">
                </div>
                <div class="header-text">
                    <h2 class="header-title">HAGONOY WATER DISTRICT</h2>
                    <p class="header-subtitle">Guihing, Hagonoy, Davao del Sur</p>
                </div>
            </div>

            <!-- Notice Title -->
            <div class="notice-title">
                NOTICE OF DISCONNECTION
            </div>

            <!-- Account Information - Two Column Layout -->
            <div class="account-info">
                <div class="account-left">
                    <div class="account-item">
                        <span class="account-label">Acc. No :</span> {{ $consumer->account_no }}
                    </div>
                    <div class="account-item">
                        <span class="account-label">Acc. Name :</span> {{ $consumer->account_name }}
                    </div>
                    <div class="account-item">
                        <span class="account-label">Address :</span> {{ $consumer->address1 }}
                    </div>
                </div>
                <div class="account-right">
                    <div class="account-item">
                        <span class="account-label">Disconnection Date :</span> {{ \Carbon\Carbon::parse($disconnectionDate)->format('m/d/Y') }}
                    </div>
                    <div class="account-item">
                        <span class="account-label">Zone :</span> {{ $consumer->zone_code }}
                    </div>
                    <div class="account-item">
                        <span class="account-label">Card No. :</span> {{ $consumer->card_number ?? $consumer->sequence ?? '1' }}
                    </div>
                </div>
            </div>

            <!-- Statement of Account -->
            <div class="statement-section">
                <div class="statement-title">STATEMENT OF ACCOUNT</div>
                <div class="statement-grid">
                    <div class="statement-column">
                        <div class="statement-label">This Month/ Arrears</div>
                        <div class="statement-line"></div>
                        <div class="statement-amount" style="text-align: center;">₱{{ number_format($consumer->this_month_arrears ?? 0, 2) }}</div>
                    </div>
                    <div class="statement-column">
                        <div class="statement-label">Last Month/ Arrears CY</div>
                        <div class="statement-line"></div>
                        <div class="statement-amount" style="text-align: center;">₱{{ number_format($consumer->last_month_arrears ?? 0, 2) }}</div>
                    </div>
                    <div class="statement-column">
                        <div class="statement-label">Others - A/R</div>
                        <div class="statement-line"></div>
                        <div class="statement-amount" style="text-align: center;">₱0.00</div>
                    </div>
                    <div class="statement-column">
                        <div class="statement-label">Total Amount Due</div>
                        <div class="statement-line"></div>
                        <div class="statement-amount statement-total" style="text-align: center;">₱{{ number_format($consumer->total_outstanding, 2) }}</div>
                    </div>
                </div>
            </div>

            <!-- Important Section -->
            <div class="important-section">
                <div class="important-title">IMPORTANT</div>
                <ol class="important-list">
                    <li>Please pay total amount at Hagonoy Water District Office, Guihing, Hagonoy, Davao del Sur on or before <strong>{{ \Carbon\Carbon::parse($disconnectionDate)->format('m/d/Y') }}</strong>.</li>
                    <li>Failure to pay on the specified date, we will be constrained to cut-off your service connection.</li>
                    <li>If service is discontinued, total amount due plus <strong>P200.00 service fee</strong> will be required to re-establish service.</li>
                    <li>Please disregard this notice if account has been paid in full.</li>
                    <li>Partial payment does not relieve the disconnection of your water service connection.</li>
                </ol>
            </div>

            <!-- Signature Section -->
            <div class="signature-section">
                <div class="signature-left">
                    <div class="signature-label">Prepared by:</div>
                    <div class="signature-line"></div>
                    <div class="signature-name">MARLO B. PORRAS</div>
                    <div class="signature-position">UCSA-E</div>
                </div>
                <div class="signature-right">
                    <div class="signature-label">Approved by:</div>
                    <div class="signature-line"></div>
                    <div class="signature-name">Engr. JOEMAR G. RAUT</div>
                    <div class="signature-position">General Manager</div>
                </div>
            </div>

            <!-- Copy Received Section -->
            <div class="copy-received">
                <div class="copy-item">
                    <div class="copy-label">Copy Received:</div>
                    <div class="copy-line"></div>
                </div>
                <div class="copy-item">
                    <div class="copy-label">Date Received:</div>
                    <div class="copy-line"></div>
                </div>
            </div>
        </div>
    @endforeach
</body>
</html>
