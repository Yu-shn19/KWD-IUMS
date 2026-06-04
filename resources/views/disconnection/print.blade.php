<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notice of Disconnection</title>
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
        }
        html, body {
            margin: 0;
            padding: 0;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            background: #e9ecef;
            padding: 15px;
        }
        @media print {
            @page {
                size: A4 portrait;
                margin: 4mm;
            }
            * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
            html, body {
                background: #fff !important;
                padding: 0;
                margin: 0;
                width: 100%;
            }
            .a4-page {
                width: 100%;
                max-width: none;
                margin: 0;
                padding: 0;
                box-shadow: none !important;
                page-break-after: always;
            }
            .a4-page:last-child {
                page-break-after: auto;
            }
            /* Stack both notices naturally — avoid min-height/flex spread that pushes page breaks */
            body.disconnection-notice-print .notices-sheet-two-up {
                gap: 3mm;
            }
            body.disconnection-notice-print .notice-document {
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }
        .no-print.btn-print {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .btn-print button,
        .btn-print a {
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
        .a4-page {
            width: 210mm;
            max-width: 100%;
            margin: 0 auto 24px;
            padding: 0;
            background: #fff;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.12);
        }
    </style>
</head>
<body class="disconnection-notice-print">
    <div class="no-print btn-print">
        <button type="button" onclick="window.print()">Print</button>
        <a href="{{ route('disconnection.index') }}" class="btn-secondary">Back</a>
    </div>

    @foreach($consumersWithOutstanding as $consumer)
        <div class="a4-page">
            <div class="notices-sheet-two-up">
                @include('disconnection.partials.notice-document', [
                    'consumer' => $consumer,
                    'disconnectionDate' => $disconnectionDate,
                    'compact' => true,
                    'forPrint' => true,
                ])
                @include('disconnection.partials.notice-document', [
                    'consumer' => $consumer,
                    'disconnectionDate' => $disconnectionDate,
                    'compact' => true,
                    'forPrint' => true,
                ])
            </div>
        </div>
    @endforeach
</body>
</html>
