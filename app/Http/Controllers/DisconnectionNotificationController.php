<?php

namespace App\Http\Controllers;

use App\Models\DisconnectionOrder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisconnectionNotificationController extends Controller
{
    /**
     * Return newly disconnected consumers for web alerts.
     */
    public function index(Request $request): JsonResponse
    {
        $since = $request->query('since');
        $sinceCarbon = $since ? Carbon::parse($since) : now()->subMinutes(30);

        $orders = DisconnectionOrder::with('disconnector')
            ->where('status', 'disconnected')
            ->whereNotNull('disconnected_at')
            ->where('disconnected_at', '>', $sinceCarbon)
            ->orderBy('disconnected_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'count' => $orders->count(),
            'items' => $orders->map(function (DisconnectionOrder $order) {
                return [
                    'id' => $order->id,
                    'account_no' => $order->account_no,
                    'account_name' => $order->account_name,
                    'disconnected_at' => optional($order->disconnected_at)->toIso8601String(),
                    'disconnected_at_human' => optional($order->disconnected_at)->diffForHumans(),
                    'disconnector_name' => optional($order->disconnector)->name,
                ];
            })->values(),
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
