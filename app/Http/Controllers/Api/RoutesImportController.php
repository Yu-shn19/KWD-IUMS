<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consumer;
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

            // Determine unique keys for upsert
            $lookup = [];
            if (!empty($accountNumber)) {
                $lookup['account_number'] = $accountNumber;
            }
            if (empty($lookup) && !empty($meterNumber)) {
                $lookup['meter_number'] = $meterNumber;
            }

            // If neither key exists, skip this item
            if (empty($lookup)) {
                continue;
            }

            $payload = [
                'full_name' => $fullName,
                'category' => $category,
                'address' => $address,
                'meter_number' => $meterNumber,
                'account_number' => $accountNumber,
                'status' => 'Active',
            ];

            $existing = Consumer::where($lookup)->first();

            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                Consumer::create($payload);
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


