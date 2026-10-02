<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\LoggedHistory;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Services\ActivityLogService;
use App\Services\LoginOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        if (!file_exists(setup())) {
            header('location:install');
            die;
        }

        $user = \App\Models\User::find(1);
        \App::setLocale($user->lang);

        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     *
     * @param  \App\Http\Requests\Auth\LoginRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(LoginRequest $request)
    {
        $google_recaptcha = getSettingsValByName('google_recaptcha');
        if ($google_recaptcha == 'on') {
            $validation['g-recaptcha-response'] = 'required|captcha';
        } else {
            $validation = [];
        }
        $this->validate($request, $validation);

        // Manually authenticate using username instead of email
        $credentials = [
            'username' => $request->input('username'),
            'password' => $request->input('password'),
        ];

        // Validate credentials without creating a logged-in session. Accounts that
        // require OTP must not become authenticated until their code is verified.
        $provider = Auth::guard('web')->getProvider();
        $loginUser = $provider->retrieveByCredentials($credentials);

        if (!$loginUser || !$provider->validateCredentials($loginUser, $credentials)) {
            return redirect()->route('login')->with('error', __('The provided credentials do not match our records.'));
        }

        if ($loginUser->is_active == 0) {
            return redirect()->route('login')->with('error', __('Your account is temporarily inactive. Please contact your administrator to reactivate your account.'));
        }
        if (empty($loginUser->email_verified_at)) {
            return redirect()->route('login')->with('error', __('Verification required: Please check your email to verify your account before continuing.'));
        }

        $loginOtp = app(LoginOtpService::class);
        if ($loginOtp->requiredFor($loginUser)) {
            $result = $loginOtp->begin($request, $loginUser, $request->filled('remember'));

            return redirect()->route('login.otp')
                ->with($result['sent'] ? 'success' : 'error', $result['message']);
        }

        return $this->completeLogin($request, $loginUser, $request->filled('remember'));
    }

    /** Complete password-only or successfully verified OTP sign-in. */
    public function completeLogin(Request $request, User $loginUser, bool $remember = false)
    {
        $previousSessionId = $request->session()->getId();
        Auth::guard('web')->login($loginUser, $remember);
        $request->session()->regenerate();
        ActivityLogService::syncSessionIdAfterRegeneration($loginUser, $previousSessionId);

        // Prevent the web middleware from challenging this verified session again.
        app(LoginOtpService::class)->markPassed($request, $loginUser);

        userLoggedHistory();

        // Show the welcome card once, on the first page loaded after login
        session(['show_welcome_popup' => true]);

        // Stamps this login. The notification flash toasts key their
        // "already played" marker off this value, so they replay once per
        // login instead of on every page load. Set here (not lazily in the
        // header) so the value is stable for the whole session.
        session(['last_login_time' => time()]);

        // Clear intended URL if it points to a JSON/utility endpoint
        // (e.g. /world-time, /api/*, etc.) to prevent redirecting there after login
        $intended = session()->get('url.intended');
        if ($intended) {
            $path = parse_url($intended, PHP_URL_PATH);
            $blockedPaths = ['/world-time', '/api/', '/get-next-temp-fileno'];
            foreach ($blockedPaths as $blocked) {
                if ($path && str_contains($path, $blocked)) {
                    session()->forget('url.intended');
                    break;
                }
            }
        }

        if ($loginUser->type == 'owner') {

            if ($loginUser->subscription_expire_date != null && date('Y-m-d') > $loginUser->subscription_expire_date) {
                assignSubscription(1);
                return redirect()->intended(RouteServiceProvider::HOME)->with('error', __('Your subscription has ended, and access to premium features is now restricted. To continue using our services without interruption, please renew your plan or upgrade to a higher-tier package.'));
            }
        }
        return redirect()->intended(RouteServiceProvider::HOME);
    }

    /**
     * Destroy an authenticated session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        // Check if this was an auto logout or manual logout with home redirect request
        if ($request->has('auto_logout') || $request->has('redirect_to_home')) {
            return redirect('/')->with('message', 'You have been logged out due to inactivity.');
        }
        
        // Default logout behavior - redirect to home page
        return redirect('/');
    }
}
