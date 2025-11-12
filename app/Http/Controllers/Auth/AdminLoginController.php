<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdminLoginController extends Controller
{
    /**
     * Show the admin login form
     */
    public function showLoginForm()
    {
        return inertia('Auth/AdminLogin');
    }

    /**
     * Handle an admin login request
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();
            
            // Only allow admin users to login here
            if (!$user->is_admin) {
                Auth::logout();
                throw ValidationException::withMessages([
                    'email' => ['Tài khoản này không phải admin. Vui lòng đăng nhập tại /login'],
                ]);
            }
            
            // Admin user, redirect to admin panel
            return redirect()->intended('/admin/assistants');
        }

        throw ValidationException::withMessages([
            'email' => ['Email hoặc mật khẩu không đúng.'],
        ]);
    }

    /**
     * Handle an admin logout request
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login');
    }
}








