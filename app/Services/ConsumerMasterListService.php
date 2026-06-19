<?php

namespace App\Services;

use App\Models\ConsumerZone;
use App\Models\MeterReadingSchedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (!function_exists(__NAMESPACE__ . '\mr_col')) {
    /**
     * Column/table name helper for static analysis.
     */
    function mr_col(string $name): string
    {
        return $name;
    }
}

class ConsumerMasterListService
{
    /**
     * @return array{
     *     zones: Collection<int, mixed>,
     *     consumers: Collection<int, ConsumerZone>,
     *     summaryByZone: Collection<int, object>,
     *     filters: array<string, mixed>
     * }
     */
    public function buildReportData(Request $request): array
    {
        $filters = $this->extractFilters($request);

        $zones = ConsumerZone::select('zone_code')
            ->distinct()
            ->orderBy(mr_col('zone_code'))
            ->pluck(mr_col('zone_code'));

        $baseQuery = ConsumerZone::query();
        $this->applyFilters($baseQuery, $filters);

        $consumers = $this->fetchConsumers($baseQuery);
        $this->attachReadingGuideFields($consumers);

        $summaryByZone = (clone $baseQuery)
            ->select('zone_code as zone', DB::raw('COUNT(*) as total'))
            ->groupBy(mr_col('zone_code'))
            ->orderBy(mr_col('zone_code'))
            ->get();

        return compact('zones', 'consumers', 'summaryByZone', 'filters');
    }

    /**
     * @return array<string, mixed>
     */
    private function extractFilters(Request $request): array
    {
        return $request->only([
            'search',
            'zone',
            'status',
            'senior_citizen',
            'meter_number',
            'address',
            'meter_location',
            'ledger_status',
        ]);
    }

    /**
     * @param  Builder<ConsumerZone>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['search'])) {
            $searchTerm = $filters['search'];
            $query->where(function (Builder $q) use ($searchTerm): void {
                $q->where(mr_col('account_name'), 'like', '%' . $searchTerm . '%')
                    ->orWhere(mr_col('account_no'), 'like', '%' . $searchTerm . '%');
            });
        }

        if (!empty($filters['zone'])) {
            $query->where(mr_col('zone_code'), $filters['zone']);
        }

        if (!empty($filters['status'])) {
            $statusValue = $filters['status'];

            $statusMap = [
                'Active' => ['A', 'ACTIVE', 'Active', 'A - ACTIVE'],
                'Pending' => ['P', 'PENDING', 'Pending'],
                'Disconnected' => ['X', 'DISCONNECTED', 'Disconnected', 'D'],
            ];

            if (isset($statusMap[$statusValue])) {
                $query->whereIn(mr_col('status_code'), $statusMap[$statusValue]);
            } else {
                $query->where(mr_col('status_code'), $statusValue);
            }
        }

        if (!empty($filters['senior_citizen']) && Schema::hasColumn('consumer_zone', 'is_senior_citizen')) {
            $query->where(mr_col('is_senior_citizen'), true);
        }

        if (!empty($filters['meter_number'])) {
            $query->where(mr_col('meter_number'), 'like', '%' . $filters['meter_number'] . '%');
        }

        if (!empty($filters['address'])) {
            $query->where(mr_col('address'), 'like', '%' . $filters['address'] . '%');
        }

        if (!empty($filters['meter_location']) && Schema::hasColumn('consumer_zone', 'meter_location')) {
            $query->where(mr_col('meter_location'), 'like', '%' . $filters['meter_location'] . '%');
        }

        if (!empty($filters['ledger_status'])) {
            if ($filters['ledger_status'] === 'missing') {
                $query->whereDoesntHave('ledgers');
            } elseif ($filters['ledger_status'] === 'imported') {
                $query->whereHas('ledgers');
            }
        }
    }

    /**
     * @param  Builder<ConsumerZone>  $baseQuery
     * @return Collection<int, ConsumerZone>
     */
    private function fetchConsumers(Builder $baseQuery): Collection
    {
        $consumersQuery = (clone $baseQuery)->orderBy(mr_col('zone_code'));

        $dbDriver = DB::connection()->getDriverName();
        if (in_array($dbDriver, ['mysql', 'mariadb'], true)) {
            // e.g. 081-12-4428 → ascending by last segment 4428 (numeric, not lexicographic)
            $consumersQuery->orderByRaw(
                'CAST(SUBSTRING_INDEX(TRIM(COALESCE(account_no, "")), "-", -1) AS UNSIGNED) ASC'
            );
        }

        $consumers = $consumersQuery->withCount('ledgers')->get();

        if (!in_array($dbDriver, ['mysql', 'mariadb'], true)) {
            $consumers = $consumers
                ->sortBy(function (ConsumerZone $consumer): array {
                    return [
                        $consumer->zone_code ?? '',
                        $this->accountTailSortKey($consumer->account_no ?? null),
                    ];
                })
                ->values();
        }

        return $consumers;
    }

