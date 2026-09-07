<?php

namespace App\Services;

use App\Models\ConsumerZone;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

if (!function_exists(__NAMESPACE__ . '\mr_col')) {
    /**
     * Column/table name helper for static analysis.
     */
    function mr_col(string $name): string
    {
        return $name;
    }
}

class StatementOfAccountReportService
{
    public const DEFAULT_CHARGE_PER_CONSUMER = 10.00;

    public const DEFAULT_DESCRIPTION = 'IT Services / System Generation';

    public const MAX_MONTH_SPAN = 24;

    /**
     * @return array{
     *     zones: Collection<int, string>,
     *     selectedZone: string|null,
     *     fromMonthInput: string,
     *     toMonthInput: string,
     *     fromMonth: Carbon,
     *     toMonth: Carbon,
     *     chargePerConsumer: float,
     *     interest: float,
     *     description: string,
     *     invoiceRows: Collection<int, object>,
     *     detailRows: Collection<int, object>,
     *     showDetail: bool,
     *     totals: array{consumers: int, subtotal: float, interest: float, total_due: float},
     *     billMonthInput: string|null,
     *     year: int
     * }
     */
    public function buildReportData(Request $request): array
    {
        $zones = ConsumerZone::distinctZoneCodes();
        $selectedZone = trim((string) $request->input('zone', ''));
        $selectedZone = $selectedZone !== '' ? $selectedZone : null;

        [$fromMonth, $toMonth, $fromMonthInput, $toMonthInput] = $this->resolvePeriod($request);

        $chargePerConsumer = $this->resolveChargePerConsumer($request);
        $interest = $this->resolveInterest($request);
        $description = $this->resolveDescription($request);

        $invoiceRows = $this->buildInvoiceRows(
            $fromMonth,
            $toMonth,
            $selectedZone,
            $chargePerConsumer,
            $description
        );

        $showDetail = $fromMonth->isSameMonth($toMonth);
        $detailRows = $showDetail
            ? $this->buildDetailRows($fromMonth, $selectedZone, $chargePerConsumer)
            : collect();

        $subtotal = round((float) $invoiceRows->sum('amount'), 2);
        $totalDue = round($subtotal + $interest, 2);

        $totals = [
            'consumers' => (int) $invoiceRows->sum('quantity'),
            'subtotal' => $subtotal,
            'interest' => $interest,
            'total_due' => $totalDue,
            // Back-compat for older export / views
            'amount' => $subtotal,
        ];

        return [
            'zones' => $zones,
            'selectedZone' => $selectedZone,
            'fromMonthInput' => $fromMonthInput,
            'toMonthInput' => $toMonthInput,
            'fromMonth' => $fromMonth,
            'toMonth' => $toMonth,
            'chargePerConsumer' => $chargePerConsumer,
            'interest' => $interest,
            'description' => $description,
            'invoiceRows' => $invoiceRows,
            'monthlyRows' => $invoiceRows,
            'detailRows' => $detailRows,
            'showDetail' => $showDetail,
            'totals' => $totals,
            'billMonthInput' => $showDetail ? $fromMonthInput : null,
            'year' => (int) $fromMonth->year,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string, 3: string}
     */
    public function resolvePeriod(Request $request): array
    {
        $fromInput = trim((string) $request->input('from_month', ''));
        $toInput = trim((string) $request->input('to_month', ''));

        if ($fromInput === '' && $toInput === '') {
            // Legacy: bill_month + months
            $billMonthInput = trim((string) $request->input('bill_month', ''));
            if ($billMonthInput !== '') {
                try {
                    $from = Carbon::createFromFormat('Y-m', $billMonthInput)->startOfMonth();
                    $months = (int) $request->input('months', 1);
                    $months = max(1, min(self::MAX_MONTH_SPAN, $months));
                    $to = $from->copy()->addMonths($months - 1)->startOfMonth();

                    return [
                        $from,
                        $to,
                        $from->format('Y-m'),
                        $to->format('Y-m'),
                    ];
                } catch (\Throwable $e) {
                    // fall through to defaults
                }
            }

            $to = Carbon::now()->startOfMonth();
            $from = $to->copy()->subMonth()->startOfMonth();

            return [
                $from,
                $to,
                $from->format('Y-m'),
                $to->format('Y-m'),
            ];
        }

        $from = $this->parseYearMonth($fromInput) ?? Carbon::now()->subMonth()->startOfMonth();
        $to = $this->parseYearMonth($toInput) ?? Carbon::now()->startOfMonth();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        $span = ((int) $from->diffInMonths($to)) + 1;
        if ($span > self::MAX_MONTH_SPAN) {
            $to = $from->copy()->addMonths(self::MAX_MONTH_SPAN - 1)->startOfMonth();
        }

        return [
            $from->startOfMonth(),
            $to->startOfMonth(),
            $from->format('Y-m'),
            $to->format('Y-m'),
        ];
    }

    public function resolveChargePerConsumer(Request $request): float
    {
        if ($request->filled('charge_per_consumer')) {
            $parsed = round((float) $request->input('charge_per_consumer'), 2);

            return max(0, min($parsed, 999999.99));
        }

        return self::DEFAULT_CHARGE_PER_CONSUMER;
    }

    public function resolveInterest(Request $request): float
    {
        if (!$request->filled('interest')) {
            return 0.0;
        }

        $parsed = round((float) $request->input('interest'), 2);

        return max(0, min($parsed, 9999999.99));
    }

    public function resolveDescription(Request $request): string
    {
        $description = trim((string) $request->input('description', ''));
        if ($description === '') {
            return self::DEFAULT_DESCRIPTION;
        }

        return mb_substr($description, 0, 120);
    }

    /**
     * @return Collection<int, object>
     */
    public function buildInvoiceRows(
        Carbon $fromMonth,
        Carbon $toMonth,
        ?string $zone,
        float $chargePerConsumer,
        string $description
    ): Collection {
        $countsByMonth = $this->successfulConsumerCountsByMonth($fromMonth, $toMonth, $zone);

        $rows = collect();
        $cursor = $fromMonth->copy()->startOfMonth();
        $end = $toMonth->copy()->startOfMonth();

        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $qty = (int) ($countsByMonth[$key] ?? 0);
            $amount = round($qty * $chargePerConsumer, 2);

            $rows->push((object) [
                'reading_month' => $key,
                'reading_month_label' => $cursor->format('F Y'),
                'date_label' => $this->formatInvoiceDateRange($cursor),
                'description' => $description,
                'quantity' => $qty,
                'successful_consumers' => $qty,
                'rate' => $chargePerConsumer,
                'amount' => $amount,
            ]);

            $cursor->addMonth();
        }

        return $rows;
    }

