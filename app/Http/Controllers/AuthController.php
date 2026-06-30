<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            $role = Auth::user()->role;
            return match ($role) {
                'admin' => redirect('/'),
                'reader' => redirect('/reader/dashboard'),
                'customer' => redirect('/dashboard'),
                default => redirect('/login'),
            };
        }

        return back()->withErrors([
            'email' => 'Invalid credentials provided.',
        ]);
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'extension' => 'nullable|string|max:10',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6|confirmed',
        ]);

        $fullName = $request->last_name . ', ' . $request->first_name;
        if (!empty($request->middle_name)) {
            $fullName .= ' ' . substr($request->middle_name, 0, 1) . '.';
        }
        if (!empty($request->extension)) {
            $fullName .= ' ' . $request->extension;
        }

        $user = User::create([
            'name' => $fullName,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'middle_name' => $request->middle_name,
            'extension' => $request->extension,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'customer',
        ]);

        Auth::login($user);

        return redirect('/dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    // Dashboards
    public function adminDashboard() { return view('dashboards.admin'); }
    public function readerDashboard() { return view('dashboards.reader'); }
    public function customerDashboard() { return view('dashboards.customer'); }
}

