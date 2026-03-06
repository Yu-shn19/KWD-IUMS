<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class ApiReaderAuth
{
    /**
     * Handle an incoming request.
     * Validates API token and ensures user is a reader
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        \Log::info('🔐 API Auth Middleware - Request', [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip()
        ]);

        // Get token from Authorization header or query parameter
        $token = $request->bearerToken() ?: $request->input('api_token');

        if (!$token) {
            \Log::warning('❌ API Auth Failed - No Token', [
                'path' => $request->path()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Please login to access this resource.'
            ], 401);
        }

        \Log::info('🔑 Token Received', [
            'token_length' => strlen($token),
            'token_start' => substr($token, 0, 20) . '...'
        ]);

        // Decode token (format: base64(user_id:timestamp))
        try {
            $decoded = base64_decode($token);
            $parts = explode(':', $decoded);
            
            if (count($parts) !== 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token format'
                ], 401);
            }

            $userId = $parts[0];
            $timestamp = $parts[1];

            // Find user
            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 401);
            }

            // Verify user is a reader
            if (strtolower($user->role) !== 'reader') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only readers can access this resource.'
                ], 403);
            }

            // Optional: Check token expiration (24 hours)
            $tokenAge = time() - $timestamp;
            if ($tokenAge > 86400) { // 24 hours
                return response()->json([
                    'success' => false,
                    'message' => 'Token expired. Please login again.'
                ], 401);
            }

            // Attach user to request
            $request->merge(['auth_user' => $user]);

            \Log::info('✅ API Auth Successful', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_role' => $user->role,
                'path' => $request->path()
            ]);

            return $next($request);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid authentication token'
            ], 401);
        }
    }
}