    /**
     * @param  Collection<int, ConsumerZone>  $consumers
     */
    private function attachReadingGuideFields(Collection $consumers): void
    {
        $previousMonth = Carbon::now()->subMonthNoOverflow();
        $accountNos = $consumers->pluck(mr_col('account_no'))->filter()->values();
        $previousScheduleByAccount = collect();
        $latestScheduleByAccount = collect();

        if ($accountNos->isNotEmpty()) {
            $previousScheduleByAccount = $this->loadPreviousMonthSchedules($accountNos, $previousMonth);
            $latestScheduleByAccount = $this->loadLatestMonthSchedules($accountNos);
        }

        $consumers->transform(function (ConsumerZone $consumer) use ($previousScheduleByAccount, $latestScheduleByAccount): ConsumerZone {
            $prev = $previousScheduleByAccount->get($consumer->account_no);
            $latest = $latestScheduleByAccount->get($consumer->account_no);
            $consumer->prev_bill_date = $prev && $prev->bill_date ? Carbon::parse($prev->bill_date)->format('m/d/Y') : '';
            $consumer->prev_pres_rdg = $latest && $latest->current_reading !== null ? (string) ((int) $latest->current_reading) : '';
            $consumer->prev_prev_rdg = $prev && $prev->current_reading !== null
                ? (string) ((int) $prev->current_reading)
                : ($prev && $prev->previous_reading !== null ? (string) ((int) $prev->previous_reading) : '');

            return $consumer;
        });
    }

    /**
     * @param  Collection<int, mixed>  $accountNos
     * @return Collection<string, object>
     */
    private function loadPreviousMonthSchedules(Collection $accountNos, Carbon $previousMonth): Collection
    {
        return MeterReadingSchedule::query()
            ->joinConsumerZone()
            ->whereIn(mr_col('cz.account_no'), $accountNos)
            ->whereYear(mr_col('meter_reading_schedules.bill_month'), $previousMonth->year)
            ->whereMonth(mr_col('meter_reading_schedules.bill_month'), $previousMonth->month)
            ->orderBy(mr_col('meter_reading_schedules.bill_date'), 'desc')
            ->orderBy(mr_col('meter_reading_schedules.id'), 'desc')
            ->get([
                'cz.account_no as account_number',
                'meter_reading_schedules.bill_date',
                'meter_reading_schedules.current_reading',
                'meter_reading_schedules.previous_reading',
            ])
            ->groupBy(mr_col('account_number'))
            ->map(function (Collection $rows): ?object {
                return $rows->first();
            });
    }

    /**
     * @param  Collection<int, mixed>  $accountNos
     * @return Collection<string, object>
     */
    private function loadLatestMonthSchedules(Collection $accountNos): Collection
    {
        $latestGeneratedBillMonth = MeterReadingSchedule::query()->whereNotNull(mr_col('bill_month'))->max(mr_col('bill_month'));
        if (!$latestGeneratedBillMonth) {
            return collect();
        }

        $latestMonth = Carbon::parse($latestGeneratedBillMonth);

        return MeterReadingSchedule::query()
            ->joinConsumerZone()
            ->whereIn(mr_col('cz.account_no'), $accountNos)
            ->whereYear(mr_col('meter_reading_schedules.bill_month'), $latestMonth->year)
            ->whereMonth(mr_col('meter_reading_schedules.bill_month'), $latestMonth->month)
            ->orderBy(mr_col('meter_reading_schedules.bill_date'), 'desc')
            ->orderBy(mr_col('meter_reading_schedules.id'), 'desc')
            ->get([
                'cz.account_no as account_number',
                'meter_reading_schedules.bill_month',
                'meter_reading_schedules.bill_date',
                'meter_reading_schedules.current_reading',
                'meter_reading_schedules.previous_reading',
            ])
            ->groupBy(mr_col('account_number'))
            ->map(function (Collection $rows): ?object {
                return $rows->first();
            });
    }

    private function accountTailSortKey(?string $accountNo): int
    {
        $acc = trim((string) $accountNo);
        if ($acc === '') {
            return 0;
        }
        $pos = strrpos($acc, '-');
        $tail = $pos === false ? $acc : substr($acc, $pos + 1);

        return (int) preg_replace('/\D/', '', $tail);
    }
}
