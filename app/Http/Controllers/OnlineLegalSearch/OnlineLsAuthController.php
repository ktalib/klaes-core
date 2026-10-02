<?php

namespace App\Http\Controllers\OnlineLegalSearch;

use App\Http\Controllers\Controller;
use App\Models\OnlineLegalSearch\OnlineLsUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OnlineLsAuthController extends Controller
{
    /**
     * Show the public landing page.
     */
    public function showLanding()
    {
        // The landing page is the portal "home" and stays reachable for everyone,
        // including signed-in users who click Home/the logo to start a new search.
        // The shared header already adapts to the authenticated state.

        // Lookup options for the advanced-search District / LGA dropdowns.
        $districtOptions = DB::connection('sqlsrv')->table('districts')
            ->whereNotNull('name')
            ->where('name', '<>', '')
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values();

        $lgaOptions = DB::connection('sqlsrv')->table('lgas')
            ->where('is_active', 1)
            ->whereNotNull('name')
            ->where('name', '<>', '')
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values();

        return view('online_legal_search.landing', compact('districtOptions', 'lgaOptions'));
    }

    /**
     * Show the login form.
     */
    public function showLogin()
    {
        if (Auth::guard('online_ls')->check()) {
            return redirect()->route('ols.dashboard');
        }
        return view('online_legal_search.auth.login');
    }

    /**
     * Handle login attempt.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = OnlineLsUser::where('username', $credentials['username'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => 'These credentials do not match our records.',
            ]);
        }

        if (!$user->isActive()) {
            throw ValidationException::withMessages([
                'username' => 'This account has been suspended. Please contact KLAES support.',
            ]);
        }

        Auth::guard('online_ls')->login($user, $request->boolean('remember'));
        $user->forceFill(['last_login_at' => now()])->save();
        $request->session()->regenerate();

        return redirect()->intended(route('ols.dashboard'));
    }

    /**
     * Show the registration form.
     */
    public function showRegister()
    {
        if (Auth::guard('online_ls')->check()) {
            return redirect()->route('ols.dashboard');
        }
        return view('online_legal_search.auth.register');
    }

    /**
     * Handle registration.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'username'     => ['required', 'string', 'min:3', 'max:100', 'alpha_dash', 'unique:sqlsrv.online_ls_users,username'],
            'email'        => ['required', 'email', 'max:150', 'unique:sqlsrv.online_ls_users,email'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'organization' => ['nullable', 'string', 'max:200'],
            'password'     => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = OnlineLsUser::create([
            'name'             => $validated['name'],
            'username'         => $validated['username'],
            'email'            => $validated['email'],
            'phone'            => $validated['phone'] ?? null,
            'organization'     => $validated['organization'] ?? null,
            'password'         => Hash::make($validated['password']),
            'status'           => 'active',
            'activation_token' => Str::random(60),
        ]);

        // Auto-login after registration
        Auth::guard('online_ls')->login($user);
        $user->forceFill(['last_login_at' => now()])->save();
        $request->session()->regenerate();

        // Honor the intended URL so a user who started a search and was sent here to
        // register is returned to the gated result page to continue (then pay).
        return redirect()->intended(route('ols.dashboard'))
            ->with('status', 'Welcome! Your account has been created successfully. You can now conduct Online Legal Searches.');
    }

    /**
     * Verify email via token.
     */
    public function verifyEmail($token)
    {
        $user = OnlineLsUser::where('activation_token', $token)->first();

        if (!$user) {
            return redirect()->route('ols.login')
                ->withErrors(['token' => 'Invalid or expired verification link.']);
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'activation_token'  => null,
        ])->save();

        return redirect()->route('ols.dashboard')
            ->with('status', 'Your email has been verified successfully.');
    }

    /**
     * Send password reset link.
     */
    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = OnlineLsUser::where('email', $request->email)->first();

        if ($user) {
            $token = Str::random(60);
            $user->forceFill(['activation_token' => $token])->save();

            // TODO: Send actual email with reset link
            // Mail::to($user->email)->send(new OnlineLsPasswordReset($token));
        }

        return back()->with('status', 'If the email exists in our system, a password reset link has been sent.');
    }

    /**
     * Show password reset form.
     */
    public function showResetForm($token)
    {
        $user = OnlineLsUser::where('activation_token', $token)->first();

        if (!$user) {
            return redirect()->route('ols.login')
                ->withErrors(['token' => 'Invalid or expired reset link.']);
        }

        return view('online_legal_search.auth.reset-password', ['token' => $token, 'email' => $user->email]);
    }

    /**
     * Handle password reset.
     */
    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = OnlineLsUser::where('email', $validated['email'])
            ->where('activation_token', $validated['token'])
            ->first();

        if (!$user) {
            return back()->withErrors(['email' => 'Invalid or expired reset link.']);
        }

        $user->forceFill([
            'password'          => Hash::make($validated['password']),
            'activation_token'  => null,
        ])->save();

        Auth::guard('online_ls')->login($user);
        $request->session()->regenerate();

        return redirect()->route('ols.dashboard')
            ->with('status', 'Your password has been reset successfully.');
    }

    /**
     * Logout.
     */
    public function logout(Request $request)
    {
        Auth::guard('online_ls')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('ols.landing');
    }
}
