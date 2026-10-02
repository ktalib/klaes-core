<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ValuationMobileAuthController extends Controller
{
    /**
     * Show the VFC mobile login form.
     */
    public function loginForm()
    {
        if (Auth::check()) {
            return redirect()->route('valuation-compensations.mobile.index');
        }
        return view('valuation_compensations.mobile_login');
    }

    /**
     * Handle VFC mobile login submission.
     */
    public function login(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'password'   => 'required|string',
        ]);

        $identifier = trim($request->input('identifier'));

        // Find by username or email
        $user = User::where('username', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return back()->withInput()->withErrors([
                'identifier' => 'Invalid username or password.',
            ]);
        }

        if ((string) $user->is_active === '0') {
            return back()->withInput()->withErrors([
                'identifier' => 'Your account is disabled. Contact support.',
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->route('valuation-compensations.mobile.index');
    }

    /**
     * Log the user out and return to the VFC mobile login page.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('valuation-compensations.mobile.login');
    }
}
