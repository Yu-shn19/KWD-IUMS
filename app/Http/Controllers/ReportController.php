<?php

namespace App\Http\Controllers;

use App\Models\MeterReadingSchedule;
use App\Models\DownloadedReading;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    protected function resolveConsumption($item): float
    {
        $consumption = (float) ($item->consumption ?? 0);
        if ($consumption <= 0) {
            $current = (float) ($item->current_reading ?? 0);
            $previous = (float) ($item->previous_reading ?? 0);
            $derived = $current - $previous;
            if ($derived > 0) {
                $consumption = $derived;
            }
        }
        return max($consumption, 0);
    }

    protected function calculateWaterBill(float $consumption): float
    {
        $minimumCharge = 150.00; // For 0-10 cubic meters
        $rate = 15.00; // Per cubic meter beyond minimum

        if ($consumption <= 10) {
            return $minimumCharge;
        }

        return $minimumCharge + (($consumption - 10) * $rate);
    }

    protected function resolveCurrentBill($item, Carbon $asOfDate): float
    {
        $storedCurrentBill = (float) ($item->current_bill ?? 0);
        if ($storedCurrentBill > 0) {
            return $storedCurrentBill;
        }

        $consumption = $this->resolveConsumption($item);
        $baseBill = $this->calculateWaterBill($consumption);

        $dueDate = $item->due_date instanceof Carbon
            ? $item->due_date
            : ($item->due_date ? Carbon::parse($item->due_date) : null);

        $penaltyAmount = 19.50;
        $penalty = ($dueDate && $asOfDate->copy()->startOfDay()->greaterThanOrEqualTo($dueDate->copy()->startOfDay()))
            ? $penaltyAmount
            : 0.00;

        return round($baseBill + $penalty, 2);
    }

    /**
     * Billing Status report: one row per zone from meter_reading_schedules for a given bill month.
     * Columns: ZONE, Bill Date, Due Date, Discon Date, Meter Reading Preparation, Reading Download,
     * Reading Upload, Reading Posting, Bill Printing, Surcharge Generation, Status.
     */
    public function billingStatus(Request $request)
    {
        $billMonthInput = $request->input('bill_month', Carbon::now()->format('Y-m'));
        $billMonth = Carbon::createFromFormat('Y-m', $billMonthInput);
        $billMonthStart = $billMonth->copy()->startOfMonth()->format('Y-m-d');
        $billMonthEnd = $billMonth->copy()->endOfMonth()->format('Y-m-d');

        $rows = DB::table('meter_reading_schedules as mrs')
            ->leftJoin('downloaded_readings as dr', 'mrs.id', '=', 'dr.schedule_id')
            ->whereBetween('mrs.bill_month', [$billMonthStart, $billMonthEnd])
            ->whereNotNull('mrs.zone')
            ->select([
                'mrs.zone',
                DB::raw('MIN(mrs.bill_date) as bill_date'),
                DB::raw('MIN(mrs.due_date) as due_date'),
                DB::raw('MIN(mrs.disconnection_date) as disconnection_date'),
                DB::raw('COUNT(DISTINCT mrs.id) as preparation'),
                DB::raw('COUNT(DISTINCT CASE WHEN dr.id IS NOT NULL THEN mrs.id END) as reading_download'),
                DB::raw('COUNT(DISTINCT CASE WHEN dr.id IS NOT NULL THEN mrs.id END) as reading_upload'),
                DB::raw("COUNT(DISTINCT CASE WHEN mrs.status = 'Completed' THEN mrs.id END) as reading_posting"),
                DB::raw('COUNT(DISTINCT CASE WHEN dr.id IS NOT NULL THEN mrs.id END) as bill_printing_count'),
            ])
            ->groupBy('mrs.zone')
            ->orderBy('mrs.zone')
            ->get();

        $periodLabel = $billMonth->format('F-Y');

        $data = $rows->map(function ($row) {
            $preparation = (int) $row->preparation;
            $downloaded = (int) $row->reading_download;
            $posting = (int) $row->reading_posting;
            $billPrinted = (int) $row->bill_printing_count;
            $billPending = $preparation - $billPrinted;
            $billPrintingDisplay = $billPrinted;
            if ($billPending > 0) {
                $billPrintingDisplay = $billPrinted . ' (' . $billPending . ')';
            }
            return [
                'zone' => $row->zone,
                'bill_date' => $row->bill_date ? Carbon::parse($row->bill_date)->format('m/d/Y') : '',
                'due_date' => $row->due_date ? Carbon::parse($row->due_date)->format('m/d/Y') : '',
                'discon_date' => $row->disconnection_date ? Carbon::parse($row->disconnection_date)->format('m/d/Y') : '',
                'preparation' => $preparation,
                'reading_download' => $downloaded,
                'reading_upload' => (int) $row->reading_upload,
                'reading_posting' => $posting,
                'bill_printing' => $billPrintingDisplay,
                'surcharge_generation' => '',
                'status' => '',
            ];
        });

        return view('reports.billing-status', [
            'rows' => $data,
            'period' => $periodLabel,
        ]);
    }

    public function monthlyBillingReport(Request $request)
    {
        $zones = MeterReadingSchedule::query()
            ->select('zone')
            ->whereNotNull('zone')
            ->distinct()
            ->orderBy('zone')
            ->pluck('zone')
            ->toArray();

        $defaultZone = $zones[0] ?? null;
        $zone = $request->input('zone', $defaultZone);

        $billMonthInput = $request->input('bill_month', Carbon::now()->format('Y-m'));
        $billMonth = Carbon::createFromFormat('Y-m', $billMonthInput);
        $billMonthStart = $billMonth->copy()->startOfMonth();
        $billMonthEnd = $billMonth->copy()->endOfMonth();

        $asOf = Carbon::parse($request->input('as_of', Carbon::now()->toDateString()));

        // Query meter_reading_schedules joined with downloaded_readings and consumer_ledgers
        // Prioritize consumer_ledgers debit data for current bill
        $query = DB::table('meter_reading_schedules as mrs')
            ->leftJoin('downloaded_readings as dr', 'mrs.id', '=', 'dr.schedule_id')
            ->leftJoin('consumer_zone as cz', function($join) {
                $join->on(DB::raw('mrs.account_number COLLATE utf8mb4_unicode_ci'), '=', DB::raw('cz.account_no COLLATE utf8mb4_unicode_ci'));
            })
            ->leftJoin('consumer_ledgers as cl', function($join) {
                $join->on('mrs.id', '=', 'cl.schedule_id')
                     ->whereIn('cl.trans', ['BILL', 'BILLING']);
            })
            ->select(
                'mrs.id',
                'mrs.zone',
                'mrs.bill_month',
                'mrs.bill_date',
                'mrs.due_date',
                'mrs.category',
                'mrs.sedr_number',
                'mrs.account_number',
                'mrs.account_name',
                'mrs.address',
                'mrs.previous_reading_date',
                'mrs.previous_reading as mrs_previous_reading',
                'mrs.current_reading as mrs_current_reading',
                'mrs.consumption as mrs_consumption',
                'mrs.current_bill as mrs_current_bill',
                'mrs.arrears',
                'mrs.total_amount',
                'dr.id as downloaded_id',
                'dr.previous_reading as dr_previous_reading',
                'dr.current_reading as dr_current_reading',
                'dr.consumption as dr_consumption',
                'dr.current_bill as dr_current_bill',
                'dr.reading_date',
                'cz.id as consumer_zone_id',
                'cl.debit as ledger_debit',
                'cl.others as ledger_others'
            )
            ->whereBetween('mrs.bill_month', [$billMonthStart, $billMonthEnd])
            ->orderBy('mrs.zone')
            ->orderBy('mrs.sedr_number');

        if ($zone) {
            $query->where('mrs.zone', $zone);
        }

        $rawRecords = $query->get();

        $records = $rawRecords->map(function ($item) use ($asOf) {
            // Prioritize downloaded_readings data over meter_reading_schedules
            $previousReading = $item->dr_previous_reading ?? $item->mrs_previous_reading ?? 0;
            $currentReading = $item->dr_current_reading ?? $item->mrs_current_reading ?? 0;
            $consumption = $item->dr_consumption ?? $item->mrs_consumption ?? 0;
            
            // Calculate consumption if not available
            if ($consumption <= 0 && $currentReading > 0 && $previousReading >= 0) {
                $consumption = max(0, $currentReading - $previousReading);
            }
            
            // Prioritize consumer_ledgers debit data for current bill
            // Debit = current_bill + others (where others = 20.00)
            // So base current_bill = debit - others
            $baseCurrentBill = 0;
            if ($item->ledger_debit !== null) {
                // Use debit from consumer_ledgers - extract base current bill (debit - others)
                $others = (float)($item->ledger_others ?? 20.00);
                $debit = (float)$item->ledger_debit;
                $baseCurrentBill = max(0, $debit - $others); // Base current bill = debit - others
            } else {
                // Fallback: use downloaded_readings.current_bill, then meter_reading_schedules.current_bill
                $storedCurrentBill = $item->dr_current_bill ?? $item->mrs_current_bill ?? 0;
                $baseCurrentBill = $storedCurrentBill;
                
                // Calculate current bill if not stored
                if ($baseCurrentBill <= 0 && $consumption > 0) {
                    $baseCurrentBill = $this->calculateWaterBill($consumption);
                    
                    // Add penalty if due date has passed
                    $dueDate = $item->due_date ? Carbon::parse($item->due_date) : null;
                    if ($dueDate && $asOf->copy()->startOfDay()->greaterThanOrEqualTo($dueDate->copy()->startOfDay())) {
                        $penalty = round($baseCurrentBill * 0.10, 2); // 10% penalty
                        $baseCurrentBill += $penalty;
                    }
                }
            }
            
            // Add 20 pesos water maintenance charge only if there's a current bill
            $currentBill = $baseCurrentBill;
            if ($currentBill > 0) {
                $currentBill += 20.00; // Add water maintenance charge
            }
            
            $arrears = (float) ($item->arrears ?? 0);
            $totalAmountStored = (float) ($item->total_amount ?? 0);
            $computedTotal = round($currentBill + $arrears, 2);
            
            // Use reading_date from downloaded_readings if available, otherwise bill_date
            $readingDate = $item->reading_date ? Carbon::parse($item->reading_date) : ($item->bill_date ? Carbon::parse($item->bill_date) : null);

            // Create a unified object
            return (object) [
                'id' => $item->id,
                'zone' => $item->zone,
                'bill_month' => $item->bill_month ? Carbon::parse($item->bill_month) : null,
                'bill_date' => $item->bill_date ? Carbon::parse($item->bill_date) : null,
                'reading_date' => $readingDate,
                'category' => $item->category,
                'sedr_number' => $item->sedr_number,
                'account_number' => $item->account_number,
                'account_name' => $item->account_name,
                'address' => $item->address,
                'previous_reading_date' => $item->previous_reading_date ? Carbon::parse($item->previous_reading_date) : null,
                'previous_reading' => $previousReading,
                'current_reading' => $currentReading,
                'computed_consumption' => round($consumption, 0),
                'computed_current_bill' => round($currentBill, 2),
                'computed_arrears' => round($arrears, 2),
                'computed_total' => $totalAmountStored > 0 ? round($totalAmountStored, 2) : $computedTotal,
                'consumer_zone_id' => $item->consumer_zone_id,
            ];
        });

        $totals = [
            'accounts' => $records->count(),
            'consumption' => $records->sum(fn ($item) => $item->computed_consumption),
            'current_bill' => $records->sum(fn ($item) => $item->computed_current_bill),
            'arrears' => $records->sum(fn ($item) => $item->computed_arrears),
            'total_amount' => $records->sum(fn ($item) => $item->computed_total),
        ];

        $summaryByCategory = $records->groupBy(function ($item) {
            return $item->category ?? 'Unclassified';
        })->map(function ($items) {
            return [
                'accounts' => $items->count(),
                'consumption' => $items->sum(fn ($item) => $item->computed_consumption),
                'current_bill' => $items->sum(fn ($item) => $item->computed_current_bill),
                'arrears' => $items->sum(fn ($item) => $item->computed_arrears),
                'total_amount' => $items->sum(fn ($item) => $item->computed_total),
            ];
        })->sortKeys();

        return view('reports.system-report.monthly-billing-report', [
            'zones' => $zones,
            'selectedZone' => $zone,
            'billMonth' => $billMonth,
            'billMonthInput' => $billMonthInput,
            'asOf' => $asOf,
            'records' => $records,
            'totals' => $totals,
            'summaryByCategory' => $summaryByCategory,
        ]);
    }

    /**
     * Export monthly billing report to Excel
     */
    public function exportMonthlyBillingReport(Request $request)
    {
        $zones = MeterReadingSchedule::query()
            ->select('zone')
            ->whereNotNull('zone')
            ->distinct()
            ->orderBy('zone')
            ->pluck('zone')
            ->toArray();

        $defaultZone = $zones[0] ?? null;
        $zone = $request->input('zone', $defaultZone);

        $billMonthInput = $request->input('bill_month', Carbon::now()->format('Y-m'));
        $billMonth = Carbon::createFromFormat('Y-m', $billMonthInput);
        $billMonthStart = $billMonth->copy()->startOfMonth();
        $billMonthEnd = $billMonth->copy()->endOfMonth();

        $asOf = Carbon::parse($request->input('as_of', Carbon::now()->toDateString()));

        // Use the same query logic as monthlyBillingReport
        $query = DB::table('meter_reading_schedules as mrs')
            ->leftJoin('downloaded_readings as dr', 'mrs.id', '=', 'dr.schedule_id')
            ->leftJoin('consumer_zone as cz', function($join) {
                $join->on(DB::raw('mrs.account_number COLLATE utf8mb4_unicode_ci'), '=', DB::raw('cz.account_no COLLATE utf8mb4_unicode_ci'));
            })
            ->leftJoin('consumer_ledgers as cl', function($join) {
                $join->on('mrs.id', '=', 'cl.schedule_id')
                     ->whereIn('cl.trans', ['BILL', 'BILLING']);
            })
            ->select(
                'mrs.id',
                'mrs.zone',
                'mrs.bill_month',
                'mrs.bill_date',
                'mrs.due_date',
                'mrs.category',
                'mrs.sedr_number',
                'mrs.account_number',
                'mrs.account_name',
                'mrs.address',
                'mrs.previous_reading_date',
                'mrs.previous_reading as mrs_previous_reading',
                'mrs.current_reading as mrs_current_reading',
                'mrs.consumption as mrs_consumption',
                'mrs.current_bill as mrs_current_bill',
                'mrs.arrears',
                'mrs.total_amount',
                'dr.id as downloaded_id',
                'dr.previous_reading as dr_previous_reading',
                'dr.current_reading as dr_current_reading',
                'dr.consumption as dr_consumption',
                'dr.current_bill as dr_current_bill',
                'dr.reading_date',
                'cz.id as consumer_zone_id',
                'cl.debit as ledger_debit',
                'cl.others as ledger_others'
            )
            ->whereBetween('mrs.bill_month', [$billMonthStart, $billMonthEnd])
            ->orderBy('mrs.zone')
            ->orderBy('mrs.sedr_number');

        if ($zone) {
            $query->where('mrs.zone', $zone);
        }

        $rawRecords = $query->get();

        $records = $rawRecords->map(function ($item) use ($asOf) {
            $previousReading = $item->dr_previous_reading ?? $item->mrs_previous_reading ?? 0;
            $currentReading = $item->dr_current_reading ?? $item->mrs_current_reading ?? 0;
            $consumption = $item->dr_consumption ?? $item->mrs_consumption ?? 0;
            
            if ($consumption <= 0 && $currentReading > 0 && $previousReading >= 0) {
                $consumption = max(0, $currentReading - $previousReading);
            }
            
            // Prioritize consumer_ledgers debit data for current bill
            // Debit = current_bill + others (where others = 20.00)
            // So base current_bill = debit - others
            $baseCurrentBill = 0;
            if ($item->ledger_debit !== null) {
                // Use debit from consumer_ledgers - extract base current bill (debit - others)
                $others = (float)($item->ledger_others ?? 20.00);
                $debit = (float)$item->ledger_debit;
                $baseCurrentBill = max(0, $debit - $others); // Base current bill = debit - others
            } else {
                // Fallback: use downloaded_readings.current_bill, then meter_reading_schedules.current_bill
                $storedCurrentBill = $item->dr_current_bill ?? $item->mrs_current_bill ?? 0;
                $baseCurrentBill = $storedCurrentBill;
                
                // Calculate current bill if not stored
                if ($baseCurrentBill <= 0 && $consumption > 0) {
                    $baseCurrentBill = $this->calculateWaterBill($consumption);
                    
                    $dueDate = $item->due_date ? Carbon::parse($item->due_date) : null;
                    if ($dueDate && $asOf->copy()->startOfDay()->greaterThanOrEqualTo($dueDate->copy()->startOfDay())) {
                        $penalty = round($baseCurrentBill * 0.10, 2);
                        $baseCurrentBill += $penalty;
                    }
                }
            }
            
            // Add 20 pesos water maintenance charge only if there's a current bill
            $currentBill = $baseCurrentBill;
            if ($currentBill > 0) {
                $currentBill += 20.00; // Add water maintenance charge
            }
            
            $arrears = (float) ($item->arrears ?? 0);
            $totalAmountStored = (float) ($item->total_amount ?? 0);
            $computedTotal = round($currentBill + $arrears, 2);
            $readingDate = $item->reading_date ? Carbon::parse($item->reading_date) : ($item->bill_date ? Carbon::parse($item->bill_date) : null);

            return [
                'Zone' => $item->zone ?? '',
                'Bill Month' => $item->bill_month ? Carbon::parse($item->bill_month)->format('m-Y') : '',
                'Category' => $item->category ?? '',
                'SEDR #' => $item->sedr_number ?? '',
                'Account #' => $item->account_number ?? '',
                'Account Name' => $item->account_name ?? '',
                'Address' => $item->address ?? '',
                'Previous Reading Date' => $item->previous_reading_date ? Carbon::parse($item->previous_reading_date)->format('m/d/Y') : '',
                'Current Reading Date' => $readingDate ? $readingDate->format('m/d/Y') : '',
                'Previous Reading' => number_format($previousReading, 0),
                'Current Reading' => number_format($currentReading, 0),
                'Consumption' => number_format(round($consumption, 0), 0),
                'Current Bill' => number_format(round($currentBill, 2), 2),
                'Arrears' => number_format(round($arrears, 2), 2),
                'Total Amount' => number_format($totalAmountStored > 0 ? round($totalAmountStored, 2) : $computedTotal, 2),
            ];
        });

        // Generate filename
        $zoneText = $zone ? "Zone-{$zone}" : "All-Zones";
        $filename = "Monthly-Billing-Report-{$zoneText}-{$billMonth->format('Y-m')}-" . Carbon::now()->format('YmdHis') . ".xlsx";

        // Check if Laravel Excel is available
        if (class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
            return \Maatwebsite\Excel\Facades\Excel::download(
                new class($records) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithTitle {
                    protected $data;

                    public function __construct($data) {
                        $this->data = collect($data);
                    }

                    public function collection() {
                        return $this->data;
                    }

                    public function headings(): array {
                        return [
                            'Zone',
                            'Bill Month',
                            'Category',
                            'SEDR #',
                            'Account #',
                            'Account Name',
                            'Address',
                            'Previous Reading Date',
                            'Current Reading Date',
                            'Previous Reading',
                            'Current Reading',
                            'Consumption',
                            'Current Bill',
                            'Arrears',
                            'Total Amount',
                        ];
                    }

                    public function title(): string {
                        return 'Billing Report';
                    }
                },
                $filename
            );
        } else {
            // Fallback to CSV if Excel package is not available
            $filename = str_replace('.xlsx', '.csv', $filename);
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $callback = function() use ($records) {
                $file = fopen('php://output', 'w');
                
                // Write headers
                fputcsv($file, [
                    'Zone', 'Bill Month', 'Category', 'SEDR #', 'Account #', 'Account Name', 'Address',
                    'Previous Reading Date', 'Current Reading Date', 'Previous Reading', 'Current Reading',
                    'Consumption', 'Current Bill', 'Arrears', 'Total Amount'
                ]);
                
                // Write data
                foreach ($records as $record) {
                    fputcsv($file, array_values($record));
                }
                
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }
    }

    /**
     * Get consumers for disconnection (3+ months unpaid)
     */
    public function consumersForDisconnection(Request $request)
    {
        $filterType = $request->input('filter_type', 'disconnection_date');
        
        // If filter type is 3_consecutive, use DisconnectionController method
        if ($filterType === '3_consecutive') {
            $disconnectionController = new \App\Http\Controllers\DisconnectionController();
            return $disconnectionController->index($request);
        }
        
        // Get zones from consumer_zone table
        $zones = DB::table('consumer_zone')
            ->select('zone_code')
            ->whereNotNull('zone_code')
            ->distinct()
            ->orderBy('zone_code')
            ->pluck('zone_code')
            ->toArray();

        $zone = $request->input('zone');
        $billMonth = $request->input('bill_month'); // Format: MM-YYYY or YYYY-MM
        $status = $request->input('status');
        $asOf = Carbon::parse($request->input('as_of', Carbon::now()->toDateString()));
        $billingCutOff = Carbon::parse($request->input('billing_cutoff', Carbon::now()->toDateString()));
        $paymentCutOff = Carbon::parse($request->input('payment_cutoff', Carbon::now()->toDateString()));
        $disconDate = Carbon::parse($request->input('discon_date', Carbon::now()->toDateString()));

        // Parse bill month if provided
        $billMonthStart = null;
        $billMonthEnd = null;
        if ($billMonth) {
            if (strpos($billMonth, '-') !== false) {
                $parts = explode('-', $billMonth);
                if (strlen($parts[0]) == 2) {
                    // MM-YYYY format
                    $billMonthStart = Carbon::createFromFormat('m-Y', $billMonth)->startOfMonth();
                    $billMonthEnd = $billMonthStart->copy()->endOfMonth();
                } else {
                    // YYYY-MM format
                    $billMonthStart = Carbon::createFromFormat('Y-m', $billMonth)->startOfMonth();
                    $billMonthEnd = $billMonthStart->copy()->endOfMonth();
                }
            }
        }

        // Get all consumer accounts with their zone info for filtering
        $consumerQuery = DB::table('consumer_zone as cz')
            ->select(
                'cz.id as consumer_zone_id',
                'cz.account_no',
                'cz.account_name',
                'cz.zone_code',
                'cz.category_code',
                'cz.address1 as address',
                'cz.sequence',
                'cz.status_code'
            )
            ->whereNotNull('cz.account_no');

        // Apply filters
        if ($zone && $zone !== 'All Zones') {
            $consumerQuery->where('cz.zone_code', $zone);
        }

        if ($status && $status !== '') {
            $statusMap = [
                'A - Active' => ['A', 'ACTIVE', 'Active', 'active'],
                'I - Inactive' => ['I', 'INACTIVE', 'Inactive', 'inactive'],
                'S - Suspended' => ['S', 'SUSPENDED', 'Suspended', 'suspended'],
                'D - Disconnected' => ['D', 'DISCONNECTED', 'Disconnected', 'disconnected'],
            ];
            if (isset($statusMap[$status])) {
                $consumerQuery->whereIn('cz.status_code', $statusMap[$status]);
            } else {
                $consumerQuery->where('cz.status_code', $status);
            }
        }

        $consumers = $consumerQuery->get();
        
        // Get all account numbers for bulk query
        $accountNumbers = $consumers->pluck('account_no')->filter()->toArray();
        
        if (empty($accountNumbers)) {
            return view('reports.system-report.consumers-for-disconnection', [
                'zones' => $zones,
                'selectedZone' => $zone,
                'billMonth' => $billMonth,
                'status' => $status,
                'asOf' => $asOf,
                'billingCutOff' => $billingCutOff,
                'paymentCutOff' => $paymentCutOff,
                'disconDate' => $disconDate,
                'consumers' => [],
                'totalDue' => 0,
                'totalConsumers' => 0,
            ]);
        }

        // Get consumers with passed disconnection dates from meter_reading_schedules
        $today = $asOf ?? Carbon::today();
        
        // Query schedules that have passed disconnection date
        $schedulesQuery = DB::table('meter_reading_schedules as mrs')
            ->join('consumer_zone as cz', function($join) {
                $join->on(DB::raw('mrs.account_number COLLATE utf8mb4_unicode_ci'), '=', DB::raw('cz.account_no COLLATE utf8mb4_unicode_ci'));
            })
            ->where('mrs.disconnection_date', '<=', $today)
            ->whereNotNull('mrs.disconnection_date')
            ->select('cz.account_no')
            ->distinct();

        if ($zone && $zone !== 'All Zones') {
            $schedulesQuery->where('cz.zone_code', $zone);
        }

        $eligibleAccountNos = $schedulesQuery->pluck('account_no')->unique()->toArray();
        
        if (empty($eligibleAccountNos)) {
            return view('reports.system-report.consumers-for-disconnection', [
                'zones' => $zones,
                'selectedZone' => $zone,
                'billMonth' => $billMonth,
                'status' => $status,
                'asOf' => $asOf,
                'billingCutOff' => $billingCutOff,
                'paymentCutOff' => $paymentCutOff,
                'disconDate' => $disconDate,
                'consumers' => [],
                'totalDue' => 0,
                'totalConsumers' => 0,
            ]);
        }

        // Filter consumers to only those with passed disconnection dates
        $consumers = $consumers->filter(function($consumer) use ($eligibleAccountNos) {
            return in_array($consumer->account_no, $eligibleAccountNos);
        });

        // BULK QUERY: Get last payments for all accounts at once (join with consumer_zone to get account_no)
        // Use proper date ordering instead of MAX() to get the most recent payment
        // Get the most recent payment for each account by ordering by date DESC
        $lastPaymentsQuery = DB::table('consumer_ledgers as cl')
            ->join('consumer_zone as cz', 'cl.consumer_zone_id', '=', 'cz.id')
            ->whereIn('cz.account_no', $eligibleAccountNos)
            ->where('cl.trans', 'PAYMENT')
            ->whereNotNull('cl.date')
            ->where('cl.date', '!=', '')
            ->select('cz.account_no', 'cl.date', 'cl.id')
            ->orderBy('cz.account_no')
            ->orderByRaw('CAST(cl.date AS DATE) DESC')
            ->orderBy('cl.id', 'DESC');
        
        // Group by account and get the first (most recent) payment for each
        $lastPayments = [];
        $groupedPayments = $lastPaymentsQuery->get()->groupBy('account_no');
        
        foreach ($groupedPayments as $accountNo => $payments) {
            $mostRecent = $payments->first();
            if ($mostRecent && $mostRecent->date) {
                $lastPayments[$accountNo] = $mostRecent->date;
            }
        }

        // Get current balances using ConsumerLedgerController method
        $ledgerController = new \App\Http\Controllers\ConsumerLedgerController();
        
        // Get disconnection dates from schedules with full schedule info
        $disconnectionSchedules = DB::table('meter_reading_schedules')
            ->whereIn('account_number', $eligibleAccountNos)
            ->where('disconnection_date', '<=', $today)
            ->whereNotNull('disconnection_date')
            ->select('id as schedule_id', 'account_number', 'disconnection_date', 'due_date', 'bill_month', 'current_bill', 'arrears', 'total_amount')
            ->orderBy('account_number')
            ->orderBy('disconnection_date', 'desc')
            ->get()
            ->groupBy('account_number');
        
        // Get all payments for eligible accounts to check against schedules
        // This will be used to check if payments were made before disconnection dates
        $allPayments = DB::table('consumer_ledgers as cl')
            ->join('consumer_zone as cz', 'cl.consumer_zone_id', '=', 'cz.id')
            ->whereIn('cz.account_no', $eligibleAccountNos)
            ->where('cl.trans', 'PAYMENT')
            ->select('cz.account_no', 'cl.credit', 'cl.date', 'cl.schedule_id')
            ->orderBy('cz.account_no')
            ->orderBy('cl.date', 'desc')
            ->get()
            ->groupBy('account_no');

        // Get latest billing info for each account (join with consumer_zone to get account_no)
        $latestBillings = DB::table('consumer_ledgers as cl')
            ->join('consumer_zone as cz', 'cl.consumer_zone_id', '=', 'cz.id')
            ->whereIn('cz.account_no', $eligibleAccountNos)
            ->whereIn('cl.trans', ['BILL', 'BILLING'])
            ->select('cz.account_no', 'cl.debit', 'cl.billamount', 'cl.balance', 'cl.due_date', 'cl.date')
            ->orderBy('cz.account_no')
            ->orderBy('cl.date', 'desc')
            ->get()
            ->groupBy('account_no')
            ->map(function($group) {
                return $group->first(); // Get most recent billing
            });

        $consumersForDisconnection = [];

        foreach ($consumers as $consumer) {
            $accountNumber = $consumer->account_no;
            
            // Get schedules for this account
            $accountSchedules = $disconnectionSchedules->get($accountNumber, collect());

            if ($accountSchedules->isEmpty()) {
                continue;
            }

            // Get all payments for this account
            $accountPayments = $allPayments->get($accountNumber, collect());
            
            // Check if ALL schedules for this account have been paid before disconnection date
            $allSchedulesPaid = true;
            $unpaidSchedules = [];
            
            foreach ($accountSchedules as $schedule) {
                $scheduleId = $schedule->schedule_id;
                $disconDate = Carbon::parse($schedule->disconnection_date);
                $billAmount = (float)($schedule->total_amount ?? $schedule->current_bill ?? 0);
                $billMonth = Carbon::parse($schedule->bill_month);
                
                // Check if there's a payment made before disconnection date for this bill month
                $hasPayment = false;
                
                foreach ($accountPayments as $payment) {
                    $paymentDate = Carbon::parse($payment->date);
                    
                    // Payment must be before or on disconnection date
                    if ($paymentDate->greaterThan($disconDate)) {
                        continue;
                    }
                    
                    // Check if payment is for this schedule (by schedule_id) OR matches the bill month
                    $isForThisSchedule = ($payment->schedule_id == $scheduleId) ||
                                         ($paymentDate->year == $billMonth->year && 
                                          $paymentDate->month == $billMonth->month);
                    
                    if ($isForThisSchedule) {
                        // Check if payment amount covers the bill (allow 10% tolerance for rounding)
                        $paymentAmount = (float)($payment->credit ?? 0);
                        if ($paymentAmount >= ($billAmount * 0.9)) {
                            $hasPayment = true;
                    break;
                        }
                    }
                }
                
                if (!$hasPayment) {
                    $allSchedulesPaid = false;
                    $unpaidSchedules[] = $schedule;
                }
            }
            
            // Skip if all schedules have been paid before disconnection date
            if ($allSchedulesPaid) {
                continue;
            }
            
            // Get current balance using the same method as ledger view
            try {
                $request = new \Illuminate\Http\Request();
                $request->merge([
                    'account_no' => $accountNumber,
                    'year' => ''
                ]);
                $ledgerResponse = $ledgerController->getLedger($request);
                $ledgerData = json_decode($ledgerResponse->getContent(), true);
                $currentBalance = isset($ledgerData['summary']['balance']) ? (float)$ledgerData['summary']['balance'] : 0;
            } catch (\Exception $e) {
                // Fallback: get latest balance from consumer_ledger (join with consumer_zone)
                $latestLedger = DB::table('consumer_ledgers as cl')
                    ->join('consumer_zone as cz', 'cl.consumer_zone_id', '=', 'cz.id')
                    ->where('cz.account_no', $accountNumber)
                    ->select('cl.balance')
                    ->orderBy('cl.date', 'desc')
                    ->orderBy('cl.id', 'desc')
                    ->first();
                $currentBalance = $latestLedger ? (float)($latestLedger->balance ?? 0) : 0;
            }
            
            // Only include if they haven't paid (balance > 0)
            if ($currentBalance <= 0) {
                continue;
            }

            // Get disconnection date info from unpaid schedules
            $earliestDisconDate = collect($unpaidSchedules)->min(function($s) {
                return $s->disconnection_date;
            });
            $latestSchedule = collect($unpaidSchedules)->first();
            
            // Get latest billing info
            $latestBilling = $latestBillings->get($accountNumber);
            
            // Calculate totals
            $latestBillAmount = $latestBilling ? (float)($latestBilling->debit ?? $latestBilling->billamount ?? 0) : 0;
                $totalCurrentBill = $latestBillAmount;
            $totalArrears = max(0, $currentBalance - $latestBillAmount);
            $totalDue = $currentBalance;

            // Get last payment date
            $lastPaymentDate = 'Never';
            if (isset($lastPayments[$accountNumber]) && $lastPayments[$accountNumber]) {
                try {
                    $paymentDate = $lastPayments[$accountNumber];
                    // Handle different date formats
                    if (is_string($paymentDate)) {
                        $lastPaymentDate = Carbon::parse($paymentDate)->format('m/d/Y');
                    } else {
                        $lastPaymentDate = Carbon::parse($paymentDate)->format('m/d/Y');
                    }
                } catch (\Exception $e) {
                    // If parsing fails, try to get it directly from database
                    $recentPayment = DB::table('consumer_ledgers as cl')
                        ->join('consumer_zone as cz', 'cl.consumer_zone_id', '=', 'cz.id')
                        ->where('cz.account_no', $accountNumber)
                        ->where('cl.trans', 'PAYMENT')
                        ->whereNotNull('cl.date')
                        ->where('cl.date', '!=', '')
                        ->select('cl.date')
                        ->orderByRaw('CAST(cl.date AS DATE) DESC')
                        ->orderBy('cl.id', 'DESC')
                        ->first();
                    
                    if ($recentPayment && $recentPayment->date) {
                        try {
                            $lastPaymentDate = Carbon::parse($recentPayment->date)->format('m/d/Y');
                        } catch (\Exception $e2) {
                            $lastPaymentDate = 'Never';
                        }
                    }
                }
            }

            // Format disconnection date
            $disconDateFormatted = $earliestDisconDate 
                ? Carbon::parse($earliestDisconDate)->format('m/d/Y') 
                : $disconDate->format('m/d/Y');

            // Generate accurate remarks based on unpaid schedules and disconnection dates
            $remarks = '';
            $unpaidBillsCount = 0; // Initialize to avoid undefined variable
            
            // Count unpaid schedules (schedules with passed disconnection dates that weren't paid)
            $unpaidSchedulesCount = count($unpaidSchedules);
            
            if ($unpaidSchedulesCount > 0) {
                // Get the oldest unpaid schedule to show how long overdue
                $oldestUnpaidSchedule = collect($unpaidSchedules)->sortBy(function($schedule) {
                    return $schedule->disconnection_date;
                })->first();
                
                if ($oldestUnpaidSchedule && $oldestUnpaidSchedule->disconnection_date) {
                    $oldestDisconDate = Carbon::parse($oldestUnpaidSchedule->disconnection_date);
                    $daysOverdue = $today->diffInDays($oldestDisconDate);
                } else {
                    $daysOverdue = 0;
                }
                
                // Count actual unpaid billing months from ledger
                $unpaidBillsCount = DB::table('consumer_ledgers as cl')
                    ->join('consumer_zone as cz', 'cl.consumer_zone_id', '=', 'cz.id')
                    ->where('cz.account_no', $accountNumber)
                    ->whereIn('cl.trans', ['BILL', 'BILLING'])
                    ->where('cl.due_date', '<', $today)
                    ->where(function($q) {
                        $q->whereRaw('(cl.debit - COALESCE(cl.credit, 0)) > 0')
                          ->orWhere('cl.balance', '>', 0);
                    })
                    ->count();
                
                // Build detailed remarks
                if ($unpaidBillsCount > 0) {
                    $remarks = $unpaidBillsCount . ' month(s) unpaid';
                    
                    // Add disconnection date info if available
                    if ($daysOverdue > 0) {
                        if ($daysOverdue >= 30) {
                            $monthsOverdue = floor($daysOverdue / 30);
                            $remarks .= ', ' . $monthsOverdue . ' month(s) past disconnection date';
                        } else {
                            $remarks .= ', ' . $daysOverdue . ' day(s) past disconnection date';
                        }
                    }
                    
                    // Add schedule count if multiple
                    if ($unpaidSchedulesCount > 1) {
                        $remarks .= ' (' . $unpaidSchedulesCount . ' unpaid schedule(s))';
                    }
                } else {
                    // No unpaid bills but disconnection date passed
                    if ($daysOverdue > 0) {
                        if ($daysOverdue >= 30) {
                            $monthsOverdue = floor($daysOverdue / 30);
                            $remarks = $monthsOverdue . ' month(s) past disconnection date';
                        } else {
                            $remarks = $daysOverdue . ' day(s) past disconnection date';
                        }
                    } else {
                        $remarks = 'Disconnection date passed';
                    }
                }
            } else {
                // Fallback: count unpaid bills from ledger
                $unpaidBillsCount = DB::table('consumer_ledgers as cl')
                    ->join('consumer_zone as cz', 'cl.consumer_zone_id', '=', 'cz.id')
                    ->where('cz.account_no', $accountNumber)
                    ->whereIn('cl.trans', ['BILL', 'BILLING'])
                    ->where('cl.due_date', '<', $today)
                    ->where(function($q) {
                        $q->whereRaw('(cl.debit - COALESCE(cl.credit, 0)) > 0')
                          ->orWhere('cl.balance', '>', 0);
                    })
                    ->count();
                
                $remarks = $unpaidBillsCount > 0 
                    ? $unpaidBillsCount . ' month(s) unpaid' 
                    : 'Disconnection date passed';
            }

                $consumersForDisconnection[] = [
                    'zone' => $consumer->zone_code ?? '',
                    'sequence' => $consumer->sequence ?? 0,
                    'account_number' => $accountNumber,
                    'account_name' => $consumer->account_name ?? '',
                    'address' => $consumer->address ?? '',
                    'status' => $consumer->status_code ?? 'A',
                    'category' => $consumer->category_code ?? '',
                    'current_bill' => round($totalCurrentBill, 2),
                    'arrears' => round($totalArrears, 2),
                    'total_due' => round($totalDue, 2),
                    'last_payment' => $lastPaymentDate,
                'discon_date' => $disconDateFormatted,
                    'remarks' => $remarks,
                'unpaid_months' => $unpaidBillsCount ?? 0,
                ];
        }

        // Sort by zone, then sequence
        usort($consumersForDisconnection, function($a, $b) {
            if ($a['zone'] != $b['zone']) {
                return strcmp($a['zone'], $b['zone']);
            }
            return $a['sequence'] - $b['sequence'];
        });

        // Calculate totals
        $totalDue = array_sum(array_column($consumersForDisconnection, 'total_due'));
        $totalConsumers = count($consumersForDisconnection);

        return view('reports.system-report.consumers-for-disconnection', [
            'zones' => $zones,
            'selectedZone' => $zone,
            'billMonth' => $billMonth,
            'status' => $status,
            'asOf' => $asOf,
            'billingCutOff' => $billingCutOff,
            'paymentCutOff' => $paymentCutOff,
            'disconDate' => $disconDate,
            'consumers' => $consumersForDisconnection,
            'totalDue' => $totalDue,
            'totalConsumers' => $totalConsumers,
        ]);
    }

    /**
     * Collection Report - Get payment/collection data from downloaded_readings
     */
    public function collectionReport(Request $request)
    {
        // Get filter parameters
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->format('Y-m-d'));
        $zone = $request->input('zone', '');
        $collector = $request->input('collector', '');
        $paymentType = $request->input('payment_type', '');
        $status = $request->input('status', '');

        // Get available zones from consumer_zone
        $zones = DB::table('consumer_zone')
            ->select('zone_code')
            ->whereNotNull('zone_code')
            ->distinct()
            ->orderBy('zone_code')
            ->pluck('zone_code')
            ->toArray();

        // Get available collectors from consumer_payments (created_by field)
        $collectors = DB::table('consumer_payments')
            ->select('created_by')
            ->whereNotNull('created_by')
            ->where('created_by', '!=', '')
            ->distinct()
            ->orderBy('created_by')
            ->pluck('created_by')
            ->toArray();

        // Build query for payments from consumer_payments table
        $query = DB::table('consumer_payments as cp')
            ->leftJoin('downloaded_readings as dr', 'cp.reading_id', '=', 'dr.id')
            ->leftJoin('meter_reading_schedules as mrs', 'dr.schedule_id', '=', 'mrs.id')
            ->leftJoin('consumer_zone as cz', 'cp.consumer_id', '=', 'cz.id')
            ->where('cp.payment_amount', '>', 0)
            ->select(
                'cp.id',
                'cp.or_number',
                DB::raw('COALESCE(cp.paid_at, cp.created_at) as paid_date'),
                DB::raw('COALESCE(dr.account_number, mrs.account_number, cz.account_no) as account_number'),
                DB::raw('COALESCE(dr.account_name, mrs.account_name, cz.account_name) as account_name'),
                DB::raw('COALESCE(dr.zone, mrs.zone, cz.zone_code) as zone'),
                DB::raw('DATE_FORMAT(COALESCE(cp.paid_at, cp.created_at), "%m/%Y") as bill_month'),
                'cp.payment_amount',
                'cp.senior_citizen_discount',
                'cp.current_bill',
                'cp.penalty',
                'cp.meter_maintenance',
                'cp.arrears_cy',
                'cp.arrears_py',
                'cp.payment_method',
                'cp.created_by as collector',
                DB::raw('CASE 
                    WHEN cp.paid_at IS NOT NULL THEN "paid"
                    WHEN dr.status = "paid" THEN "paid"
                    ELSE "pending"
                END as status')
            );

        // Apply date filter
        if ($dateFrom) {
            $query->where(function($q) use ($dateFrom) {
                $q->whereDate('cp.paid_at', '>=', Carbon::parse($dateFrom)->format('Y-m-d'))
                  ->orWhere(function($q2) use ($dateFrom) {
                      $q2->whereNull('cp.paid_at')
                         ->whereDate('cp.created_at', '>=', Carbon::parse($dateFrom)->format('Y-m-d'));
                  });
            });
        }
        if ($dateTo) {
            $query->where(function($q) use ($dateTo) {
                $q->whereDate('cp.paid_at', '<=', Carbon::parse($dateTo)->format('Y-m-d'))
                  ->orWhere(function($q2) use ($dateTo) {
                      $q2->whereNull('cp.paid_at')
                         ->whereDate('cp.created_at', '<=', Carbon::parse($dateTo)->format('Y-m-d'));
                  });
            });
        }

        // Apply zone filter
        if ($zone && $zone !== '') {
            $query->where(function($q) use ($zone) {
                $q->where('dr.zone', $zone)
                  ->orWhere('mrs.zone', $zone)
                  ->orWhere('cz.zone_code', $zone);
            });
        }

        // Apply collector filter
        if ($collector && $collector !== '') {
            $query->where('cp.created_by', $collector);
        }

        // Apply payment type filter
        if ($paymentType && $paymentType !== '') {
            $query->where('cp.payment_method', $paymentType);
        }
        
        // Apply status filter
        if ($status && $status !== '') {
            if ($status === 'paid') {
                $query->where(function($q) {
                    $q->whereNotNull('cp.paid_at')
                      ->orWhere('dr.status', 'paid');
                });
            } elseif ($status === 'pending') {
                $query->whereNull('cp.paid_at')
                      ->where(function($q) {
                          $q->whereNull('dr.status')
                            ->orWhere('dr.status', '!=', 'paid');
                      });
            } elseif ($status === 'cancelled') {
                $query->where('dr.status', 'cancelled');
            }
        }
        // Get all records
        // Get all records – order by OR number lowest to highest (numeric then string)
        $records = $query->orderByRaw('CAST(cp.or_number AS UNSIGNED) ASC')
            ->orderBy('cp.or_number', 'asc')
            ->get();

        // Service Rev. (648): one query to lro_ledger for all ORs in this result set
        $orNumbers = $records->pluck('or_number')->unique()->filter()->values();
        $serviceRevByOr = [];
        foreach ($orNumbers as $or) {
            $key = trim((string) $or);
            if ($key !== '' && $key !== 'N/A') {
                $serviceRevByOr[$key] = 0;
            }
        }
        if ($orNumbers->isNotEmpty()) {
            $remarks = $orNumbers->map(fn ($or) => 'Payment OR#' . trim((string) $or))->filter(fn ($r) => $r !== 'Payment OR#' && $r !== 'Payment OR#N/A')->values()->toArray();
            if (!empty($remarks)) {
                $lroSums = DB::table('lro_ledger')
                    ->whereIn('remarks', $remarks)
                    ->select('remarks', DB::raw('SUM(amount) as total'))
                    ->groupBy('remarks')
                    ->get();
                foreach ($lroSums as $row) {
                    $key = trim(preg_replace('/^Payment OR#/', '', $row->remarks ?? ''));
                    if ($key !== '') {
                        $serviceRevByOr[$key] = (float) $row->total;
                    }
                }
            }
        }

        // Format records for display
        $detailRecords = $records->map(function ($record) {
            $paidDate = $record->paid_date ? Carbon::parse($record->paid_date) : null;
            
            return [
                'or_number' => $record->or_number ?? 'N/A',
                'date' => $paidDate ? $paidDate->format('m/d/Y') : 'N/A',
                'account_number' => $record->account_number ?? '',
                'account_name' => $record->account_name ?? '',
                'zone' => $record->zone ?? '',
                'bill_month' => $record->bill_month ?? 'N/A',
                'amount' => '₱ ' . number_format((float)($record->payment_amount ?? 0), 2),
                'payment_type' => ucfirst($record->payment_method ?? 'cash'),
                'collector' => $record->collector ?? 'N/A',
                'status' => ucfirst($record->status ?? 'pending'),
            ];
        });

        // Calculate totals
        $totalAmount = $records->sum(function ($record) {
            return (float)($record->payment_amount ?? 0);
        });
        $totalTransactions = $records->count();

        // Summary by Collector
        $summaryByCollector = $records->groupBy('collector')->map(function ($items, $collectorName) {
            return [
                'collector' => $collectorName ?? 'Unknown',
                'transactions' => $items->count(),
                'total_amount' => $items->sum(function ($item) {
                    return (float)($item->payment_amount ?? 0);
                }),
            ];
        })->sortBy('collector')->values();

        // Summary by Zone
        $summaryByZone = $records->groupBy('zone')->map(function ($items, $zoneCode) {
            return [
                'zone' => $zoneCode ?? 'Unknown',
                'transactions' => $items->count(),
                'total_amount' => $items->sum(function ($item) {
                    return (float)($item->payment_amount ?? 0);
                }),
            ];
        })->sortBy('zone')->values();

        // Summary by Payment Type
        $summaryByPaymentType = $records->groupBy('payment_method')->map(function ($items, $paymentMethod) {
            return [
                'payment_type' => ucfirst($paymentMethod ?? 'cash'),
                'transactions' => $items->count(),
                'total_amount' => $items->sum(function ($item) {
                    return (float)($item->payment_amount ?? 0);
                }),
            ];
        })->sortBy('payment_type')->values();

        // Daily Summary
        $dailySummary = $records->groupBy(function ($record) {
            return $record->paid_date ? Carbon::parse($record->paid_date)->format('Y-m-d') : 'Unknown';
        })->map(function ($items, $date) {
            return [
                'date' => $date !== 'Unknown' ? Carbon::parse($date)->format('m/d/Y') : 'Unknown',
                'transactions' => $items->count(),
                'total_amount' => $items->sum(function ($item) {
                    return (float)($item->payment_amount ?? 0);
                }),
            ];
        })->sortByDesc(function ($item, $key) {
            return $key;
        })->values();

        return view('reports.system-report.collection-report', [
            'zones' => $zones,
            'collectors' => $collectors,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'selectedZone' => $zone,
            'selectedCollector' => $collector,
            'selectedPaymentType' => $paymentType,
            'selectedStatus' => $status,
            'detailRecords' => $detailRecords,
            'serviceRevByOr' => $serviceRevByOr,
            'totalAmount' => $totalAmount,
            'totalTransactions' => $totalTransactions,
            'summaryByCollector' => $summaryByCollector,
            'summaryByZone' => $summaryByZone,
            'summaryByPaymentType' => $summaryByPaymentType,
            'dailySummary' => $dailySummary,
        ]);
    }

    /**
     * Export Collection Report to Excel using same filters and data as the on-screen report.
     */
    public function exportCollectionReport(Request $request)
    {
        // Reuse the same filters as collectionReport
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo   = $request->input('date_to', Carbon::now()->format('Y-m-d'));
        $zone     = $request->input('zone', '');
        $collector = $request->input('collector', '');
        $paymentType = $request->input('payment_type', '');
        $status   = $request->input('status', '');

        // Build the same base query as collectionReport
        $query = DB::table('consumer_payments as cp')
            ->leftJoin('downloaded_readings as dr', 'cp.reading_id', '=', 'dr.id')
            ->leftJoin('meter_reading_schedules as mrs', 'dr.schedule_id', '=', 'mrs.id')
            ->leftJoin('consumer_zone as cz', 'cp.consumer_id', '=', 'cz.id')
            ->where('cp.payment_amount', '>', 0)
            ->select(
                'cp.id',
                'cp.or_number',
                DB::raw('COALESCE(cp.paid_at, cp.created_at) as paid_date'),
                DB::raw('COALESCE(dr.account_number, mrs.account_number, cz.account_no) as account_number'),
                DB::raw('COALESCE(dr.account_name, mrs.account_name, cz.account_name) as account_name'),
                DB::raw('COALESCE(dr.zone, mrs.zone, cz.zone_code) as zone'),
                DB::raw('DATE_FORMAT(COALESCE(cp.paid_at, cp.created_at), "%m/%Y") as bill_month'),
                'cp.payment_amount',
                'cp.senior_citizen_discount',
                'cp.current_bill',
                'cp.penalty',
                'cp.meter_maintenance',
                'cp.arrears_cy',
                'cp.arrears_py',
                'cp.payment_method',
                'cp.created_by as collector',
                DB::raw('CASE 
                    WHEN cp.paid_at IS NOT NULL THEN "paid"
                    WHEN dr.status = "paid" THEN "paid"
                    ELSE "pending"
                END as status')
            );

        // Date filter (same logic as collectionReport)
        if ($dateFrom) {
            $query->where(function ($q) use ($dateFrom) {
                $q->whereDate('cp.paid_at', '>=', Carbon::parse($dateFrom)->format('Y-m-d'))
                  ->orWhere(function ($q2) use ($dateFrom) {
                      $q2->whereNull('cp.paid_at')
                         ->whereDate('cp.created_at', '>=', Carbon::parse($dateFrom)->format('Y-m-d'));
                  });
            });
        }
        if ($dateTo) {
            $query->where(function ($q) use ($dateTo) {
                $q->whereDate('cp.paid_at', '<=', Carbon::parse($dateTo)->format('Y-m-d'))
                  ->orWhere(function ($q2) use ($dateTo) {
                      $q2->whereNull('cp.paid_at')
                         ->whereDate('cp.created_at', '<=', Carbon::parse($dateTo)->format('Y-m-d'));
                  });
            });
        }

        // Zone filter
        if ($zone && $zone !== '') {
            $query->where(function ($q) use ($zone) {
                $q->where('dr.zone', $zone)
                  ->orWhere('mrs.zone', $zone)
                  ->orWhere('cz.zone_code', $zone);
            });
        }

        // Collector filter
        if ($collector && $collector !== '') {
            $query->where('cp.created_by', $collector);
        }

        // Payment type filter
        if ($paymentType && $paymentType !== '') {
            $query->where('cp.payment_method', $paymentType);
        }

        // Status filter
        if ($status && $status !== '') {
            if ($status === 'paid') {
                $query->where(function ($q) {
                    $q->whereNotNull('cp.paid_at')
                      ->orWhere('dr.status', 'paid');
                });
            } elseif ($status === 'pending') {
                $query->whereNull('cp.paid_at')
                      ->where(function ($q) {
                          $q->whereNull('dr.status')
                            ->orWhere('dr.status', '!=', 'paid');
                      });
            } elseif ($status === 'cancelled') {
                $query->where('dr.status', 'cancelled');
            }
        }

        $records = $query
            ->orderByRaw('CAST(cp.or_number AS UNSIGNED) ASC')
            ->orderBy('cp.or_number', 'asc')
            ->get();

        if ($records->isEmpty()) {
            return redirect()
                ->back()
                ->with('error', 'No collection records found for the selected criteria to export.');
        }

        // Map to flat rows matching the Detail tab columns
        $rows = $records->map(function ($record, $index) {
            $paidDate = $record->paid_date ? Carbon::parse($record->paid_date) : null;
            return [
                '#'             => $index + 1,
                'OR Number'     => $record->or_number ?? 'N/A',
                'Date'          => $paidDate ? $paidDate->format('m/d/Y') : 'N/A',
                'Account No'    => $record->account_number ?? '',
                'Account Name'  => $record->account_name ?? '',
                'Zone'          => $record->zone ?? '',
                'Bill Month'    => $record->bill_month ?? 'N/A',
                'Amount'        => round((float)($record->payment_amount ?? 0), 2),
                'Payment Type'  => ucfirst($record->payment_method ?? 'cash'),
                'Collector'     => $record->collector ?? 'N/A',
                'Status'        => ucfirst($record->status ?? 'pending'),
            ];
        });

        // Filename with date range
        $fromLabel = Carbon::parse($dateFrom)->format('Ymd');
        $toLabel   = Carbon::parse($dateTo)->format('Ymd');
        $zoneText  = $zone ? "Zone-{$zone}" : "All-Zones";
        $filename  = "Collection-Report-{$zoneText}-{$fromLabel}-to-{$toLabel}-" . Carbon::now()->format('YmdHis') . ".xlsx";

        if (class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
            return \Maatwebsite\Excel\Facades\Excel::download(
                new class($rows) implements
                    \Maatwebsite\Excel\Concerns\FromCollection,
                    \Maatwebsite\Excel\Concerns\WithHeadings,
                    \Maatwebsite\Excel\Concerns\WithTitle {
                    protected $data;

                    public function __construct($data)
                    {
                        $this->data = collect($data);
                    }

                    public function collection()
                    {
                        return $this->data;
                    }

                    public function headings(): array
                    {
                        $first = $this->data->first();
                        return $first ? array_keys($first) : [];
                    }

                    public function title(): string
                    {
                        return 'Collection Report';
                    }
                },
                $filename
            );
        }

        return redirect()
            ->back()
            ->with('error', 'Excel export is not available. Laravel Excel package is missing.');
    }

    /**
     * AR Aging Summary - Calculate accounts receivable aging
     */
    public function arAgingSummary(Request $request)
    {
        // Log that method was called
        try {
            Log::info('AR Aging Summary method called', ['time' => now()]);
        } catch (\Throwable $e) {}
        
        $safeDefaults = function () {
            $now = Carbon::now();
            return [
                'zones' => [],
                'categories' => [],
                'status' => '',
                'selectedZone' => '',
                'selectedCategory' => '',
                'asOf' => $now,
                'billingCutOff' => $now,
                'paymentCutOff' => $now,
                'detailRecords' => [],
                'totals' => [
                    'current' => 0, 'days_1_30' => 0, 'days_31_60' => 0, 'days_61_90' => 0,
                    'days_91_120' => 0, 'over_120' => 0, 'prev_house' => 0, 'total_balance' => 0,
                ],
                'arSummaryRecap' => [
                    'total_accounts' => 0, 'current' => 0, 'days_1_30' => 0, 'days_31_60' => 0,
                    'days_61_90' => 0, 'days_91_120' => 0, 'over_120' => 0, 'prev_house' => 0, 'total_balance' => 0,
                ],
                'arSummaryZone' => collect(),
                'arSummaryCategory' => collect(),
                'reportError' => 'Report could not be loaded. Please check storage/logs/laravel.log for details.',
            ];
        };

        try {
            // Prevent memory exhaustion on large datasets (700k+ rows)
            @ini_set('memory_limit', '1024M');
            @ini_set('max_execution_time', '300'); // 5 minutes

            $status = $request->input('status', '');
            $zone = $request->input('zone', '');
            $category = $request->input('category', '');
            try {
                $asOf = Carbon::parse($request->input('as_of', Carbon::now()->format('Y-m-d')));
            } catch (\Exception $e) {
                $asOf = Carbon::now();
            }
            try {
                $billingCutOff = Carbon::parse($request->input('billing_cutoff', Carbon::now()->format('Y-m-d')));
            } catch (\Exception $e) {
                $billingCutOff = Carbon::now();
            }
            try {
                $paymentCutOff = Carbon::parse($request->input('payment_cutoff', Carbon::now()->format('Y-m-d')));
            } catch (\Exception $e) {
                $paymentCutOff = Carbon::now();
            }

            $zones = [];
            $categories = [];
            $detailRecords = [];
            $totals = [
                'current' => 0,
                'days_1_30' => 0,
                'days_31_60' => 0,
                'days_61_90' => 0,
                'days_91_120' => 0,
                'over_120' => 0,
                'prev_house' => 0,
                'total_balance' => 0,
            ];
            $arSummaryRecap = [
                'total_accounts' => 0,
                'current' => 0,
                'days_1_30' => 0,
                'days_31_60' => 0,
                'days_61_90' => 0,
                'days_91_120' => 0,
                'over_120' => 0,
                'prev_house' => 0,
                'total_balance' => 0,
            ];
            $arSummaryZone = collect();
            $arSummaryCategory = collect();
            $reportError = null;

            try {
        // Get available zones
        $zones = MeterReadingSchedule::query()
            ->select('zone')
            ->whereNotNull('zone')
            ->distinct()
            ->orderBy('zone')
            ->pluck('zone')
            ->toArray();

        // Get available categories
        $categories = MeterReadingSchedule::query()
            ->select('category')
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->toArray();

        // OPTIMIZATION: Only get unpaid bills from last 3 years to avoid loading 700k+ rows
        $threeYearsAgo = Carbon::now()->subYears(3)->startOfYear();
        
        // Get unpaid bills from consumer_ledgers table (optimized with date filter)
        $query = DB::table('consumer_ledgers as cl')
            ->join('consumer_zone as cz', 'cl.consumer_zone_id', '=', 'cz.id')
            ->leftJoin('meter_reading_schedules as mrs', 'cl.schedule_id', '=', 'mrs.id')
            ->leftJoin('downloaded_readings as dr', 'cl.downloaded_reading_id', '=', 'dr.id')
            ->select(
                'cz.zone_code as zone',
                'cz.sequence',
                'cz.account_no as account_number',
                'cz.account_name',
                'cz.status_code',
                'cz.category_code as consumer_category',
                'mrs.category as schedule_category',
                'mrs.zone as schedule_zone',
                'mrs.id as schedule_id',
                'mrs.due_date as mrs_due_date',
                'cl.due_date as cl_due_date',
                'cl.debit',
                'cl.others',
                'cl.credit',
                'cl.balance',
                'cl.date as transaction_date',
                'cl.reference',
                'mrs.bill_month',
                'cl.consumer_zone_id'
            )
            ->where('cl.balance', '>', 0)
            ->where(function($q) {
                $q->where('cl.trans', 'BILL')
                  ->orWhere('cl.trans', 'like', '%BILL%');
            })
            ->where('cl.date', '>=', $threeYearsAgo->format('Y-m-d')); // PERFORMANCE: Only last 3 years

        // Apply status filter
        if ($status && $status !== '' && $status !== 'All Status') {
            $statusMap = [
                'A - ACTIVE' => ['A', 'ACTIVE', 'Active', 'active'],
                'I - INACTIVE' => ['I', 'INACTIVE', 'Inactive', 'inactive'],
                'S - SUSPENDED' => ['S', 'SUSPENDED', 'Suspended', 'suspended'],
                'D - DISCONNECTED' => ['D', 'DISCONNECTED', 'Disconnected', 'disconnected'],
            ];
            if (isset($statusMap[$status])) {
                $query->whereIn('cz.status_code', $statusMap[$status]);
            } else {
                $query->where('cz.status_code', $status);
            }
        }

        // Apply zone filter
        if ($zone && $zone !== '' && $zone !== 'All Zones') {
            $query->where('cz.zone_code', $zone);
        }

        // Apply category filter (check both mrs.category and cz.category_code)
        if ($category && $category !== '' && $category !== 'All Categories') {
            $query->where(function($q) use ($category) {
                $q->where('mrs.category', $category)
                  ->orWhere('cz.category_code', $category);
            });
        }

        // Apply billing cut-off filter
        if ($billingCutOff) {
            $query->where('cl.date', '<=', $billingCutOff->format('Y-m-d'));
        }

        // OPTIMIZATION: Use limit to prevent memory exhaustion
        // For 700k+ row table, limit to 50k most recent unpaid bills
        $records = $query->orderBy('cl.date', 'desc') // Most recent first
            ->orderBy('cz.zone_code')
            ->orderBy('cz.sequence')
            ->limit(50000) // Max 50k records at once
            ->get();
        
        // Log query performance for monitoring
        try {
            Log::info('AR Aging Summary query executed', [
                'record_count' => $records->count(),
                'filters' => ['zone' => $zone, 'category' => $category, 'status' => $status]
            ]);
        } catch (\Throwable $e) {}

        // Pre-compute previous year balances for all consumer_zone_ids in one query
        $currentYear = (int) $asOf->format('Y');
        $previousYear = $currentYear - 1;
        $prevYearEnd = Carbon::create($previousYear, 12, 31, 23, 59, 59);

        $consumerZoneIds = $records->pluck('consumer_zone_id')->filter()->unique()->values();

        $prevYearBalances = collect();
        if ($consumerZoneIds->isNotEmpty()) {
            // OPTIMIZATION: Use subquery to get only the latest balance per consumer_zone_id
            // This is much faster than getting all records and grouping
            $prevYearBalances = DB::table('consumer_ledgers as cl1')
                ->select('cl1.consumer_zone_id', 'cl1.balance')
                ->whereIn('cl1.consumer_zone_id', $consumerZoneIds)
                ->where('cl1.date', '<=', $prevYearEnd->format('Y-m-d'))
                ->whereNotNull('cl1.balance')
                ->whereRaw('cl1.id = (
                    SELECT cl2.id FROM consumer_ledgers cl2 
                    WHERE cl2.consumer_zone_id = cl1.consumer_zone_id 
                    AND cl2.date <= ? 
                    ORDER BY cl2.date DESC, cl2.id DESC 
                    LIMIT 1
                )', [$prevYearEnd->format('Y-m-d')])
                ->get()
                ->pluck('balance', 'consumer_zone_id')
                ->map(fn($balance) => (float) $balance);
        }

        // SIMPLIFIED: Using consumer_ledgers only for all data
        // Skip downloaded_readings query since all data is in consumer_ledgers
        /* DISABLED - NOT USING DOWNLOADED_READINGS
        $downloadedReadingsQuery = DB::table('downloaded_readings as dr')
            ->leftJoin('meter_reading_schedules as mrs', 'dr.schedule_id', '=', 'mrs.id')
            ->leftJoin('consumer_ledgers as cl', function($join) use ($dateCutoff) {
                $join->on('cl.downloaded_reading_id', '=', 'dr.id')
                     ->where('cl.trans', 'like', '%BILL%')
                     ->where('cl.date', '>=', $dateCutoff->format('Y-m-d')); // Only check ledgers from Dec 2025 onwards
            })
            ->leftJoin('consumer_payments as cp', 'cp.reading_id', '=', 'dr.id')
            ->select(
                'dr.account_number',
                'dr.zone as dr_zone',
                'dr.account_name as dr_account_name',
                'mrs.zone as mrs_zone',
                'mrs.category as schedule_category',
                'mrs.due_date',
                'mrs.bill_month',
                'mrs.id as schedule_id',
                'dr.current_bill as downloaded_current_bill',
                'mrs.current_bill as schedule_current_bill',
                DB::raw('COALESCE(cl.balance, dr.current_bill, mrs.current_bill, 0) as balance'),
                'mrs.bill_date as transaction_date',
                'dr.reading_date'
            )
            ->where(function($q) {
                $q->where('dr.status', '!=', 'paid')
                  ->orWhereNull('dr.status');
            })
            ->where(function($q) {
                $q->where('dr.current_bill', '>', 0)
                  ->orWhere('mrs.current_bill', '>', 0);
            })
            ->where(function($q) use ($dateCutoff) {
                // Get records from December 2025 onwards
                $q->where('mrs.bill_date', '>=', $dateCutoff->format('Y-m-d'))
                  ->orWhere('dr.reading_date', '>=', $dateCutoff->format('Y-m-d'))
                  ->orWhere('mrs.bill_month', '>=', $dateCutoff->format('Y-m'));
            });

        // Apply filters to downloaded_readings query
        if ($zone && $zone !== '' && $zone !== 'All Zones') {
            $downloadedReadingsQuery->where(function($q) use ($zone) {
                $q->where('dr.zone', $zone)
                  ->orWhere('mrs.zone', $zone);
            });
        }

        if ($category && $category !== '' && $category !== 'All Categories') {
            $downloadedReadingsQuery->where('mrs.category', $category);
        }

        $downloadedRecords = $downloadedReadingsQuery->get();

        // Get all unique account numbers from downloaded records
        $accountNumbers = $downloadedRecords->pluck('account_number')->filter()->unique()->toArray();
        
        // Fetch consumer_zone data separately to avoid collation issues
        $consumerZoneData = [];
        if (!empty($accountNumbers)) {
            $normalizedAccounts = array_map(function($acc) {
                return str_replace('-', '', $acc);
            }, $accountNumbers);
            
            $consumerZones = DB::table('consumer_zone')
                ->where(function($query) use ($accountNumbers, $normalizedAccounts) {
                    $query->whereIn('account_no', $accountNumbers);
                    foreach ($normalizedAccounts as $normalized) {
                        $query->orWhereRaw('REPLACE(account_no, "-", "") = ?', [$normalized]);
                    }
                })
                ->get();
            
            // Create lookup maps for both original and normalized account numbers
            foreach ($consumerZones as $cz) {
                $original = $cz->account_no;
                $normalized = str_replace('-', '', $original);
                $consumerZoneData[$original] = $cz;
                $consumerZoneData[$normalized] = $cz;
            }
        }

        // Merge consumer_zone data into downloaded records (December 2025 onwards data)
        $downloadedRecords = $downloadedRecords->map(function($record) use ($consumerZoneData, $status, $category, $zone) {
            $accountNo = $record->account_number ?? '';
            $normalized = str_replace('-', '', $accountNo);
            
            $cz = $consumerZoneData[$accountNo] ?? $consumerZoneData[$normalized] ?? null;
            
            // Apply status filter after merging consumer_zone data
            if ($cz && $status && $status !== '' && $status !== 'All Status') {
                $statusMap = [
                    'A - ACTIVE' => ['A', 'ACTIVE', 'Active', 'active'],
                    'I - INACTIVE' => ['I', 'INACTIVE', 'Inactive', 'inactive'],
                    'S - SUSPENDED' => ['S', 'SUSPENDED', 'Suspended', 'suspended'],
                    'D - DISCONNECTED' => ['D', 'DISCONNECTED', 'Disconnected', 'disconnected'],
                ];
                $czStatus = $cz->status_code ?? '';
                $shouldInclude = false;
                
                if (isset($statusMap[$status])) {
                    $shouldInclude = in_array($czStatus, $statusMap[$status]);
                } else {
                    $shouldInclude = ($czStatus === $status);
                }
                
                if (!$shouldInclude) {
                    return null; // Filter out this record
                }
            }
            
            // Apply category filter
            if ($cz && $category && $category !== '' && $category !== 'All Categories') {
                $czCategory = $cz->category_code ?? '';
                if ($czCategory !== $category && ($record->schedule_category ?? '') !== $category) {
                    return null; // Filter out this record
                }
            }
            
            // Apply zone filter (additional check)
            if ($cz && $zone && $zone !== '' && $zone !== 'All Zones') {
                $czZone = $cz->zone_code ?? '';
                if ($czZone !== $zone && ($record->dr_zone ?? '') !== $zone && ($record->mrs_zone ?? '') !== $zone) {
                    return null; // Filter out this record
                }
            }
            
            return (object) [
                'zone' => $cz->zone_code ?? $record->dr_zone ?? $record->mrs_zone ?? '',
                'sequence' => $cz->sequence ?? 0,
                'account_number' => $accountNo,
                'account_name' => $cz->account_name ?? $record->dr_account_name ?? '',
                'status_code' => $cz->status_code ?? '',
                'consumer_category' => $cz->category_code ?? '',
                'schedule_category' => $record->schedule_category ?? '',
                'due_date' => $record->due_date ?? null, // This is from meter_reading_schedules.due_date for current bill
                'bill_month' => $record->bill_month ?? null,
                'downloaded_current_bill' => $record->downloaded_current_bill ?? 0,
                'schedule_current_bill' => $record->schedule_current_bill ?? 0,
                'balance' => $record->balance ?? 0,
                'transaction_date' => $record->transaction_date ?? null,
                'schedule_id' => $record->schedule_id ?? null, // Include schedule_id for matching
                'consumer_zone_id' => $cz->id ?? null, // For prevYearBalances lookup; ledger records already have this
            ];
        })->filter(); // Remove null records (filtered out)
        END OF DISABLED CODE */

        // Use ALL records from consumer_ledgers only
        $allRecords = $records;

        // Get latest downloaded_readings per account (MySQL 5.7/MariaDB compatible: no window functions)
        $latestDownloadedSub = DB::table('downloaded_readings as dr')
            ->leftJoin('meter_reading_schedules as mrs', 'dr.schedule_id', '=', 'mrs.id')
            ->where(function($q) {
                $q->where(function($q2) {
                    $q2->where('dr.status', '!=', 'paid')
                       ->orWhereNull('dr.status')
                       ->orWhereNull('dr.paid_at');
                })
                ->where(function($q3) {
                    $q3->where('dr.current_bill', '>', 0)
                       ->orWhere('mrs.current_bill', '>', 0);
                });
            })
            ->select('dr.account_number', DB::raw('MAX(dr.id) as latest_dr_id'))
            ->groupBy('dr.account_number');

        $latestDownloadedReadings = DB::table('downloaded_readings as dr')
            ->leftJoin('meter_reading_schedules as mrs', 'dr.schedule_id', '=', 'mrs.id')
            ->joinSub($latestDownloadedSub, 'latest_dr', function($join) {
                $join->on('dr.id', '=', 'latest_dr.latest_dr_id');
            })
            ->select(
                'dr.account_number',
                'dr.current_bill as downloaded_current_bill',
                'mrs.current_bill as schedule_current_bill',
                'mrs.due_date',
                'mrs.bill_month',
                'mrs.id as schedule_id'
            )
            ->get()
            ->keyBy('account_number');

        // Also map normalized account numbers (without dashes) for lookup
        $latestDownloadedReadingsNormalized = $latestDownloadedReadings->mapWithKeys(function ($item) {
            $normalized = str_replace('-', '', $item->account_number ?? '');
            return $normalized ? [$normalized => $item] : [];
        });

        // Group by account and calculate aging buckets
        $accountGroups = $allRecords->groupBy('account_number');
        $detailRecords = [];
        $totals = [
            'current' => 0,
            'days_1_30' => 0,
            'days_31_60' => 0,
            'days_61_90' => 0,
            'days_91_120' => 0,
            'over_120' => 0,
            'prev_house' => 0,
            'total_balance' => 0,
        ];

        foreach ($accountGroups as $accountNumber => $accountBills) {
            $firstBill = $accountBills->first();
            $current = 0;
            $days_1_30 = 0;
            $days_31_60 = 0;
            $days_61_90 = 0;
            $days_91_120 = 0;
            $over_120 = 0;
            $prev_house = 0;
            $total_balance = 0; // Will be calculated as sum of all individually aged bills

            // Get the latest downloaded_reading for this account from the pre-fetched collection
            $latestDownloadedReading = $latestDownloadedReadings->get($accountNumber);
            if (!$latestDownloadedReading) {
                $normalizedAcc = str_replace('-', '', $accountNumber);
                $latestDownloadedReading = $latestDownloadedReadingsNormalized->get($normalizedAcc);
            }

            // Get current bill amount from latest downloaded_reading
            $latestCurrentBill = 0;
            $latestDueDate = null;
            $latestBillMonth = null;
            $latestScheduleId = null;
            if ($latestDownloadedReading) {
                $latestCurrentBill = (float)($latestDownloadedReading->downloaded_current_bill ?? $latestDownloadedReading->schedule_current_bill ?? 0);
                $latestDueDate = $latestDownloadedReading->due_date ? Carbon::parse($latestDownloadedReading->due_date) : null;
                $latestBillMonth = $latestDownloadedReading->bill_month ? Carbon::parse($latestDownloadedReading->bill_month) : null;
                $latestScheduleId = $latestDownloadedReading->schedule_id;
            }
            
            // Also try to find by matching account number variations
            if (!$latestDownloadedReading && $accountNumber) {
                $normalizedAccount = str_replace('-', '', $accountNumber);
                $latestDownloadedReading = $latestDownloadedReadings->first(function($item) use ($accountNumber, $normalizedAccount) {
                    $itemAccount = $item->account_number ?? '';
                    $itemNormalized = str_replace('-', '', $itemAccount);
                    return $itemAccount === $accountNumber || $itemNormalized === $normalizedAccount;
                });
                
                if ($latestDownloadedReading) {
                    $latestCurrentBill = (float)($latestDownloadedReading->downloaded_current_bill ?? $latestDownloadedReading->schedule_current_bill ?? 0);
                    $latestDueDate = $latestDownloadedReading->due_date ? Carbon::parse($latestDownloadedReading->due_date) : null;
                    $latestBillMonth = $latestDownloadedReading->bill_month ? Carbon::parse($latestDownloadedReading->bill_month) : null;
                    $latestScheduleId = $latestDownloadedReading->schedule_id;
                }
            }

            // Separate past bills (from consumer_ledgers) from current bill (from downloaded_readings)
            // Past bills: Age based on consumer_ledgers due_date or transaction_date
            // Current bill: Age based on meter_reading_schedules.due_date from downloaded_readings
            // Separate bills by year: Previous year bills go to Prev_Year, current year bills go to aging buckets
            
            $currentBillProcessed = false;
            
            foreach ($accountBills as $bill) {
                // Check if this is from consumer_ledgers (past bills) or downloaded_readings (current bill)
                $isFromLedger = isset($bill->debit);
                $isFromDownloaded = !$isFromLedger && (isset($bill->downloaded_current_bill) || isset($bill->schedule_current_bill));
                
                // Determine the year of this bill
                $billYear = null;
                $billMonth = $bill->bill_month ? Carbon::parse($bill->bill_month) : null;
                if ($billMonth) {
                    $billYear = (int)$billMonth->format('Y');
                } elseif (!empty($bill->transaction_date)) {
                    try {
                        $billYear = (int)Carbon::parse($bill->transaction_date)->format('Y');
                    } catch (\Exception $e) {
                        // Use current year as fallback
                        $billYear = $currentYear;
                    }
                } else {
                    // Use current year as fallback
                    $billYear = $currentYear;
                }
                
                // Check if this is from previous year
                $isPreviousYear = ($billYear < $currentYear);
                
                // Check if this matches the latest bill
                $isLatestBill = false;
                
                if ($latestBillMonth && $billMonth) {
                    $latestMonthStr = $latestBillMonth->format('Y-m');
                    $billMonthStr = $billMonth->format('Y-m');
                    $isLatestBill = ($latestMonthStr === $billMonthStr);
                }
                
                if (!$isLatestBill && $latestScheduleId && isset($bill->schedule_id)) {
                    $isLatestBill = ($bill->schedule_id == $latestScheduleId);
                }
                
                // If this is the latest bill and it's from downloaded_readings, handle it separately
                if ($isLatestBill && $isFromDownloaded && $latestCurrentBill > 0) {
                    // This is the current/latest bill from downloaded_readings
                    // Check if it's from previous year
                    $latestBillYear = $latestBillMonth ? (int)$latestBillMonth->format('Y') : $currentYear;
                    
                    if ($latestBillYear < $currentYear) {
                        // Latest bill is from previous year - skip (prev_house comes from prevYearBalances)
                        // Do not add to prev_house here
                    } else {
                        // Latest bill is from current year - age it based on meter_reading_schedules.due_date
                        $dueDate = $latestDueDate;
                        
                        if ($dueDate) {
                            $daysPastDue = $asOf->diffInDays($dueDate, false);
                            
                            if ($daysPastDue < 0) {
                                $current += $latestCurrentBill;
                            } elseif ($daysPastDue <= 30) {
                                $days_1_30 += $latestCurrentBill;
                            } elseif ($daysPastDue <= 60) {
                                $days_31_60 += $latestCurrentBill;
                            } elseif ($daysPastDue <= 90) {
                                $days_61_90 += $latestCurrentBill;
                            } elseif ($daysPastDue <= 120) {
                                $days_91_120 += $latestCurrentBill;
                            } else {
                                $over_120 += $latestCurrentBill;
                            }
                        } else {
                            $current += $latestCurrentBill;
                        }
                    }
                    
                    $currentBillProcessed = true;
                    continue; // Skip this bill in the past bills processing
                }
                
                // Past bills from consumer_ledgers - only process current year bills
                if ($isFromLedger) {
                    // Skip previous year bills - they are handled by prevYearBalances
                    if ($isPreviousYear) {
                        continue;
                    }
                    
                    // Use the EXACT balance from consumer_ledgers table
                    $billOutstandingBalance = (float)($bill->balance ?? 0);
                    
                    // Include meter rental (20 pesos from 'others' field) in the balance
                    $meterRental = (float)($bill->others ?? 20.00);
                    $totalBalanceWithRental = $billOutstandingBalance + $meterRental;
                    
                    if ($totalBalanceWithRental <= 0) {
                        continue; // Skip if no balance
                    }
                    
                    // Bill is from current year - age it based on due_date
                    $dueDate = null;
                    if (!empty($bill->mrs_due_date)) {
                        try {
                            $dueDate = Carbon::parse($bill->mrs_due_date);
                        } catch (\Exception $e) {
                            $dueDate = null;
                        }
                    }
                    
                    if (!$dueDate && !empty($bill->cl_due_date)) {
                        try {
                            $dueDate = Carbon::parse($bill->cl_due_date);
                        } catch (\Exception $e) {
                            $dueDate = null;
                        }
                    }
                    
                    // If no due_date, use transaction_date from consumer_ledgers as fallback
                    if (!$dueDate && !empty($bill->transaction_date)) {
                        try {
                            $dueDate = Carbon::parse($bill->transaction_date);
                        } catch (\Exception $e) {
                            $dueDate = null;
                        }
                    }
                    
                    // Age this current year bill (with meter rental included)
                    if ($dueDate) {
                        $daysPastDue = $asOf->diffInDays($dueDate, false);
                        
                        if ($daysPastDue < 0) {
                            $current += $totalBalanceWithRental; // Includes 20 peso meter rental
                        } elseif ($daysPastDue <= 30) {
                            $days_1_30 += $totalBalanceWithRental;
                        } elseif ($daysPastDue <= 60) {
                            $days_31_60 += $totalBalanceWithRental;
                        } elseif ($daysPastDue <= 90) {
                            $days_61_90 += $totalBalanceWithRental;
                        } elseif ($daysPastDue <= 120) {
                            $days_91_120 += $totalBalanceWithRental;
                        } else {
                            $over_120 += $totalBalanceWithRental;
                        }
                    } else {
                        // No due date - put in Current bucket (includes 20 peso meter rental)
                        $current += $totalBalanceWithRental;
                    }
                }
            }
            
            // Handle latest current bill from downloaded_readings if it wasn't already processed
            // This handles cases where the latest bill exists in downloaded_readings but not in consumer_ledgers
            if (!$currentBillProcessed && $latestCurrentBill > 0) {
                // Check if latest bill is from previous year
                $latestBillYear = $latestBillMonth ? (int)$latestBillMonth->format('Y') : $currentYear;
                
                if ($latestBillYear < $currentYear) {
                    // Latest bill is from previous year - skip (prev_house comes from prevYearBalances)
                    // Do not add to prev_house here
                } elseif ($latestDueDate) {
                    // Latest bill is from current year - age it based on meter_reading_schedules.due_date
                    $daysPastDue = $asOf->diffInDays($latestDueDate, false);
                    
                    if ($daysPastDue < 0) {
                        $current += $latestCurrentBill;
                    } elseif ($daysPastDue <= 30) {
                        $days_1_30 += $latestCurrentBill;
                    } elseif ($daysPastDue <= 60) {
                        $days_31_60 += $latestCurrentBill;
                    } elseif ($daysPastDue <= 90) {
                        $days_61_90 += $latestCurrentBill;
                    } elseif ($daysPastDue <= 120) {
                        $days_91_120 += $latestCurrentBill;
                    } else {
                        $over_120 += $latestCurrentBill;
                    }
                } else {
                    // Latest bill has no due date - put in Current bucket
                    $current += $latestCurrentBill;
                }
            }

            // Set Prev_Year from pre-computed ledger balances (balance as of end of previous year)
            $consumerZoneId = $firstBill->consumer_zone_id ?? null;
            if ($consumerZoneId !== null && $consumerZoneId !== '' && $prevYearBalances->has($consumerZoneId)) {
                $prev_house = max(0, (float) $prevYearBalances->get($consumerZoneId));
            }
            
            // Ensure prev_house is never negative (safeguard)
            $prev_house = max(0, $prev_house);
            
            // Total balance = sum of aging buckets (EXCLUDING CURRENT)
            // CURRENT is for display only, not included in balance calculation
            // PREV YEAR is the balance as of December 31 of last year (pre-computed)
            // Balance = 30 DAYS + 60 DAYS + 90 DAYS + 120 DAYS + OVER 120 + PREV YEAR
            $total_balance = $days_1_30 + $days_31_60 + $days_61_90 + $days_91_120 + $over_120 + $prev_house;

            // Get category - prioritize from meter_reading_schedules, fallback to consumer_zone.category_code
            $categoryCode = $firstBill->schedule_category ?? $firstBill->consumer_category ?? '';

            $detailRecords[] = [
                'zone' => $firstBill->zone ?? '',
                'sequence' => $firstBill->sequence ?? 0,
                'account_number' => $accountNumber,
                'account_name' => $firstBill->account_name ?? '',
                'status_code' => $firstBill->status_code ?? '',
                'category_code' => $categoryCode,
                // Expose downloaded current bill explicitly for the view
                'current_bill' => round($latestCurrentBill, 2),
                'current' => round($current, 2),
                'days_1_30' => round($days_1_30, 2),
                'days_31_60' => round($days_31_60, 2),
                'days_61_90' => round($days_61_90, 2),
                'days_91_120' => round($days_91_120, 2),
                'over_120' => round($over_120, 2),
                'prev_house' => round($prev_house, 2),
                'balance' => round($total_balance, 2),
            ];

            // Add to totals
            $totals['current'] += $current;
            $totals['days_1_30'] += $days_1_30;
            $totals['days_31_60'] += $days_31_60;
            $totals['days_61_90'] += $days_61_90;
            $totals['days_91_120'] += $days_91_120;
            $totals['over_120'] += $over_120;
            $totals['prev_house'] += $prev_house;
            $totals['total_balance'] += $total_balance;
        }

        // Round totals
        foreach ($totals as $key => $value) {
            $totals[$key] = round($value, 2);
        }

        // AR Summary Recap (overall totals)
        $arSummaryRecap = [
            'total_accounts' => count($detailRecords),
            'current' => $totals['current'],
            'days_1_30' => $totals['days_1_30'],
            'days_31_60' => $totals['days_31_60'],
            'days_61_90' => $totals['days_61_90'],
            'days_91_120' => $totals['days_91_120'],
            'over_120' => $totals['over_120'],
            'prev_house' => $totals['prev_house'],
            'total_balance' => $totals['total_balance'],
        ];

        // AR Summary per Zone
        $arSummaryZone = collect($detailRecords)->groupBy('zone')->map(function ($items, $zoneCode) {
            return [
                'zone' => $zoneCode ?? 'Unknown',
                'accounts' => $items->count(),
                'current' => round($items->sum('current'), 2),
                'days_1_30' => round($items->sum('days_1_30'), 2),
                'days_31_60' => round($items->sum('days_31_60'), 2),
                'days_61_90' => round($items->sum('days_61_90'), 2),
                'days_91_120' => round($items->sum('days_91_120'), 2),
                'over_120' => round($items->sum('over_120'), 2),
                'prev_house' => round($items->sum('prev_house'), 2),
                'total_balance' => round($items->sum('balance'), 2),
            ];
        })->sortBy('zone')->values();

        // AR Summary by Category
        $arSummaryCategory = collect($detailRecords)->groupBy('category_code')->map(function ($items, $categoryCode) {
            return [
                'category' => $categoryCode ?? 'Unknown',
                'accounts' => $items->count(),
                'current' => round($items->sum('current'), 2),
                'days_1_30' => round($items->sum('days_1_30'), 2),
                'days_31_60' => round($items->sum('days_31_60'), 2),
                'days_61_90' => round($items->sum('days_61_90'), 2),
                'days_91_120' => round($items->sum('days_91_120'), 2),
                'over_120' => round($items->sum('over_120'), 2),
                'prev_house' => round($items->sum('prev_house'), 2),
                'total_balance' => round($items->sum('balance'), 2),
            ];
        })->sortBy('category')->values();

            } catch (\Throwable $e) {
                try {
                    Log::error('AR Aging Summary error: ' . $e->getMessage(), [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                } catch (\Throwable $e2) {
                    // ignore logging failure
                }
                $reportError = 'Report data could not be loaded. Please try again or check your filters.';
            }

            return view('reports.system-report.ar-aging-summary', [
                'zones' => $zones,
                'categories' => $categories,
                'status' => $status,
                'selectedZone' => $zone,
                'selectedCategory' => $category,
                'asOf' => $asOf,
                'billingCutOff' => $billingCutOff,
                'paymentCutOff' => $paymentCutOff,
                'detailRecords' => $detailRecords,
                'totals' => $totals,
                'arSummaryRecap' => $arSummaryRecap,
                'arSummaryZone' => $arSummaryZone,
                'arSummaryCategory' => $arSummaryCategory,
                'reportError' => $reportError,
            ]);
        } catch (\Throwable $outer) {
            try {
                Log::error('AR Aging Summary fatal: ' . $outer->getMessage(), [
                    'file' => $outer->getFile(),
                    'line' => $outer->getLine(),
                    'trace' => $outer->getTraceAsString(),
                ]);
            } catch (\Throwable $e2) {
                // ignore
            }
            // Try to show the report page with empty data; if view fails (e.g. partial throws), show minimal HTML
            try {
                return view('reports.system-report.ar-aging-summary', $safeDefaults());
            } catch (\Throwable $viewEx) {
                try {
                    Log::error('AR Aging Summary view failed: ' . $viewEx->getMessage());
                } catch (\Throwable $e2) {
                    // ignore
                }
                $homeUrl = url('/');
                $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>AR Aging Summary</title></head><body style="font-family:sans-serif;padding:2rem;"><h1>Report could not be loaded</h1><p>Please try again or check storage/logs/laravel.log for details.</p><p><a href="' . htmlspecialchars($homeUrl) . '">Go to Dashboard</a></p></body></html>';
                return response()->make($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
            }
        }
    }

    /**
     * Export AR Aging Summary to Excel using same filters and data as the on-screen report.
     */
    public function exportArAgingSummary(Request $request)
    {
        try {
            // Prevent memory exhaustion on large datasets
            @ini_set('memory_limit', '1024M');
            @ini_set('max_execution_time', '300');

            // Reuse the same filter logic as arAgingSummary
            $status = $request->input('status', '');
            $zone = $request->input('zone', '');
            $category = $request->input('category', '');
            try {
                $asOf = Carbon::parse($request->input('as_of', Carbon::now()->format('Y-m-d')));
            } catch (\Exception $e) {
                $asOf = Carbon::now();
            }
            try {
                $billingCutOff = Carbon::parse($request->input('billing_cutoff', Carbon::now()->format('Y-m-d')));
            } catch (\Exception $e) {
                $billingCutOff = Carbon::now();
            }
            try {
                $paymentCutOff = Carbon::parse($request->input('payment_cutoff', Carbon::now()->format('Y-m-d')));
            } catch (\Exception $e) {
                $paymentCutOff = Carbon::now();
            }

            // Call the same internal logic to get detailRecords
            // We'll need to duplicate the core logic or refactor, but for now let's duplicate the key parts
            // Get available zones
            $zones = MeterReadingSchedule::query()
                ->select('zone')
                ->whereNotNull('zone')
                ->distinct()
                ->orderBy('zone')
                ->pluck('zone')
                ->toArray();

            // Get available categories
            $categories = MeterReadingSchedule::query()
                ->select('category')
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->toArray();

            // OPTIMIZATION: Only get unpaid bills from last 3 years
            $threeYearsAgo = Carbon::now()->subYears(3)->startOfYear();
            
            // Get unpaid bills from consumer_ledgers table
            $query = DB::table('consumer_ledgers as cl')
                ->join('consumer_zone as cz', 'cl.consumer_zone_id', '=', 'cz.id')
                ->leftJoin('meter_reading_schedules as mrs', 'cl.schedule_id', '=', 'mrs.id')
                ->leftJoin('downloaded_readings as dr', 'cl.downloaded_reading_id', '=', 'dr.id')
                ->select(
                    'cz.zone_code as zone',
                    'cz.sequence',
                    'cz.account_no as account_number',
                    'cz.account_name',
                    'cz.status_code',
                    'cz.category_code as consumer_category',
                    'mrs.category as schedule_category',
                    'mrs.zone as schedule_zone',
                    'mrs.id as schedule_id',
                    'mrs.due_date as mrs_due_date',
                    'cl.due_date as cl_due_date',
                    'cl.debit',
                    'cl.others',
                    'cl.credit',
                    'cl.balance',
                    'cl.date as transaction_date',
                    'cl.reference',
                    'mrs.bill_month',
                    'cl.consumer_zone_id'
                )
                ->where('cl.balance', '>', 0)
                ->where(function($q) {
                    $q->where('cl.trans', 'BILL')
                      ->orWhere('cl.trans', 'like', '%BILL%');
                })
                ->where('cl.date', '>=', $threeYearsAgo->format('Y-m-d'));

            // Apply status filter
            if ($status && $status !== '' && $status !== 'All Status') {
                $statusMap = [
                    'A - ACTIVE' => ['A', 'ACTIVE', 'Active', 'active'],
                    'I - INACTIVE' => ['I', 'INACTIVE', 'Inactive', 'inactive'],
                    'S - SUSPENDED' => ['S', 'SUSPENDED', 'Suspended', 'suspended'],
                    'D - DISCONNECTED' => ['D', 'DISCONNECTED', 'Disconnected', 'disconnected'],
                ];
                if (isset($statusMap[$status])) {
                    $query->whereIn('cz.status_code', $statusMap[$status]);
                } else {
                    $query->where('cz.status_code', $status);
                }
            }

            // Apply zone filter
            if ($zone && $zone !== '' && $zone !== 'All Zones') {
                $query->where(function($q) use ($zone) {
                    $q->where('cz.zone_code', $zone)
                      ->orWhere('mrs.zone', $zone);
                });
            }

            // Apply category filter
            if ($category && $category !== '' && $category !== 'All Categories') {
                $query->where(function($q) use ($category) {
                    $q->where('cz.category_code', $category)
                      ->orWhere('mrs.category', $category);
                });
            }

            $records = $query->get();

            if ($records->isEmpty()) {
                return redirect()
                    ->back()
                    ->with('error', 'No AR aging records found for the selected criteria to export.');
            }

            // Pre-compute previous year balances
            $currentYear = (int) $asOf->format('Y');
            $previousYear = $currentYear - 1;
            $prevYearEnd = Carbon::create($previousYear, 12, 31, 23, 59, 59);

            $consumerZoneIds = $records->pluck('consumer_zone_id')->filter()->unique()->values();

            $prevYearBalances = collect();
            if ($consumerZoneIds->isNotEmpty()) {
                $prevYearBalances = DB::table('consumer_ledgers as cl1')
                    ->select('cl1.consumer_zone_id', 'cl1.balance')
                    ->whereIn('cl1.consumer_zone_id', $consumerZoneIds)
                    ->where('cl1.date', '<=', $prevYearEnd->format('Y-m-d'))
                    ->whereNotNull('cl1.balance')
                    ->whereRaw('cl1.id = (
                        SELECT cl2.id FROM consumer_ledgers cl2 
                        WHERE cl2.consumer_zone_id = cl1.consumer_zone_id 
                        AND cl2.date <= ? 
                        ORDER BY cl2.date DESC, cl2.id DESC 
                        LIMIT 1
                    )', [$prevYearEnd->format('Y-m-d')])
                    ->get()
                    ->pluck('balance', 'consumer_zone_id')
                    ->map(fn($balance) => (float) $balance);
            }

            // Get latest downloaded_readings per account
            $latestDownloadedSub = DB::table('downloaded_readings as dr')
                ->leftJoin('meter_reading_schedules as mrs', 'dr.schedule_id', '=', 'mrs.id')
                ->where(function($q) {
                    $q->where(function($q2) {
                        $q2->where('dr.status', '!=', 'paid')
                           ->orWhereNull('dr.status')
                           ->orWhereNull('dr.paid_at');
                    })
                    ->where(function($q3) {
                        $q3->where('dr.current_bill', '>', 0)
                           ->orWhere('mrs.current_bill', '>', 0);
                    });
                })
                ->select('dr.account_number', DB::raw('MAX(dr.id) as latest_dr_id'))
                ->groupBy('dr.account_number');

            $latestDownloadedReadings = DB::table('downloaded_readings as dr')
                ->leftJoin('meter_reading_schedules as mrs', 'dr.schedule_id', '=', 'mrs.id')
                ->joinSub($latestDownloadedSub, 'latest_dr', function($join) {
                    $join->on('dr.id', '=', 'latest_dr.latest_dr_id');
                })
                ->select(
                    'dr.account_number',
                    'dr.current_bill as downloaded_current_bill',
                    'mrs.current_bill as schedule_current_bill',
                    'mrs.due_date',
                    'mrs.bill_month',
                    'mrs.id as schedule_id'
                )
                ->get()
                ->keyBy('account_number');

            $latestDownloadedReadingsNormalized = $latestDownloadedReadings->mapWithKeys(function ($item) {
                $normalized = str_replace('-', '', $item->account_number ?? '');
                return $normalized ? [$normalized => $item] : [];
            });

            // Group by account and calculate aging buckets (same logic as arAgingSummary)
            $accountGroups = $records->groupBy('account_number');
            $detailRecords = [];

            foreach ($accountGroups as $accountNumber => $accountBills) {
                $firstBill = $accountBills->first();
                $current = 0;
                $days_1_30 = 0;
                $days_31_60 = 0;
                $days_61_90 = 0;
                $days_91_120 = 0;
                $over_120 = 0;
                $prev_house = 0;

                $latestDownloadedReading = $latestDownloadedReadings->get($accountNumber);
                if (!$latestDownloadedReading) {
                    $normalizedAcc = str_replace('-', '', $accountNumber);
                    $latestDownloadedReading = $latestDownloadedReadingsNormalized->get($normalizedAcc);
                }

                $latestCurrentBill = 0;
                $latestDueDate = null;
                $latestBillMonth = null;
                $latestScheduleId = null;
                if ($latestDownloadedReading) {
                    $latestCurrentBill = (float)($latestDownloadedReading->downloaded_current_bill ?? $latestDownloadedReading->schedule_current_bill ?? 0);
                    $latestDueDate = $latestDownloadedReading->due_date ? Carbon::parse($latestDownloadedReading->due_date) : null;
                    $latestBillMonth = $latestDownloadedReading->bill_month ? Carbon::parse($latestDownloadedReading->bill_month) : null;
                    $latestScheduleId = $latestDownloadedReading->schedule_id;
                }

                $currentBillProcessed = false;
                
                foreach ($accountBills as $bill) {
                    $isFromLedger = isset($bill->debit);
                    $billYear = $bill->bill_month ? (int)Carbon::parse($bill->bill_month)->format('Y') : (int)Carbon::parse($bill->transaction_date)->format('Y');
                    
                    if ($billYear < $currentYear) {
                        // Previous year bill - add to prev_house
                        $debit = (float)($bill->debit ?? 0);
                        $credit = (float)($bill->credit ?? 0);
                        $billBalance = max(0, $debit - $credit);
                        $prev_house += $billBalance;
                    } else {
                        // Current year bill - age it
                        $debit = (float)($bill->debit ?? 0);
                        $others = (float)($bill->others ?? 20.00);
                        $credit = (float)($bill->credit ?? 0);
                        $baseBill = max(0, $debit - $others);
                        $totalBalanceWithRental = max(0, $baseBill + $others - $credit);
                        
                        if ($latestScheduleId && $bill->schedule_id == $latestScheduleId) {
                            $currentBillProcessed = true;
                        }
                        
                        $dueDate = null;
                        if ($bill->mrs_due_date) {
                            try {
                                $dueDate = Carbon::parse($bill->mrs_due_date);
                            } catch (\Exception $e) {
                                $dueDate = null;
                            }
                        }
                        if (!$dueDate && $bill->cl_due_date) {
                            try {
                                $dueDate = Carbon::parse($bill->cl_due_date);
                            } catch (\Exception $e) {
                                $dueDate = null;
                            }
                        }
                        if (!$dueDate && !empty($bill->transaction_date)) {
                            try {
                                $dueDate = Carbon::parse($bill->transaction_date);
                            } catch (\Exception $e) {
                                $dueDate = null;
                            }
                        }
                        
                        if ($dueDate) {
                            $daysPastDue = $asOf->diffInDays($dueDate, false);
                            
                            if ($daysPastDue < 0) {
                                $current += $totalBalanceWithRental;
                            } elseif ($daysPastDue <= 30) {
                                $days_1_30 += $totalBalanceWithRental;
                            } elseif ($daysPastDue <= 60) {
                                $days_31_60 += $totalBalanceWithRental;
                            } elseif ($daysPastDue <= 90) {
                                $days_61_90 += $totalBalanceWithRental;
                            } elseif ($daysPastDue <= 120) {
                                $days_91_120 += $totalBalanceWithRental;
                            } else {
                                $over_120 += $totalBalanceWithRental;
                            }
                        } else {
                            $current += $totalBalanceWithRental;
                        }
                    }
                }
                
                if (!$currentBillProcessed && $latestCurrentBill > 0) {
                    $latestBillYear = $latestBillMonth ? (int)$latestBillMonth->format('Y') : $currentYear;
                    
                    if ($latestBillYear >= $currentYear && $latestDueDate) {
                        $daysPastDue = $asOf->diffInDays($latestDueDate, false);
                        
                        if ($daysPastDue < 0) {
                            $current += $latestCurrentBill;
                        } elseif ($daysPastDue <= 30) {
                            $days_1_30 += $latestCurrentBill;
                        } elseif ($daysPastDue <= 60) {
                            $days_31_60 += $latestCurrentBill;
                        } elseif ($daysPastDue <= 90) {
                            $days_61_90 += $latestCurrentBill;
                        } elseif ($daysPastDue <= 120) {
                            $days_91_120 += $latestCurrentBill;
                        } else {
                            $over_120 += $latestCurrentBill;
                        }
                    } elseif (!$latestDueDate) {
                        $current += $latestCurrentBill;
                    }
                }

                $consumerZoneId = $firstBill->consumer_zone_id ?? null;
                if ($consumerZoneId !== null && $consumerZoneId !== '' && $prevYearBalances->has($consumerZoneId)) {
                    $prev_house = max(0, (float) $prevYearBalances->get($consumerZoneId));
                }
                
                $prev_house = max(0, $prev_house);
                $total_balance = $days_1_30 + $days_31_60 + $days_61_90 + $days_91_120 + $over_120 + $prev_house;

                $categoryCode = $firstBill->schedule_category ?? $firstBill->consumer_category ?? '';

                $detailRecords[] = [
                    'Zone_code' => $firstBill->zone ?? '',
                    'Sequence' => $firstBill->sequence ?? 0,
                    'ACCOUNT NO' => $accountNumber,
                    'ACCOUNT NAME' => $firstBill->account_name ?? '',
                    'STATUS' => $firstBill->status_code ?? '',
                    'Category_code' => $categoryCode,
                    'CURRENT' => round($current, 2),
                    '30 DAYS' => round($days_1_30, 2),
                    '60 DAYS' => round($days_31_60, 2),
                    '90 DAYS' => round($days_61_90, 2),
                    '120 DAYS' => round($days_91_120, 2),
                    'OVER 120' => round($over_120, 2),
                    'PREV YEAR' => round($prev_house, 2),
                    'BALANCE' => round($total_balance, 2),
                ];
            }

            if (empty($detailRecords)) {
                return redirect()
                    ->back()
                    ->with('error', 'No AR aging records found for the selected criteria to export.');
            }

            // Generate filename
            $zoneText = $zone ? "Zone-{$zone}" : "All-Zones";
            $asOfText = $asOf->format('Ymd');
            $filename = "AR-Aging-Summary-{$zoneText}-AsOf-{$asOfText}-" . Carbon::now()->format('YmdHis') . ".xlsx";

            if (class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
                return \Maatwebsite\Excel\Facades\Excel::download(
                    new class($detailRecords) implements
                        \Maatwebsite\Excel\Concerns\FromCollection,
                        \Maatwebsite\Excel\Concerns\WithHeadings,
                        \Maatwebsite\Excel\Concerns\WithTitle {
                        protected $data;

                        public function __construct($data)
                        {
                            $this->data = collect($data);
                        }

                        public function collection()
                        {
                            return $this->data;
                        }

                        public function headings(): array
                        {
                            $first = $this->data->first();
                            return $first ? array_keys($first) : [];
                        }

                        public function title(): string
                        {
                            return 'AR Aging Summary';
                        }
                    },
                    $filename
                );
            }

            return redirect()
                ->back()
                ->with('error', 'Excel export is not available. Laravel Excel package is missing.');
        } catch (\Exception $e) {
            \Log::error('AR Aging Summary Export Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            
            return redirect()
                ->back()
                ->with('error', 'Error exporting AR aging summary: ' . $e->getMessage());
        }
    }
}
