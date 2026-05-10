<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    /**
     * Get customer routes for mobile app
     * Returns list of meter reading schedules with accountNumber, name, and category
     * Database: wdms, Table: meter_reading_schedules
     */
    public function getRoutes(Request $request)
    {
        try {
            // Query meter_reading_schedules from database
            // Adjust column names based on your actual database structure
            $routes = DB::table('meter_reading_schedules')
                ->select(
                    'id',
                    DB::raw('COALESCE(account_number, accountNumber, account_no) as accountNumber'),
                    DB::raw('COALESCE(customer_name, name, customer_name) as name'),
                    DB::raw('COALESCE(category, consumer_type, "12") as category'),
                    DB::raw('COALESCE(address, customer_address, "") as address'),
                    DB::raw('COALESCE(meter_number, meterNumber, meter_no) as meterNumber'),
                    DB::raw('COALESCE(last_reading, lastReading, previous_reading, 0) as lastReading'),
                    DB::raw('COALESCE(estimated_reading, estimatedReading, estimated_reading, 0) as estimatedReading')
                )
                ->orderBy('id')
                ->get();

            // Return in the format expected by the mobile app
            return response()->json([
                'success' => true,
                'data' => $routes
            ]);
        } catch (\Exception $e) {
            // Try alternative column names if first query fails
            try {
                $routes = DB::table('meter_reading_schedules')
                    ->select(
                        'id',
                        DB::raw('COALESCE(accountNumber, account_number) as accountNumber'),
                        DB::raw('COALESCE(name, customer_name) as name'),
                        DB::raw('COALESCE(category, "12") as category'),
                        DB::raw('COALESCE(address, "") as address'),
                        DB::raw('COALESCE(meterNumber, meter_number) as meterNumber'),
                        DB::raw('COALESCE(lastReading, last_reading, 0) as lastReading'),
                        DB::raw('COALESCE(estimatedReading, estimated_reading, 0) as estimatedReading')
                    )
                    ->orderBy('id')
                    ->get();

                return response()->json([
                    'success' => true,
                    'data' => $routes
                ]);
            } catch (\Exception $e2) {
                // Return error with details for debugging
                return response()->json([
                    'success' => false,
                    'data' => [],
                    'message' => 'Error querying meter_reading_schedules table: ' . $e2->getMessage(),
                    'error' => $e2->getMessage()
                ], 500);
            }
        }
    }

    /**
     * Get all customers
     */
    public function index()
    {
        try {
            $customers = DB::table('customers')
                ->select(
                    'id',
                    'account_number as accountNumber',
                    'name',
                    'email',
                    'phone',
                    'address',
                    'meter_number as meterNumber',
                    'status'
                )
                ->get();

            return response()->json([
                'success' => true,
                'data' => $customers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching customers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer by ID
     */
    public function show($id)
    {
        try {
            $customer = DB::table('customers')
                ->where('id', $id)
                ->first();

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $customer
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching customer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import routes into meter_reading_schedules
     * Accepts JSON body: { routes: [ { accountNumber, name, category, address, meterNumber, lastReading, estimatedReading } ] }
     */
    public function importRoutes(Request $request)
    {
        $routes = $request->input('routes');
        if (!is_array($routes)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid payload: routes must be an array'
            ], 422);
        }

        $inserted = 0;
        $updated = 0;

        DB::beginTransaction();
        try {
            foreach ($routes as $item) {
                // Normalize keys
                $accountNumber = $item['accountNumber'] ?? $item['account_number'] ?? $item['account_no'] ?? null;
                if (!$accountNumber) {
                    // Skip if no account number
                    continue;
                }

                $data = [
                    // Prefer explicit keys, fallback to common aliases
                    'account_number'     => $accountNumber,
                    'customer_name'      => $item['name'] ?? $item['customer_name'] ?? null,
                    'category'           => $item['category'] ?? $item['consumer_type'] ?? null,
                    'address'            => $item['address'] ?? $item['customer_address'] ?? null,
                    'meter_number'       => $item['meterNumber'] ?? $item['meter_number'] ?? $item['meter_no'] ?? null,
                    'last_reading'       => $item['lastReading'] ?? $item['previous_reading'] ?? 0,
                    'estimated_reading'  => $item['estimatedReading'] ?? $item['estimated_reading'] ?? 0,
                ];

                // Upsert by account_number
                $exists = DB::table('meter_reading_schedules')->where('account_number', $accountNumber)->first();
                if ($exists) {
                    DB::table('meter_reading_schedules')->where('account_number', $accountNumber)->update($data);
                    $updated++;
                } else {
                    DB::table('meter_reading_schedules')->insert($data);
                    $inserted++;
                }
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Routes imported successfully',
                'inserted' => $inserted,
                'updated' => $updated,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}

