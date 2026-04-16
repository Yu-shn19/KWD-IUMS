<?php

namespace App\Http\Controllers;

use App\Models\Penalty;
use App\Models\ConsumerLedger;
use App\Models\ConsumerZoneOne;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PenaltyController extends Controller
{
    /**
     * Get penalty data for editing
     */
    public function show($id)
    {
        $penalty = Penalty::with('consumerLedgers')->findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $penalty
        ]);
    }

    /**
     * Update an existing penalty
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'date' => 'required|date',
            'due_date' => 'nullable|date',
            'reference' => 'nullable|string|max:100',
            'bill_amount' => 'nullable|numeric|min:0',
            'penalty_amount' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $penalty = Penalty::findOrFail($id);
            $oldLedger = ConsumerLedger::where('penalty_id', $id)->first();
            
            if (!$oldLedger) {
                return response()->json([
                    'success' => false,
                    'message' => 'Related consumer ledger entry not found'
                ], 404);
            }

            // Store old values for balance reversal
            $oldPenaltyAmount = (float)$penalty->penalty_amount;
            $oldDate = $penalty->date;
            $oldDateTime = Carbon::parse($oldDate)->format('Y-m-d H:i:s');
            $oldBalance = (float)($oldLedger->balance ?? 0);

            // Calculate what the balance was BEFORE the old penalty transaction
            // Penalty increases balance: oldBalance = previousBalance + oldPenaltyAmount
            // So: previousBalance = oldBalance - oldPenaltyAmount
            $previousBalanceBeforeOld = round($oldBalance - $oldPenaltyAmount, 2);

            // Verify by checking the actual ledger entry before this one
            $actualPreviousLedger = ConsumerLedger::where('consumer_zone_id', $oldLedger->consumer_zone_id)
                ->where('txtime', '<', $oldDateTime)
                ->orderBy('txtime', 'desc')
                ->orderBy('id', 'desc')
                ->first();
            
            if ($actualPreviousLedger) {
                $previousBalanceBeforeOld = (float)($actualPreviousLedger->balance ?? $previousBalanceBeforeOld);
            } else {
                // If no previous ledger, use consumer zone balance
                $consumerZoneBefore = ConsumerZoneOne::find($oldLedger->consumer_zone_id);
                if ($consumerZoneBefore) {
                    $previousBalanceBeforeOld = (float)($consumerZoneBefore->balance ?? $previousBalanceBeforeOld);
                }
            }

            // Parse new date
            try {
                $dateCarbon = Carbon::parse($request->date);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid date format.'
                ], 422);
            }
            $newDate = $dateCarbon->format('Y-m-d');
            $newDateTime = $dateCarbon->format('Y-m-d H:i:s');

            // Parse due_date if provided
            $dueDate = null;
            if ($request->due_date) {
                try {
                    $dueDateCarbon = Carbon::parse($request->due_date);
                    $dueDate = $dueDateCarbon->format('Y-m-d');
                } catch (\Exception $e) {
                    // Keep existing due_date if parsing fails
                    $dueDate = $penalty->due_date ? Carbon::parse($penalty->due_date)->format('Y-m-d') : null;
                }
            } else {
                $dueDate = $penalty->due_date ? Carbon::parse($penalty->due_date)->format('Y-m-d') : null;
            }

            // Calculate new balance with new penalty amount
            $newPenaltyAmount = (float)$request->penalty_amount;
            $newBalance = round($previousBalanceBeforeOld + $newPenaltyAmount, 2);

            // Update penalty record
            $penalty->update([
                'date' => $newDate,
                'due_date' => $dueDate,
                'reference' => $request->reference ?: $penalty->reference,
                'bill_amount' => (float)($request->bill_amount ?? $penalty->bill_amount),
                'penalty_amount' => $newPenaltyAmount,
                'balance' => $newBalance,
                'username' => auth()->user()->name ?? $penalty->username,
                'txtime' => $newDateTime,
            ]);

            // Update consumer ledger entry
            $oldLedger->update([
                'date' => $newDate,
                'due_date' => $dueDate,
                'reference' => $request->reference ?: $oldLedger->reference,
                'penalty' => $newPenaltyAmount,
                'debit' => $newPenaltyAmount, // Penalty is always a debit
                'credit' => 0,
                'balance' => $newBalance,
                'username' => auth()->user()->name ?? $oldLedger->username,
                'txtime' => $newDateTime,
            ]);

            // Recalculate balances for all subsequent ledger entries
            $this->recalculateSubsequentBalances($oldLedger->consumer_zone_id, $newDateTime, $newBalance);

            // Update consumer zone balance
            $latestBalance = ConsumerLedger::where('consumer_zone_id', $oldLedger->consumer_zone_id)
                ->orderBy('txtime', 'desc')
                ->orderBy('id', 'desc')
                ->first();
            
            if ($latestBalance) {
                $consumerZone = ConsumerZoneOne::find($oldLedger->consumer_zone_id);
                if ($consumerZone) {
                    $consumerZone->balance = (float)($latestBalance->balance ?? 0);
                    $consumerZone->save();
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Penalty updated successfully',
                'reference' => $penalty->reference,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating penalty', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating penalty: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recalculate balances for all ledger entries after a given datetime
     */
    private function recalculateSubsequentBalances($consumerZoneId, $afterDateTime, $startingBalance)
    {
        $subsequentEntries = ConsumerLedger::where('consumer_zone_id', $consumerZoneId)
            ->where('txtime', '>', $afterDateTime)
            ->orderBy('txtime', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $currentBalance = $startingBalance;

        foreach ($subsequentEntries as $entry) {
            $currentBalance = round($currentBalance + (float)$entry->debit - (float)$entry->credit, 2);
            $entry->balance = $currentBalance;
            $entry->save();
        }
    }
}
