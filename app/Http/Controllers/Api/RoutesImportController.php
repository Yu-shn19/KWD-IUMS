<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumerZoneOne;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoutesImportController extends Controller
{
    /**
     * Import route/consumer data.
     * Accepts JSON payload with an array under key `routes`.
     * Each item may include: accountNumber, name, category, address,
     * meterNumber, lastReading, estimatedReading.
     *
     * Returns counts for inserted and updated records.
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'routes' => 'required|array|min:1',
            'routes.*.accountNumber' => 'nullable|string',
            'routes.*.name' => 'required|string',
            'routes.*.category' => 'nullable|string',
            'routes.*.address' => 'nullable|string',
            'routes.*.meterNumber' => 'nullable|string',
            'routes.*.lastReading' => 'nullable|integer|min:0',
            'routes.*.estimatedReading' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $routes = $request->input('routes', []);

        $inserted = 0;
        $updated = 0;

        foreach ($routes as $item) {
            $accountNumber = $item['accountNumber'] ?? null;
            $meterNumber = $item['meterNumber'] ?? null;
            $fullName = $item['name'] ?? null;
            $category = $item['category'] ?? null;
            $address = $item['address'] ?? null;

            // Determine unique keys for upsert (consumer_zone: account_no, meter_number)
            $lookup = [];
            if (!empty($accountNumber)) {
                $lookup['account_no'] = $accountNumber;
            }
            if (empty($lookup) && !empty($meterNumber)) {
                $lookup['meter_number'] = $meterNumber;
            }

            // If neither key exists, skip this item
            if (empty($lookup)) {
                continue;
            }

            $accountNo = $accountNumber ?: ('MTR-'.preg_replace('/\W+/', '', (string) $meterNumber));

            $payload = [
                'account_name' => $fullName,
                'category_code' => $category,
                'address1' => $address,
                'meter_number' => $meterNumber,
                'account_no' => $accountNo,
                'status_code' => 'A',
                'zone_code' => $item['zone'] ?? $item['zoneCode'] ?? 'UN',
            ];

            $existing = ConsumerZoneOne::where($lookup)->first();

            if ($existing) {
                $existing->update(array_filter([
                    'account_name' => $fullName,
                    'category_code' => $category,
                    'address1' => $address,
                    'meter_number' => $meterNumber,
                    'account_no' => $accountNumber ?: $existing->account_no,
                    'status_code' => 'A',
                ], fn ($v) => $v !== null && $v !== ''));
                $updated++;
            } else {
                ConsumerZoneOne::create($payload);
                $inserted++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Routes imported successfully',
            'inserted' => $inserted,
            'updated' => $updated,
        ]);
    }
}



