<!DOCTYPE html>
<html lang="en">
@include('partials.header')

<body id="page-top">
    <div id="wrapper">
        <!-- Sidebar -->
        @include('partials.sidebar')
        <!-- Sidebar -->
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Topbar -->
                @include('partials.navbar')
                <!-- Topbar -->

                <!-- Container Fluid-->
                <div class="container-fluid" id="container-wrapper">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4 no-print">
                        <h1 class="h3 mb-0 text-gray-800">AR Aging Summary</h1>
                        <div>
                            <button type="button" class="btn btn-primary btn-sm mr-2" onclick="generateReport()">
                                <i class="fas fa-sync-alt mr-1"></i>Generate
                            </button>
                            <a href="{{ route('ar-aging-summary.export', request()->all()) }}" class="btn btn-success btn-sm mr-2">
                                <i class="fas fa-file-excel mr-1"></i>Export Excel
                            </a>
                            <button type="button" class="btn btn-danger btn-sm" onclick="window.print()">
                                <i class="fas fa-print mr-1"></i>Print
                            </button>
                        </div>
                    </div>

                    <!-- Filter Card -->
                    <form method="GET" action="{{ route('ar-aging-summary') }}" id="filterForm" class="no-print">
                        <div class="row mb-3">
                            <div class="col-lg-12">
                                <div class="card shadow-sm">
                                    <div class="card-body py-3">
                                        <div class="row align-items-end">
                                            <!-- Status -->
                                            <div class="col-md-2">
                                                <label class="small font-weight-bold mb-1">Status</label>
                                                <select name="status" class="form-control form-control-sm">
                                                    <option value="">All Status</option>
                                                    <option value="A - ACTIVE" {{ $status == 'A - ACTIVE' ? 'selected' : '' }}>A - ACTIVE</option>
                                                    <option value="P - PENDING" {{ $status == 'P - PENDING' ? 'selected' : '' }}>P - PENDING</option>
                                                    <option value="X - DISCONNECTED" {{ $status == 'X - DISCONNECTED' ? 'selected' : '' }}>X - DISCONNECTED</option>
                                                </select>
                                            </div>

                                            <!-- Balance Filter -->
                                            <div class="col-md-2">
                                                <label class="small font-weight-bold mb-1">Balance Filter</label>
                                                <select name="balance_filter" class="form-control form-control-sm">
                                                    <option value="" {{ ($selectedBalanceFilter ?? '') == '' ? 'selected' : '' }}>All Data</option>
                                                    <option value="with_balance" {{ ($selectedBalanceFilter ?? '') == 'with_balance' ? 'selected' : '' }}>With Balance</option>
                                                </select>
                                            </div>

                                            <!-- Zone/Route -->
                                            <div class="col-md-2">
                                                <label class="small font-weight-bold mb-1">Zone / Route</label>
                                                <select name="zone" class="form-control form-control-sm">
                                                    <option value="">All Zones</option>
                                                    @foreach(($zones ?? []) as $zoneOption)
                                                        <option value="{{ $zoneOption }}" {{ ($selectedZone ?? '') == $zoneOption ? 'selected' : '' }}>{{ $zoneOption }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <!-- Category -->
                                            <div class="col-md-2">
                                                <label class="small font-weight-bold mb-1">Category</label>
                                                <select name="category" class="form-control form-control-sm">
                                                    <option value="">All Categories</option>
                                                    @foreach(($categories ?? []) as $categoryOption)
                                                        <option value="{{ $categoryOption }}" {{ ($selectedCategory ?? '') == $categoryOption ? 'selected' : '' }}>{{ $categoryOption }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <!-- As of -->
                                            <div class="col-md-2">
                                                <label class="small font-weight-bold mb-1">As of</label>
                                                <input type="date" name="as_of" class="form-control form-control-sm" value="{{ isset($asOf) ? $asOf->format('Y-m-d') : now()->format('Y-m-d') }}">
                                            </div>

                                            <!-- Billing Cut-Off -->
                                            <div class="col-md-2">
                                                <label class="small font-weight-bold mb-1">Billing Cut-Off</label>
                                                <input type="date" name="billing_cutoff" class="form-control form-control-sm" value="{{ isset($billingCutOff) ? $billingCutOff->format('Y-m-d') : now()->format('Y-m-d') }}">
                                            </div>

                                            <!-- Payment Cut-Off -->
                                            <div class="col-md-2">
                                                <label class="small font-weight-bold mb-1">Payment Cut-Off</label>
                                                <input type="date" name="payment_cutoff" class="form-control form-control-sm" value="{{ isset($paymentCutOff) ? $paymentCutOff->format('Y-m-d') : now()->format('Y-m-d') }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                    @if(!empty($reportError))
                        <div class="alert alert-warning alert-dismissible fade show no-print" role="alert">
                            <i class="fas fa-exclamation-triangle mr-2"></i>{{ $reportError }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    <!-- Report Tabs (screen only; avoids extra vertical space in print) -->
                    <div class="row mb-2 no-print">
                        <div class="col-md-12">
                            <ul class="nav nav-tabs">
                                <li class="nav-item">
                                    <a class="nav-link active" data-toggle="tab" href="#detail">Detail</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#arSummaryRecap">AR Summary Recap</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#arSummaryZone">AR Summary per Zone</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#arSummaryCategory">AR Summary by Category</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#materialsBalance">Materials Balance</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#cdaAging">CDA-Aging</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#cdaArRecap">CDA-AR Summary Recap</a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Print Header (only visible when printing) -->
                    <div class="print-header" style="display: none;">
                        <div style="text-align: center; margin-bottom: 15px;">
                            <h1 style="margin: 0; font-size: 18px; font-weight: bold; letter-spacing: 1px;">HAGONOY WATER DISTRICT</h1>
                            <p style="margin: 3px 0; font-size: 12px;">Guihing Hagonoy</p>
                            <h2 style="margin: 8px 0; font-size: 16px; font-weight: bold; letter-spacing: 0.5px;">AGING OF ACCOUNTS</h2>
                            <p style="margin: 3px 0; font-size: 11px;">
                                @if($selectedZone)
                                    {{ $selectedZone }}
                                @else
                                    011
                                @endif
                            </p>
                            <p style="margin: 3px 0; font-size: 11px;">{{ isset($asOf) ? $asOf->format('m/d/Y') : now()->format('m/d/Y') }}</p>
                        </div>
                    </div>

                    <!-- Tab Content -->
                    <div class="tab-content">
                        <!-- Detail Tab -->
                        <div class="tab-pane fade show active" id="detail">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body p-0">
                                            <div class="table-responsive" style="max-height: 600px; overflow: auto;">
                                                <table class="table table-sm table-bordered table-hover mb-0" style="font-size: 10px;" id="agingTable">
                                                    <thead class="thead-light" style="position: sticky; top: 0; z-index: 10;">
                                                        <tr>
                                                            <th class="text-center py-2 px-1 no-print-col" style="min-width: 70px;">Zone_code</th>
                                                            <th class="text-center py-2 px-1 no-print-col" style="min-width: 70px;">Sequence</th>
                                                            <th class="text-center py-2 px-1" style="min-width: 100px;">ACCOUNT NO</th>
                                                            <th class="py-2 px-1" style="min-width: 180px;">ACCOUNT NAME</th>
                                                            <th class="text-center py-2 px-1 no-print-col" style="min-width: 60px;">STATUS</th>
                                                            <th class="text-center py-2 px-1 no-print-col" style="min-width: 90px;">Category_code</th>
                                                            <th class="text-center py-2 px-1" style="min-width: 90px;">CURRENT</th>
                                                            <th class="text-center py-2 px-1" style="min-width: 70px;">_30</th>
                                                            <th class="text-center py-2 px-1" style="min-width: 70px;">_60</th>
                                                            <th class="text-center py-2 px-1" style="min-width: 70px;">_90</th>
                                                            <th class="text-center py-2 px-1" style="min-width: 80px;">_OVER90</th>
                                                            <th class="text-center py-2 px-1" style="min-width: 80px;">PREV YEAR</th>
                                                            <th class="text-center py-2 px-1" style="min-width: 80px;">BALANCE</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse(($detailRecords ?? []) as $record)
                                                            <tr>
                                                                <td class="text-center py-1 no-print-col">{{ $record['zone'] }}</td>
                                                                <td class="text-center py-1 no-print-col">{{ $record['sequence'] }}</td>
                                                                <td class="text-center py-1">{{ $record['account_number'] }}</td>
                                                                <td class="py-1 px-1">{{ $record['account_name'] }}</td>
                                                                <td class="text-center py-1 no-print-col">{{ $record['status_code'] }}</td>
                                                                <td class="text-center py-1 no-print-col">{{ $record['category_code'] }}</td>
                                                                <td class="text-right py-1 px-1">{{ number_format($record['current'] ?? 0, 2) }}</td>
                                                                <td class="text-right py-1 px-1">{{ number_format($record['_30'] ?? 0, 2) }}</td>
                                                                <td class="text-right py-1 px-1">{{ number_format($record['_60'] ?? 0, 2) }}</td>
                                                                <td class="text-right py-1 px-1">{{ number_format($record['_90'] ?? 0, 2) }}</td>
                                                                <td class="text-right py-1 px-1">{{ number_format($record['_over90'] ?? 0, 2) }}</td>
                                                                <td class="text-right py-1 px-1">{{ number_format($record['prev_year'] ?? 0, 2) }}</td>
                                                                <td class="text-right py-1 px-1 font-weight-bold">{{ number_format($record['balance'], 2) }}</td>
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="11" class="text-center py-4">
                                                                    <em class="text-muted">No AR aging records found for the selected criteria.</em>
                                                                </td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        
                                        <!-- Totals table for print only - appears after main table, only on last page -->
                                        <table class="print-totals-table" style="display: none; width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 15px;">
                                            <!-- COUNT row -->
                                            <tr>
                                                <td colspan="10" class="text-left" style="border: none; padding: 6px 5px; font-size: 9px; font-weight: bold; color: #333;">COUNT: {{ count($detailRecords ?? []) }}</td>
                                            </tr>
                                            <!-- TOTALS row - calculating from all detailRecords to ensure all data is included -->
                                            @php
                                                $detailRecordsForTotals = $detailRecords ?? [];
                                                $totalCurrent = collect($detailRecordsForTotals)->sum(function($record) { return (float)($record['current'] ?? 0); });
                                                $total30 = collect($detailRecordsForTotals)->sum(function($record) { return (float)($record['_30'] ?? 0); });
                                                $total60 = collect($detailRecordsForTotals)->sum(function($record) { return (float)($record['_60'] ?? 0); });
                                                $total90 = collect($detailRecordsForTotals)->sum(function($record) { return (float)($record['_90'] ?? 0); });
                                                $totalOver90 = collect($detailRecordsForTotals)->sum(function($record) { return (float)($record['_over90'] ?? 0); });
                                                $totalPrevYear = collect($detailRecordsForTotals)->sum(function($record) { return (float)($record['prev_year'] ?? 0); });
                                                $totalBalance = collect($detailRecordsForTotals)->sum(function($record) { return (float)($record['balance'] ?? 0); });
                                            @endphp
                                            <tr style="background-color: #f8f9fa !important; font-weight: bold;">
                                                <td class="text-right" style="border: none; padding: 8px 5px; font-size: 9px; font-weight: bold; color: #333;"></td>
                                                <td class="text-right" style="border: none; padding: 8px 5px; font-size: 9px; font-weight: bold; color: #333;">{{ $selectedZone ? $selectedZone : 'ALL' }} TOTALS:</td>
                                                <td class="text-right" style="border: none; padding: 8px 5px; font-size: 9px; font-weight: bold; color: #333;">{{ number_format($totalCurrent, 2) }}</td>
                                                <td class="text-right" style="border: none; padding: 8px 5px; font-size: 9px; font-weight: bold; color: #333;">{{ number_format($total30, 2) }}</td>
                                                <td class="text-right" style="border: none; padding: 8px 5px; font-size: 9px; font-weight: bold; color: #333;">{{ number_format($total60, 2) }}</td>
                                                <td class="text-right" style="border: none; padding: 8px 5px; font-size: 9px; font-weight: bold; color: #333;">{{ number_format($total90, 2) }}</td>
                                                <td class="text-right" style="border: none; padding: 8px 5px; font-size: 9px; font-weight: bold; color: #333;">{{ number_format($totalOver90, 2) }}</td>
                                                <td class="text-right" style="border: none; padding: 8px 5px; font-size: 9px; font-weight: bold; color: #333;">{{ number_format($totalPrevYear, 2) }}</td>
                                                <td class="text-right" style="border: none; padding: 8px 5px; font-size: 9px; font-weight: bold; color: #333;">{{ number_format($totalBalance, 2) }}</td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Signature section for print only - appears at the very last page -->
                                        <div class="print-signatures" style="display: none; margin-top: 40px; margin-left: auto; margin-right: auto; width: 80%; max-width: 600px; page-break-inside: avoid;">
                                            <div style="display: flex; justify-content: space-between; width: 100%;">
                                                <div style="text-align: left;">
                                                    <p style="margin: 0 0 5px 0; font-size: 10px; line-height: 1.4;">Prepared by :</p>
                                                    <p style="margin: 0; font-size: 11px; font-weight: bold; text-decoration: underline; text-underline-offset: 2px;">
                                                        @php
                                                            try {
                                                                $preparedBy = auth()->check() && auth()->user()
                                                                    ? trim(strtoupper(auth()->user()->last_name ?? '') . ', ' . strtoupper(auth()->user()->first_name ?? '') . (!empty(auth()->user()->middle_name) ? ' ' . strtoupper(substr(auth()->user()->middle_name, 0, 1)) . '.' : '') . (!empty(auth()->user()->extension) ? ' ' . strtoupper(auth()->user()->extension) : ''))
                                                                    : 'SYSTEM';
                                                                if ($preparedBy === '') $preparedBy = 'SYSTEM';
                                                            } catch (\Throwable $e) { $preparedBy = 'SYSTEM'; }
                                                        @endphp
                                                        {{ $preparedBy }}
                                                    </p>
                                                </div>
                                                <div style="text-align: left;">
                                                    <p style="margin: 0 0 5px 0; font-size: 10px; line-height: 1.4;">Verified by :</p>
                                                    <p style="margin: 0; font-size: 11px; font-weight: bold; text-decoration: underline; text-underline-offset: 2px;">
                                                        Engr. JOEMAR G. RAUT
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-light py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    @if($selectedZone)
                                                        Zone {{ $selectedZone }} - 
                                                    @endif
                                                    As of {{ isset($asOf) ? $asOf->format('m/d/Y') : now()->format('m/d/Y') }}
                                                </small>
                                                <small class="text-muted">
                                                    Total Balance: <strong class="text-danger">₱ {{ number_format($totals['total_balance'] ?? 0, 2) }}</strong>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- AR Summary Recap Tab -->
                        <div class="tab-pane fade" id="arSummaryRecap">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered table-hover mb-0" style="font-size: 11px;">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th class="text-center py-2 px-2">Total Accounts</th>
                                                            <th class="text-right py-2 px-2">Current</th>
                                                            <th class="text-right py-2 px-2">_30</th>
                                                            <th class="text-right py-2 px-2">_60</th>
                                                            <th class="text-right py-2 px-2">_90</th>
                                                            <th class="text-right py-2 px-2">_over90</th>
                                                            <th class="text-right py-2 px-2">Prev Year</th>
                                                            <th class="text-right py-2 px-2">Total Balance</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td class="text-center py-2">{{ $arSummaryRecap['total_accounts'] ?? 0 }}</td>
                                                            <td class="text-right py-2 px-2">₱ {{ number_format($arSummaryRecap['current'] ?? 0, 2) }}</td>
                                                            <td class="text-right py-2 px-2">₱ {{ number_format($arSummaryRecap['_30'] ?? 0, 2) }}</td>
                                                            <td class="text-right py-2 px-2">₱ {{ number_format($arSummaryRecap['_60'] ?? 0, 2) }}</td>
                                                            <td class="text-right py-2 px-2">₱ {{ number_format($arSummaryRecap['_90'] ?? 0, 2) }}</td>
                                                            <td class="text-right py-2 px-2">₱ {{ number_format($arSummaryRecap['_over90'] ?? 0, 2) }}</td>
                                                            <td class="text-right py-2 px-2">₱ {{ number_format($arSummaryRecap['prev_year'] ?? 0, 2) }}</td>
                                                            <td class="text-right py-2 px-2"><strong>₱ {{ number_format($arSummaryRecap['total_balance'] ?? 0, 2) }}</strong></td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- AR Summary per Zone Tab -->
                        <div class="tab-pane fade" id="arSummaryZone">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered table-hover mb-0" style="font-size: 11px;">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th class="text-center py-2 px-2">Zone</th>
                                                            <th class="text-center py-2 px-2">Accounts</th>
                                                            <th class="text-right py-2 px-2">Current</th>
                                                            <th class="text-right py-2 px-2">_30</th>
                                                            <th class="text-right py-2 px-2">_60</th>
                                                            <th class="text-right py-2 px-2">_90</th>
                                                            <th class="text-right py-2 px-2">_over90</th>
                                                            <th class="text-right py-2 px-2">Prev Year</th>
                                                            <th class="text-right py-2 px-2">Total Balance</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse(($arSummaryZone ?? []) as $summary)
                                                            <tr>
                                                                <td class="text-center py-1">{{ $summary['zone'] }}</td>
                                                                <td class="text-center py-1">{{ $summary['accounts'] }}</td>
                                                                <td class="text-right py-1 px-2">₱ {{ number_format($summary['current'], 2) }}</td>
                                                                <td class="text-right py-1 px-2">₱ {{ number_format($summary['_30'], 2) }}</td>
                                                                <td class="text-right py-1 px-2">₱ {{ number_format($summary['_60'], 2) }}</td>
                                                                <td class="text-right py-1 px-2">₱ {{ number_format($summary['_90'], 2) }}</td>
                                                                <td class="text-right py-1 px-2">₱ {{ number_format($summary['_over90'], 2) }}</td>
                                                                <td class="text-right py-1 px-2">₱ {{ number_format($summary['prev_year'], 2) }}</td>
                                                                <td class="text-right py-1 px-2"><strong>₱ {{ number_format($summary['total_balance'], 2) }}</strong></td>
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="10" class="text-center py-4">
                                                                    <em class="text-muted">No data available.</em>
                                                                </td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- AR Summary by Category Tab -->
                        <div class="tab-pane fade" id="arSummaryCategory">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered table-hover mb-0" style="font-size: 11px;">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th class="py-2 px-2">Category</th>
                                                            <th class="text-center py-2 px-2">Accounts</th>
                                                            <th class="text-right py-2 px-2">Current</th>
                                                            <th class="text-right py-2 px-2">_30</th>
                                                            <th class="text-right py-2 px-2">_60</th>
                                                            <th class="text-right py-2 px-2">_90</th>
                                                            <th class="text-right py-2 px-2">_over90</th>
                                                            <th class="text-right py-2 px-2">Prev Year</th>
                                                            <th class="text-right py-2 px-2">Total Balance</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse(($arSummaryCategory ?? []) as $summary)
                                                            <tr>
                                                                <td class="py-1 px-2">{{ $summary['category'] }}</td>
                                                                <td class="text-center py-1">{{ $summary['accounts'] }}</td>
                                                                <td class="text-right py-1 px-2">₱ {{ number_format($summary['current'], 2) }}</td>
                                                                <td class="text-right py-1 px-2">₱ {{ number_format($summary['_30'], 2) }}</td>
                                                                <td class="text-right py-1 px-2">₱ {{ number_format($summary['_60'], 2) }}</td>
                                                                <td class="text-right py-1 px-2">₱ {{ number_format($summary['_90'], 2) }}</td>
                                                                <td class="text-right py-1 px-2">₱ {{ number_format($summary['_over90'], 2) }}</td>
                                                                <td class="text-right py-1 px-2">₱ {{ number_format($summary['prev_year'], 2) }}</td>
                                                                <td class="text-right py-1 px-2"><strong>₱ {{ number_format($summary['total_balance'], 2) }}</strong></td>
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="10" class="text-center py-4">
                                                                    <em class="text-muted">No data available.</em>
                                                                </td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Materials Balance Tab -->
                        <div class="tab-pane fade" id="materialsBalance">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                Materials Balance will be displayed here.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CDA-Aging Tab -->
                        <div class="tab-pane fade" id="cdaAging">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                CDA-Aging report will be displayed here.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CDA-AR Summary Recap Tab -->
                        <div class="tab-pane fade" id="cdaArRecap">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                CDA-AR Summary Recap will be displayed here.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <!---Container Fluid-->
            </div>
            <!-- Footer -->
            @include('partials.footer')
            <!-- Footer -->
        </div>
    </div>

    <!-- Scroll to top -->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <style>
        @media print {
            /* Do NOT use visibility:hidden on body — it keeps layout boxes (e.g. 100vh sidebar
               wrapper) and often causes a blank first printed page. Use display:none instead. */
            html, body, #page-top {
                height: auto !important;
                min-height: 0 !important;
            }

            /* Beat global header.css: body #wrapper { min-height: 100vh } flex layout —
               that reserves a full viewport on page 1 and often leaves it blank in print. */
            body #wrapper {
                display: block !important;
                min-height: 0 !important;
                height: auto !important;
                width: 100% !important;
            }

            body #wrapper #content-wrapper {
                display: block !important;
                flex: none !important;
                width: 100% !important;
                margin-left: 0 !important;
                min-height: 0 !important;
                height: auto !important;
            }

            body #wrapper #content {
                display: block !important;
                flex: none !important;
                min-height: 0 !important;
                height: auto !important;
            }

            body #wrapper .navbar-nav.sidebar,
            body #wrapper #accordionSidebar {
                display: none !important;
                position: absolute !important;
                left: -9999px !important;
                width: 0 !important;
                height: 0 !important;
                overflow: hidden !important;
                visibility: hidden !important;
            }

            /* Hide sidebar, navbar, and elements marked as no-print */
            #sidebar,
            #accordionSidebar,
            .sidebar,
            .navbar-nav.sidebar,
            .modern-sidebar,
            #wrapper > ul,
            footer,
            .sticky-footer,
            .scroll-to-top,
            .navbar,
            .topbar,
            .sidebar-brand,
            .sidebar-divider-modern,
            .sidebar-heading-modern,
            .no-print,
            .no-print *,
            .btn,
            .card-header,
            .nav-tabs,
            .card-footer,
            .no-print-col {
                display: none !important;
            }

            /* Show print header */
            .print-header {
                display: block !important;
                margin-bottom: 15px !important;
                padding: 10px 0 !important;
                page-break-after: avoid;
            }

            .print-header h1 {
                font-size: 19px !important;
                font-weight: bold !important;
                margin: 0 !important;
                letter-spacing: 0.5px !important;
                text-transform: uppercase !important;
            }

            .print-header h2 {
                font-size: 16px !important;
                font-weight: bold !important;
                margin: 8px 0 5px 0 !important;
                letter-spacing: 0.5px !important;
                text-transform: uppercase !important;
            }

            .print-header p {
                margin: 2px 0 !important;
                font-size: 12px !important;
            }

            /* Ensure table container is visible */
            .table-responsive {
                display: block !important;
                width: 100% !important;
                max-height: none !important;
                overflow: visible !important;
            }

            /* Professional table styling */
            #agingTable {
                width: 100% !important;
                border-collapse: separate !important;
                border-spacing: 0 !important;
                font-size: 11px !important;
                margin: 0 !important;
                display: table !important;
                font-family: Arial, sans-serif !important;
                border: none !important;
            }

            #agingTable thead {
                display: table-header-group !important;
                background-color: #f5f5f5 !important;
            }

            #agingTable thead tr {
                display: table-row !important;
                page-break-inside: avoid !important;
            }

            #agingTable thead th {
                background-color: #f8f9fa !important;
                border: none !important;
                border-bottom: 2px solid #333 !important;
                padding: 10px 6px !important;
                font-weight: bold !important;
                text-align: center !important;
                font-size: 11px !important;
                text-transform: uppercase !important;
                color: #333 !important;
                position: static !important;
                top: auto !important;
            }

            #agingTable tbody {
                display: table-row-group !important;
            }

            #agingTable tbody tr {
                display: table-row !important;
                page-break-inside: avoid !important;
                border: none !important;
            }

            #agingTable tbody td {
                border: none !important;
                padding: 8px 6px !important;
                font-size: 11px !important;
                color: #333 !important;
            }

            #agingTable tbody td.text-right {
                text-align: right !important;
            }

            #agingTable tbody td.text-center {
                text-align: center !important;
            }

            #agingTable tbody tr:nth-child(even) {
                background-color: #fafafa !important;
            }

            /* Remove card styling */
            .card {
                border: none !important;
                box-shadow: none !important;
                background: white !important;
            }

            .card-body {
                padding: 0 !important;
            }

            /* Page settings (single @page block — duplicate rules can confuse some print engines) */
            @page {
                margin: 1.5cm 1cm !important;
                size: A4 portrait !important;
            }

            /* Ensure content wrapper is visible */
            #content-wrapper,
            #container-wrapper {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                min-height: 0 !important;
                height: auto !important;
            }

            /* Hide empty message in print */
            tbody tr td[colspan] {
                display: none !important;
            }

            /* Hide tfoot in print - we'll use separate totals table instead */
            #agingTable tfoot {
                display: none !important;
            }

            /* Show the separate totals table only in print - appears only at the end */
            .print-totals-table {
                display: table !important;
                width: 100% !important;
                border-collapse: separate !important;
                border-spacing: 0 !important;
                font-size: 11px !important;
                margin-top: 15px !important;
                page-break-inside: avoid !important;
            }

            .print-totals-table tr {
                display: table-row !important;
            }
            
            .print-totals-table tr:first-child {
                background-color: transparent !important;
                font-weight: bold !important;
            }
            
            .print-totals-table tr:last-child {
                background-color: #f8f9fa !important;
                font-weight: bold !important;
            }

            .print-totals-table td {
                display: table-cell !important;
                border: none !important;
                padding: 9px 6px !important;
                font-size: 11px !important;
                font-weight: bold !important;
                color: #333 !important;
            }
            
            /* COUNT row styling */
            .print-totals-table tr:first-child td {
                border: none !important;
                border-bottom: 1px solid #ddd !important;
                background-color: transparent !important;
                padding-bottom: 6px !important;
            }
            
            /* TOTALS row styling - add top border */
            .print-totals-table tr:last-child td {
                border-top: 2px solid #333 !important;
            }
            
            /* Show signature section in print */
            .print-signatures {
                display: block !important;
                page-break-inside: avoid !important;
            }

        }
    </style>

    <script>
        function generateReport() {
            document.getElementById('filterForm').submit();
        }

        // Match column widths between main table and totals table for print
        window.addEventListener('beforeprint', function() {
            const mainTable = document.getElementById('agingTable');
            const totalsTable = document.querySelector('.print-totals-table');
            
            if (mainTable && totalsTable) {
                // Get visible headers (excluding no-print-col)
                const mainHeaders = Array.from(mainTable.querySelectorAll('thead th')).filter(th => !th.classList.contains('no-print-col'));
                const totalsRow = totalsTable.querySelector('tr:last-child');
                const totalsCells = totalsRow ? Array.from(totalsRow.querySelectorAll('td')) : [];
                
                // Match widths for visible print columns.
                mainHeaders.forEach((header, headerIndex) => {
                    if (totalsCells[headerIndex]) {
                        const width = header.offsetWidth;
                        totalsCells[headerIndex].style.width = width + 'px';
                        totalsCells[headerIndex].style.minWidth = width + 'px';
                    }
                });
            }
        });
    </script>
</body>
</html>

