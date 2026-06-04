@once
<style>
    .notice-document {
        font-family: Arial, Helvetica, sans-serif;
        border: 1px solid #000;
        padding: 36px 40px 44px;
        font-size: 16pt;
        color: #000;
        background: #fff;
        box-sizing: border-box;
        max-width: 210mm;
        margin-left: auto;
        margin-right: auto;
    }

    .notice-document .nd-header {
        display: grid;
        grid-template-columns: 108px 1fr 108px;
        align-items: center;
        margin-bottom: 28px;
    }

    .notice-document .nd-logo-box {
        width: 96px;
        height: 96px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 4px;
        box-sizing: border-box;
    }

    .notice-document .nd-logo-box img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .notice-document .nd-title {
        grid-column: 2;
        text-align: center;
        font-weight: bold;
        font-size: 16pt;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin: 0;
        line-height: 1.2;
    }

    .notice-document .nd-header-spacer {
        display: block;
    }

    .notice-document .nd-intro {
        text-align: left;
        margin: 0 0 32px;
        line-height: 1.55;
        padding: 0;
    }

    .notice-document .nd-soa-heading {
        text-align: center;
        font-weight: bold;
        font-size: 16pt;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin: 0 0 22px;
    }

    .notice-document .nd-soa-section {
        margin-bottom: 4px;
    }

    .notice-document .nd-soa-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        column-gap: 2.5rem;
        align-items: start;
    }

    .notice-document .nd-soa-col--left,
    .notice-document .nd-soa-col--right {
        text-align: left;
    }

    .notice-document .nd-soa-row {
        display: flex;
        align-items: baseline;
        margin-bottom: 16px;
        line-height: 1.35;
    }

    .notice-document .nd-soa-label {
        flex: 0 0 auto;
        white-space: nowrap;
        padding-right: 8px;
        font-weight: bold;
    }

    .notice-document .nd-soa-value {
        flex: 1;
        min-width: 0;
        border-bottom: 1px solid #000;
        padding: 0 6px 5px;
        word-break: break-word;
    }

    .notice-document .nd-address-row .nd-soa-value {
        min-height: 2.4em;
    }

    .notice-document .nd-important {
        margin-top: 28px;
        text-align: left;
    }

    /* Same row: Important Notes (left) + Total Amount Due (right), then numbered list below */
    .notice-document .nd-notes-summary-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 1.25rem;
        margin-bottom: 10px;
    }

    .notice-document .nd-notes-summary-left {
        flex: 0 1 auto;
        font-size: 16pt;
    }

    .notice-document .nd-notes-summary-right {
        flex: 0 1 340px;
        min-width: 0;
    }

    .notice-document .nd-total-line {
        display: flex;
        align-items: baseline;
        justify-content: flex-end;
        gap: 6px;
        width: 100%;
    }

    .notice-document .nd-total-line-label {
        font-weight: bold;
        flex: 0 0 auto;
        white-space: nowrap;
    }

    .notice-document .nd-total-line-value {
        flex: 0 0 auto;
        border-bottom: 1px solid #000;
        font-weight: bold;
        text-align: right;
        padding: 0 2px 5px 4px;
        color: #dc3545;
    }

    .notice-document .nd-important-list {
        margin: 0;
        padding-left: 24px;
        line-height: 1.55;
        text-align: left;
    }

    .notice-document .nd-important-list li {
        margin-bottom: 12px;
    }

    .notice-document .nd-sign-row {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        column-gap: 1.5rem;
        margin-top: 48px;
        font-size: 16pt;
    }

    .notice-document .nd-sign-col {
        text-align: left;
    }

    /* Shrink-wrap to name/title width; center signature only over that block */
    .notice-document .nd-sign-name-stack {
        display: inline-block;
        max-width: 100%;
        vertical-align: top;
    }

    .notice-document .nd-sign-img-holder {
        width: 100%;
        text-align: center;
    }

    /* Third column: reserve same vertical space as signature row so BOD lines up with names */
    .notice-document .nd-sign-img-holder--spacer {
        min-height: calc(4px + 52px + 6px);
        box-sizing: border-box;
    }

    .notice-document .nd-sign-label {
        margin: 0 0 6px;
    }

    .notice-document .nd-sign-img {
        display: inline-block;
        height: 52px;
        width: auto;
        max-width: 100%;
        margin: 4px 0 6px;
        object-fit: contain;
        object-position: center center;
        vertical-align: bottom;
    }

    .notice-document .nd-sign-name {
        margin: 0 0 4px;
        font-weight: bold;
        text-align: left;
    }

    .notice-document .nd-sign-title {
        margin: 0;
        font-weight: normal;
        font-size: 16pt;
        text-align: left;
    }

    .notice-document .nd-sign-title--phantom {
        visibility: hidden;
        user-select: none;
        line-height: 1.35;
    }

    .notice-document .nd-bod {
        margin: 0;
        font-weight: bold;
        line-height: 1.35;
    }

    .notice-document .nd-sign-row .nd-sign-col:last-child .nd-bod {
        margin: 0 0 4px;
    }

    .notice-document .nd-received {
        margin-top: 48px;
        display: flex;
        align-items: baseline;
        gap: 12px;
        flex-wrap: wrap;
        text-align: left;
    }

    .notice-document .nd-received-label {
        font-weight: bold;
        white-space: nowrap;
    }

    .notice-document .nd-received-line {
        flex: 0 0 auto;
        width: 240px;
        max-width: 55%;
        border-bottom: 1px solid #000;
        min-height: 1.4em;
    }

    .notice-document.notice-document--compact {
        padding: 22px 26px 30px;
        font-size: 16pt;
        max-width: 100%;
    }

    .notice-document.notice-document--compact .nd-header {
        margin-bottom: 18px;
    }

    .notice-document.notice-document--compact .nd-intro {
        margin-bottom: 18px;
    }

    .notice-document.notice-document--compact .nd-soa-heading {
        margin-bottom: 14px;
    }

    .notice-document.notice-document--compact .nd-soa-row {
        margin-bottom: 10px;
    }

    .notice-document.notice-document--compact .nd-important {
        margin-top: 20px;
    }

    .notice-document.notice-document--compact .nd-notes-summary-row {
        margin-bottom: 8px;
    }

    .notice-document.notice-document--compact .nd-sign-img {
        height: 44px;
        margin: 3px 0 4px;
    }

    .notice-document.notice-document--compact .nd-sign-img-holder--spacer {
        min-height: calc(3px + 44px + 4px);
    }

    .notice-document.notice-document--compact .nd-sign-row {
        margin-top: 32px;
    }

    .notice-document.notice-document--compact .nd-received {
        margin-top: 32px;
    }

    .notices-sheet-two-up {
        display: flex;
        flex-direction: column;
        gap: 4mm;
        width: 100%;
        align-items: stretch;
    }

    /*
     * Print: two cut-apart notices per A4 page — compact type & spacing so both fit.
     */
    @media print {
        /*
         * Two cut-apart notices on one A4: keep vertical rhythm compact enough that both
         * fit with page-break-inside: avoid (see print.blade.php).
         */
        .notice-document.notice-document--a4-print {
            max-width: none;
            width: 100%;
            padding: 2mm 3mm 3mm;
            margin: 0;
            font-size: 10pt;
            line-height: 1.34;
            border: 1px solid #000;
        }

        .notice-document.notice-document--a4-print .nd-header {
            grid-template-columns: 48px 1fr 48px;
            margin-bottom: 7px;
        }

        .notice-document.notice-document--a4-print .nd-logo-box {
            width: 44px;
            height: 44px;
            padding: 2px;
        }

        .notice-document.notice-document--a4-print .nd-title {
            font-size: 10pt;
            letter-spacing: 0.04em;
        }

        .notice-document.notice-document--a4-print .nd-intro {
            margin-bottom: 8px;
            line-height: 1.38;
        }

        .notice-document.notice-document--a4-print .nd-soa-heading {
            font-size: 10pt;
            margin-bottom: 7px;
        }

        .notice-document.notice-document--a4-print .nd-soa-grid {
            column-gap: 12px;
        }

        .notice-document.notice-document--a4-print .nd-soa-row {
            margin-bottom: 6px;
            line-height: 1.28;
        }

        .notice-document.notice-document--a4-print .nd-soa-value {
            padding: 0 3px 3px;
        }

        .notice-document.notice-document--a4-print .nd-address-row .nd-soa-value {
            min-height: 1.35em;
        }

        .notice-document.notice-document--a4-print .nd-important {
            margin-top: 8px;
        }

        .notice-document.notice-document--a4-print .nd-notes-summary-row {
            align-items: flex-end;
            gap: 8px;
            margin-bottom: 6px;
        }

        .notice-document.notice-document--a4-print .nd-notes-summary-left {
            font-size: 10pt;
        }

        .notice-document.notice-document--a4-print .nd-notes-summary-right {
            flex-basis: 44%;
            max-width: 54%;
        }

        .notice-document.notice-document--a4-print .nd-total-line {
            gap: 4px;
        }

        .notice-document.notice-document--a4-print .nd-total-line-value {
            min-width: 0;
            padding: 0 2px 3px 3px;
            font-size: 10pt;
            color: #dc3545 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .notice-document.notice-document--a4-print .nd-important-list {
            padding-left: 16px;
            line-height: 1.36;
        }

        .notice-document.notice-document--a4-print .nd-important-list li {
            margin-bottom: 4px;
        }

        .notice-document.notice-document--a4-print .nd-sign-row {
            margin-top: 10px;
            column-gap: 10px;
            font-size: 10pt;
        }

        .notice-document.notice-document--a4-print .nd-sign-img {
            height: 26px !important;
            margin: 3px 0 4px !important;
        }

        .notice-document.notice-document--a4-print .nd-sign-img-holder--spacer {
            min-height: calc(3px + 26px + 4px);
        }

        .notice-document.notice-document--a4-print .nd-sign-title {
            font-size: 10pt;
        }

        .notice-document.notice-document--a4-print .nd-received {
            margin-top: 10px;
            gap: 6px;
        }

        .notice-document.notice-document--a4-print .nd-received-line {
            min-height: 1.05em;
            width: 160px;
            max-width: 45%;
        }
    }
</style>
@endonce