    /**
     * @return array<string, int>
     */
    public function successfulConsumerCountsByMonth(Carbon $fromMonth, Carbon $toMonth, ?string $zone): array
    {
        $monthExpr = 'DATE_FORMAT(COALESCE(' . mr_col('mrs.bill_month') . ', ' . mr_col('dr.reading_date') . '), "%Y-%m")';
        $accountExpr = 'COALESCE(NULLIF(TRIM(' . mr_col('cz.account_no') . '), ""), CONCAT("id-", COALESCE(' . mr_col('dr.consumer_zone_id') . ', ' . mr_col('mrs.consumer_zone_id') . ', ' . mr_col('dr.id') . ')))';

        $start = $fromMonth->copy()->startOfMonth()->toDateString();
        $end = $toMonth->copy()->endOfMonth()->toDateString();

        $rows = $this->successfulReadingsBaseQuery($zone)
            ->selectRaw("{$monthExpr} as reading_month")
            ->selectRaw("COUNT(DISTINCT {$accountExpr}) as successful_consumers")
            ->whereBetween(
                DB::raw('COALESCE(' . mr_col('mrs.bill_month') . ', ' . mr_col('dr.reading_date') . ')'),
                [$start, $end]
            )
            ->groupBy(DB::raw($monthExpr))
            ->orderBy(DB::raw($monthExpr))
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $key = (string) ($row->reading_month ?? '');
            if ($key !== '') {
                $map[$key] = (int) ($row->successful_consumers ?? 0);
            }
        }

