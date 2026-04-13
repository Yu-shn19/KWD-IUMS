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
                margin: 8mm;
            }
            html, body { margin: 0; padding: 0; }
            .no-print { display: none; }
            /* Full width of A4 printable area (210mm - 16mm margins = 194mm) */
            .a4-page {
                width: 194mm;
                max-width: 194mm;
                height: 248mm;
                overflow: hidden;
                page-break-after: always;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .a4-page:last-child { page-break-after: auto; }
            /* Single unbreakable sheet containing both notice copies */
            .notices-sheet {
                width: 194mm;
                max-width: 194mm;
                height: 248mm;
                max-height: 248mm;
                overflow: hidden;
                box-sizing: border-box;
                display: flex;
                flex-direction: column;
                justify-content: flex-start;
                page-break-inside: avoid;
                break-inside: avoid;
                -webkit-column-break-inside: avoid;
            }
            .notice {
                width: 100%;
                flex: 0 0 121mm;
                height: 121mm;
                min-height: 0;
                max-height: 121mm;
                margin: 0 auto 3px;
                padding: 8px 12px;
                box-sizing: border-box;
                overflow: hidden;
                page-break-after: avoid;
                page-break-before: avoid;
            }
        }
        body {
            font-family: Arial, sans-serif;
            padding: 15px;
            font-size: 10px;
            background: white;
        }
        .a4-page {
            max-width: 8.27in;
            margin: 0 auto 20px;
        }
        .notice {
            max-width: 100%;
            padding: 12px 15px;
            background: white;
            box-sizing: border-box;
            border: 1px solid #eee;
        }
        .header-section {
            display: flex;
            align-items: center;
            margin-bottom: 6px;
        }
        .logo-container {
            width: 50px;
            height: 50px;
            margin-right: 10px;
        }
        .logo-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .header-text { flex: 1; text-align: center; }
        .header-title { font-size: 0.95rem; font-weight: bold; margin: 0; }
        .header-subtitle { font-size: 0.7rem; margin: 0; }
        .notice-title {
            text-align: center;
            font-weight: bold;
            font-size: 0.9rem;
            margin: 6px 0;
            text-decoration: underline;
        }
        .account-info {
            margin: 8px 0;
            display: flex;
            justify-content: space-between;
        }
        .account-left, .account-right { width: 48%; }
        .account-item { margin-bottom: 2px; font-size: 9px; }
        .account-label { display: inline-block; width: 95px; font-weight: normal; }
        .account-right .account-label { width: 115px; }
        .statement-section { margin: 8px 0; }
        .statement-title {
            font-weight: bold;
            font-size: 0.8rem;
            text-align: center;
            text-decoration: underline;
            margin-bottom: 6px;
        }
        .statement-grid {
            display: flex;
            justify-content: space-between;
            text-align: center;
        }
        .statement-column { flex: 1; padding: 0 6px; }
        .statement-label { margin-bottom: 2px; font-size: 0.7rem; }
        .statement-line { border-top: 1px solid #000; margin: 2px 0 4px 0; }
        .statement-amount { font-weight: bold; font-size: 0.8rem; text-align: center; }
        .statement-total { font-size: 0.85rem; color: #dc3545; }
        .important-section { margin: 8px 0; }
        .important-title {
            font-weight: bold;
            font-size: 0.8rem;
            text-align: center;
            text-decoration: underline;
            margin-bottom: 4px;
        }
        .important-list { margin: 0; padding-left: 18px; }
        .important-list li {
            margin-bottom: 2px;
            font-size: 9px;
            line-height: 1.3;
            text-align: justify;
        }
        .signature-section {
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
        }
        .signature-left, .signature-right { width: 45%; }
        .signature-right { text-align: right; }
        .signature-label { font-size: 9px; margin-bottom: 2px; }
        .signature-line {
            border-top: 1px solid #000;
            width: 120px;
            margin-top: 12px;
            margin-bottom: 2px;
        }
        .signature-right .signature-line { margin-left: auto; }
        .signature-name { font-weight: bold; font-size: 9px; margin-top: 2px; text-decoration: underline; }
        .signature-position { font-size: 0.7rem; margin-top: 0; }
        .copy-received { margin-top: 8px; }
        .copy-item { margin-bottom: 4px; }
        .copy-label { font-size: 9px; margin-bottom: 2px; }
        .copy-line {
            border-top: 1px solid #000;
            width: 100%;
            max-width: 200px;
            margin-top: 8px;
        }
        .no-print.btn-print {
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
        .btn-print .btn-secondary { background: #6c757d; }
    </style>
</head>
<body>
    <div class="no-print btn-print">
        <button onclick="window.print()">Print</button>
        <a href="{{ route('disconnection.index') }}" class="btn-secondary">Back</a>
    </div>

    @foreach($consumersWithOutstanding as $consumer)
        <div class="a4-page">
            <div class="notices-sheet">
            <div class="notice">
                <div class="header-section">
                    <div class="logo-container">
                        <img src="{{ url('WDMS/img/logo/logo.png') }}" alt="Hagonoy Water District Logo">
                    </div>
                    <div class="header-text">
                        <h2 class="header-title">HAGONOY WATER DISTRICT</h2>
                        <p class="header-subtitle">Guihing, Hagonoy, Davao del Sur</p>
                    </div>
                </div>
                <div class="notice-title">NOTICE OF DISCONNECTION</div>
                <div class="account-info">
                    <div class="account-left">
                        <div class="account-item"><span class="account-label">Acc. No :</span> {{ $consumer->account_no }}</div>
                        <div class="account-item"><span class="account-label">Acc. Name :</span> {{ $consumer->account_name }}</div>
                        <div class="account-item"><span class="account-label">Address :</span> {{ $consumer->address1 }}</div>
                    </div>
                    <div class="account-right">
                        <div class="account-item"><span class="account-label">Disconnection Date :</span> {{ \Carbon\Carbon::parse($disconnectionDate)->format('m/d/Y') }}</div>
                        <div class="account-item"><span class="account-label">Zone :</span> {{ $consumer->zone_code }}</div>
                        <div class="account-item"><span class="account-label">Card No. :</span> {{ $consumer->card_number ?? $consumer->sequence ?? '1' }}</div>
                    </div>
                </div>
                <div class="statement-section">
                    <div class="statement-title">STATEMENT OF ACCOUNT</div>
                    <div class="statement-grid">
                        <div class="statement-column">
                            <div class="statement-label">This Month/ Arrears</div>
                            <div class="statement-line"></div>
                            <div class="statement-amount">₱{{ number_format($consumer->this_month_arrears ?? 0, 2) }}</div>
                        </div>
                        <div class="statement-column">
                            <div class="statement-label">Last Month/ Arrears CY</div>
                            <div class="statement-line"></div>
                            <div class="statement-amount">₱{{ number_format($consumer->last_month_arrears ?? 0, 2) }}</div>
                        </div>
                        <div class="statement-column">
                            <div class="statement-label">Others - A/R</div>
                            <div class="statement-line"></div>
                            <div class="statement-amount">₱{{ number_format($consumer->others_ar ?? 0, 2) }}</div>
                        </div>
                        <div class="statement-column">
                            <div class="statement-label">Total Amount Due</div>
                            <div class="statement-line"></div>
                            <div class="statement-amount statement-total">₱{{ number_format($consumer->total_outstanding, 2) }}</div>
                        </div>
                    </div>
                </div>
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
                <div class="signature-section">
                    <div class="signature-left">
                        <div class="signature-label">Prepared by:</div>
                        <img src="{{ asset('images/signatures/marlo-sign.png') }}"
                             alt="Signature of Marlo B. Porras"
                             style="height: 40px; margin-top: 6px; display: block;">
                        <div class="signature-name">MARLO B. PORRAS</div>
                        <div class="signature-position">UCSA-E</div>
                    </div>
                    <div class="signature-right">
                        <div class="signature-label">Approved by:</div>
                        <img src="{{ asset('images/signatures/gm-sign.png') }}"
                             alt="Signature of Engr. Joemar G. Raut"
                             style="height: 40px; margin-top: 6px; display: block; margin-left: auto;">
                        <div class="signature-name">Engr. JOEMAR G. RAUT</div>
                        <div class="signature-position">General Manager</div>
                    </div>
                </div>
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

            <div class="notice notice-second">
                <div class="header-section">
                    <div class="logo-container">
                        <img src="{{ url('WDMS/img/logo/logo.png') }}" alt="Hagonoy Water District Logo">
                    </div>
                    <div class="header-text">
                        <h2 class="header-title">HAGONOY WATER DISTRICT</h2>
                        <p class="header-subtitle">Guihing, Hagonoy, Davao del Sur</p>
                    </div>
                </div>
                <div class="notice-title">NOTICE OF DISCONNECTION</div>
                <div class="account-info">
                    <div class="account-left">
                        <div class="account-item"><span class="account-label">Acc. No :</span> {{ $consumer->account_no }}</div>
                        <div class="account-item"><span class="account-label">Acc. Name :</span> {{ $consumer->account_name }}</div>
                        <div class="account-item"><span class="account-label">Address :</span> {{ $consumer->address1 }}</div>
                    </div>
                    <div class="account-right">
                        <div class="account-item"><span class="account-label">Disconnection Date :</span> {{ \Carbon\Carbon::parse($disconnectionDate)->format('m/d/Y') }}</div>
                        <div class="account-item"><span class="account-label">Zone :</span> {{ $consumer->zone_code }}</div>
                        <div class="account-item"><span class="account-label">Card No. :</span> {{ $consumer->card_number ?? $consumer->sequence ?? '1' }}</div>
                    </div>
                </div>
                <div class="statement-section">
                    <div class="statement-title">STATEMENT OF ACCOUNT</div>
                    <div class="statement-grid">
                        <div class="statement-column">
                            <div class="statement-label">This Month/ Arrears</div>
                            <div class="statement-line"></div>
                            <div class="statement-amount">₱{{ number_format($consumer->this_month_arrears ?? 0, 2) }}</div>
                        </div>
                        <div class="statement-column">
                            <div class="statement-label">Last Month/ Arrears CY</div>
                            <div class="statement-line"></div>
                            <div class="statement-amount">₱{{ number_format($consumer->last_month_arrears ?? 0, 2) }}</div>
                        </div>
                        <div class="statement-column">
                            <div class="statement-label">Others - A/R</div>
                            <div class="statement-line"></div>
                            <div class="statement-amount">₱{{ number_format($consumer->others_ar ?? 0, 2) }}</div>
                        </div>
                        <div class="statement-column">
                            <div class="statement-label">Total Amount Due</div>
                            <div class="statement-line"></div>
                            <div class="statement-amount statement-total">₱{{ number_format($consumer->total_outstanding, 2) }}</div>
                        </div>
                    </div>
                </div>
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
                <div class="signature-section">
                    <div class="signature-left">
                        <div class="signature-label">Prepared by:</div>
                        <img src="{{ asset('images/signatures/marlo-sign.png') }}"
                             alt="Signature of Marlo B. Porras"
                             style="height: 40px; margin-top: 6px; display: block;">
                        <div class="signature-name">MARLO B. PORRAS</div>
                        <div class="signature-position">UCSA-E</div>
                    </div>
                    <div class="signature-right">
                        <div class="signature-label">Approved by:</div>
                        <img src="{{ asset('images/signatures/gm-sign.png') }}"
                             alt="Signature of Engr. Joemar G. Raut"
                             style="height: 40px; margin-top: 6px; display: block; margin-left: auto;">
                        <div class="signature-name">Engr. JOEMAR G. RAUT</div>
                        <div class="signature-position">General Manager</div>
                    </div>
                </div>
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
            </div>
        </div>
    @endforeach
</body>
</html>
