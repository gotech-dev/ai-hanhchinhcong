<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Show the login form
     */
    public function showLoginForm(Request $request)
    {
        // If intended URL is provided in query string, store it in session
        if ($request->has('intended')) {
            $request->session()->put('url.intended', $request->query('intended'));
        }
        
        return inertia('Auth/Login');
    }

    /**
     * Handle a login request
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
            
            // Only allow regular users (not admin) to login here
            if ($user->is_admin) {
                Auth::logout();
                throw ValidationException::withMessages([
                    'email' => ['Vui lòng đăng nhập qua trang admin: /admin/login'],
                ]);
            }
            
            // Regular user, redirect to chat dashboard
            return redirect()->intended('/chat');
        }

        throw ValidationException::withMessages([
            'email' => ['Email hoặc mật khẩu không đúng.'],
        ]);
    }

    /**
     * Handle a logout request
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