        return $map;
    }

    /**
     * @return Collection<int, object>
     */
    public function buildDetailRows(Carbon $billMonth, ?string $zone, float $chargePerConsumer): Collection
    {
        $start = $billMonth->copy()->startOfMonth()->toDateString();
        $end = $billMonth->copy()->endOfMonth()->toDateString();

        $rows = $this->successfulReadingsBaseQuery($zone)
            ->select(
                'dr.id',
                'cz.zone_code as zone',
                'cz.account_no as account_number',
                'cz.account_name',
                'cz.address',
                'dr.reading_date',
                'dr.status',
                'dr.current_reading',
                'dr.consumption',
                'mrs.bill_month',
                'mrs.sedr_number'
            )
            ->whereBetween(
                DB::raw('COALESCE(' . mr_col('mrs.bill_month') . ', ' . mr_col('dr.reading_date') . ')'),
                [$start, $end]
            )
            ->orderBy(mr_col('cz.zone_code'))
            ->orderBy(mr_col('cz.account_no'))
            ->get();

        return $rows
            ->groupBy(function ($row) {
                $account = trim((string) ($row->account_number ?? ''));

                return $account !== '' ? $account : ('id-' . $row->id);
            })
            ->map(function (Collection $group) use ($chargePerConsumer) {
                $item = $group->sortByDesc(function ($row) {
                    return $row->reading_date ? Carbon::parse($row->reading_date)->timestamp : 0;
                })->first();

                $account = trim((string) ($item->account_number ?? ''));

                return (object) [
                    'zone' => $item->zone,
                    'account_number' => $account !== '' ? $account : null,
                    'account_name' => $item->account_name,
                    'address' => $item->address,
                    'sedr_number' => $item->sedr_number,
                    'reading_date' => $item->reading_date ? Carbon::parse($item->reading_date) : null,
                    'bill_month' => $item->bill_month ? Carbon::parse($item->bill_month) : null,
                    'status' => $item->status,
                    'current_reading' => $item->current_reading,
                    'consumption' => $item->consumption,
                    'charge' => $chargePerConsumer,
                ];
            })
            ->values();
    }

    public function formatInvoiceDateRange(Carbon $month): string
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        return $start->format('F j') . '-' . $end->format('j, Y');
    }

    private function parseYearMonth(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m', $value)->startOfMonth();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Base query: successful readings from downloaded_readings.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private function successfulReadingsBaseQuery(?string $zone)
    {
        $query = DB::table(mr_col('downloaded_readings as dr'))
            ->leftJoin(mr_col('meter_reading_schedules as mrs'), mr_col('dr.schedule_id'), '=', mr_col('mrs.id'))
            ->leftJoin(mr_col('consumer_zone as cz'), function ($join) {
                $join->on(mr_col('cz.id'), '=', DB::raw('COALESCE(' . mr_col('dr.consumer_zone_id') . ', ' . mr_col('mrs.consumer_zone_id') . ')'));
            })
            ->where(function ($q) {
                $q->whereNotNull(mr_col('dr.current_reading'))
                    ->orWhereRaw('LOWER(COALESCE(' . mr_col('dr.status') . ', "")) IN (?, ?)', [
                        'completed',
                        'paid',
                    ]);
            })
            ->whereRaw('COALESCE(' . mr_col('mrs.bill_month') . ', ' . mr_col('dr.reading_date') . ') IS NOT NULL');

        if ($zone !== null && $zone !== '') {
            ConsumerZone::applyZoneCodeConstraint($query, $zone, 'cz.zone_code');
        }

        return $query;
    }
}
