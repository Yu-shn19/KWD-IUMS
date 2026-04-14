<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Customer;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = Payment::with('customer')->latest()->get();
        
        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,gcash,palawan,bank_transfer',
            'reference_number' => 'nullable|string',
        ]);

        $payment = Payment::create([
            'customer_id' => $request->customer_id,
            'payment_number' => 'PAY-' . Str::random(8),
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'reference_number' => $request->reference_number,
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment created successfully',
            'data' => $payment->load('customer')
        ], 201);
    }

    public function show(Payment $payment)
    {
        return response()->json([
            'success' => true,
            'data' => $payment->load('customer')
        ]);
    }

    public function processMobilePayment(Request $request)
    {
        $request->validate([
            'method' => 'required|in:gcash,palawan',
            'amount' => 'required|numeric|min:0',
        ]);

        // Create a test payment record
        $payment = Payment::create([
            'customer_id' => 1, // Default customer for testing
            'payment_number' => 'PAY-' . Str::random(8),
            'amount' => $request->amount,
            'payment_method' => $request->method,
            'status' => 'pending',
        ]);

        // Simulate payment processing
        $payment->update([
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mobile payment processed successfully',
            'data' => [
                'payment_id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'amount' => $payment->amount,
                'method' => $payment->payment_method,
                'status' => $payment->status,
                'timestamp' => $request->timestamp ?? now()->toISOString(),
            ]
        ]);
    }

    public function update(Request $request, Payment $payment)
    {
        $request->validate([
            'amount' => 'sometimes|numeric|min:0',
            'payment_method' => 'sometimes|in:cash,gcash,palawan,bank_transfer',
            'status' => 'sometimes|in:pending,completed,failed',
        ]);

        $payment->update($request->only(['amount', 'payment_method', 'status']));

        return response()->json([
            'success' => true,
            'message' => 'Payment updated successfully',
            'data' => $payment->load('customer')
        ]);
    }

    public function destroy(Payment $payment)
    {
        $payment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payment deleted successfully'
        ]);
    }
}
