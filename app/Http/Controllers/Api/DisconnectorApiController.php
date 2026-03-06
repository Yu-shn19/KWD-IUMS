<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DisconnectionOrder;
use App\Models\User;
use App\Models\ConsumerPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DisconnectorApiController extends Controller
{
    /**
     * Get disconnection assignments for a specific disconnector
     * 
     * GET /api/disconnector/assignments?disconnector_id={id}
     */
    public function getAssignments(Request $request)
    {
        $request->validate([
            'disconnector_id' => 'required|exists:users,id',
        ]);

        $disconnectorId = $request->input('disconnector_id');

        try {
            // Get all active assignments for this disconnector
            $assignments = DisconnectionOrder::where('disconnector_id', $disconnectorId)
                ->whereIn('status', ['assigned', 'in-progress'])
                ->with('consumer')
                ->orderBy('disconnection_date', 'asc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($order) {
                    return $this->formatAssignmentForMobile($order);
                });

            return response()->json([
                'success' => true,
                'assignments' => $assignments,
                'count' => count($assignments),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching assignments: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all disconnection orders (for admin/dashboard)
     * 
     * GET /api/disconnector/orders?status={status}&zone={zone}
     */
    public function getOrders(Request $request)
    {
        try {
            $query = DisconnectionOrder::with(['consumer', 'disconnector']);

            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->has('zone')) {
                $query->where('zone_code', $request->input('zone'));
            }

            if ($request->has('disconnector_id')) {
                $query->where('disconnector_id', $request->input('disconnector_id'));
            }

            $orders = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'orders' => $orders,
                'count' => count($orders),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching orders: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a single disconnection order details
     * 
     * GET /api/disconnector/orders/{orderId}
     */
    public function getOrder($orderId)
    {
        try {
            $order = DisconnectionOrder::with(['consumer', 'disconnector'])
                ->findOrFail($orderId);

            return response()->json([
                'success' => true,
                'order' => $order,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found: ' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update assignment status (mark as in-progress, disconnected, reconnected, etc.)
     * 
     * POST /api/disconnector/assignments/status
     * {
     *   "order_id": 1,
     *   "status": "disconnected|in-progress|reconnected",
     *   "notes": "optional notes"
     * }
     */
    public function updateAssignmentStatus(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:disconnection_orders,id',
            'status' => 'required|in:in-progress,disconnected,reconnected,cancelled',
            'notes' => 'nullable|string',
        ]);

        try {
            $order = DisconnectionOrder::findOrFail($request->input('order_id'));
            $status = $request->input('status');
            $notes = $request->input('notes');

            switch ($status) {
                case 'in-progress':
                    $order->update([
                        'status' => 'in-progress',
                        'notes' => $notes ?? $order->notes,
                    ]);
                    break;

                case 'disconnected':
                    $consumerHasPaid = $order->consumer_id && ConsumerPayment::where('consumer_id', $order->consumer_id)
                        ->whereNotNull('paid_at')->exists();
                    if ($consumerHasPaid) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Cannot mark as disconnected: consumer has already paid.',
                        ], 422);
                    }
                    $order->markAsDisconnected($notes);
                    // Consumer status_code is automatically updated to 'X' in the model method
                    break;

                case 'reconnected':
                    $order->markAsReconnected($notes);
                    // Consumer status_code is automatically updated to 'A' in the model method
                    break;

                case 'cancelled':
                    $order->update([
                        'status' => 'cancelled',
                        'notes' => $notes ?? $order->notes,
                    ]);
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'order' => $order->fresh(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get orders cancelled due to payment (for disconnector notifications: do not disconnect these consumers)
     *
     * GET /api/disconnector/cancelled-due-to-payment?disconnector_id={id}&since={datetime}
     * since: optional, ISO datetime; default last 30 days
     */
    public function getCancelledDueToPayment(Request $request)
    {
        $request->validate([
            'disconnector_id' => 'required|exists:users,id',
            'since' => 'nullable|date',
        ]);

        $disconnectorId = $request->input('disconnector_id');
        $since = $request->input('since')
            ? \Carbon\Carbon::parse($request->input('since'))
            : now()->subDays(30);

        try {
            $orders = DisconnectionOrder::where('disconnector_id', $disconnectorId)
                ->where('status', 'cancelled')
                ->where('notes', 'like', '%' . DisconnectionOrder::CANCELLED_DUE_TO_PAYMENT_NOTE_SUFFIX)
                ->where('updated_at', '>=', $since)
                ->with('consumer')
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function ($order) {
                    return $this->formatAssignmentForMobile($order);
                });

            return response()->json([
                'success' => true,
                'cancelled_due_to_payment' => $orders,
                'count' => $orders->count(),
                'message' => 'Consumers who paid and were removed from your disconnection list.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching notifications: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get statistics for disconnector dashboard
     * 
     * GET /api/disconnector/stats?disconnector_id={id}
     */
    public function getStats(Request $request)
    {
        $request->validate([
            'disconnector_id' => 'required|exists:users,id',
        ]);

        $disconnectorId = $request->input('disconnector_id');

        try {
            $stats = [
                'total_assigned' => DisconnectionOrder::where('disconnector_id', $disconnectorId)->count(),
                'pending' => DisconnectionOrder::where('disconnector_id', $disconnectorId)
                    ->where('status', 'pending')->count(),
                'assigned' => DisconnectionOrder::where('disconnector_id', $disconnectorId)
                    ->where('status', 'assigned')->count(),
                'in_progress' => DisconnectionOrder::where('disconnector_id', $disconnectorId)
                    ->where('status', 'in-progress')->count(),
                'disconnected' => DisconnectionOrder::where('disconnector_id', $disconnectorId)
                    ->where('status', 'disconnected')->count(),
                'reconnected' => DisconnectionOrder::where('disconnector_id', $disconnectorId)
                    ->where('status', 'reconnected')->count(),
                'cancelled' => DisconnectionOrder::where('disconnector_id', $disconnectorId)
                    ->where('status', 'cancelled')->count(),
                'total_outstanding' => DisconnectionOrder::where('disconnector_id', $disconnectorId)
                    ->whereIn('status', ['assigned', 'in-progress'])
                    ->sum('total_outstanding'),
            ];

            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching stats: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format disconnection order for mobile app
     * Includes consumer_has_paid so the app can disable the Disconnect button when the consumer has already paid.
     */
    private function formatAssignmentForMobile($order)
    {
        $consumerHasPaid = false;
        if ($order->consumer_id) {
            $consumerHasPaid = ConsumerPayment::where('consumer_id', $order->consumer_id)
                ->whereNotNull('paid_at')
                ->exists();
        }

        return [
            'id' => $order->id,
            'account_no' => $order->account_no,
            'account_name' => $order->account_name,
            'address' => $order->address,
            'zone_code' => $order->zone_code,
            'meter_number' => $order->meter_number,
            'card_number' => $order->card_number,
            'this_month_arrears' => (float)$order->this_month_arrears,
            'last_month_arrears' => (float)$order->last_month_arrears,
            'others_ar' => (float)$order->others_ar,
            'total_outstanding' => (float)$order->total_outstanding,
            'unpaid_months' => $order->unpaid_months,
            'oldest_unpaid_date' => $order->oldest_unpaid_date?->format('Y-m-d'),
            'latest_unpaid_date' => $order->latest_unpaid_date?->format('Y-m-d'),
            'disconnection_date' => $order->disconnection_date?->format('Y-m-d'),
            'status' => $order->status,
            'notes' => $order->notes,
            'consumer_id' => $order->consumer_id,
            'consumer_has_paid' => $consumerHasPaid,
            'type' => 'disconnection',
            'assignment_type' => 'disconnection',
        ];
    }
}
