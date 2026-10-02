<?php

namespace App\Http\Controllers\Laas;

use App\Http\Controllers\Controller;
use App\Models\Laas\LaasApplicant;
use App\Services\Laas\LaasLoginOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Registration and sign-in for public LAAS applicants (guard: laas).
 *
 * Applicants sign in with the phone number they will be texted on, which keeps
 * the delivery address for every workflow SMS identical to their login and
 * removes a whole class of "I never got the message" support cases.
 *
 * Signing in is TWO steps. The password is checked here without signing anyone
 * in; the account is then held at the code screen until a one-time code sent to
 * that same mobile number (or, on request, to the email on the account) is typed
 * back. Only completeLogin() below ever calls Auth::guard('laas')->login(), so
 * there is exactly one door into an applicant session. See LaasLoginOtpService.
 */
class LaasAuthController extends Controller
{
    public function __construct(private LaasLoginOtpService $otp)
    {
    }

    public function showLogin()
    {
        if (Auth::guard('laas')->check()) {
            return redirect()->route('laas.dashboard');
        }

        return view('laas.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'phone'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $applicant = LaasApplicant::where('phone', $this->normalizePhone($credentials['phone']))
            ->orWhere('email', $credentials['phone'])
            ->first();

        if (!$applicant || !Hash::check($credentials['password'], $applicant->password)) {
            throw ValidationException::withMessages([
                'phone' => 'These credentials do not match our records.',
            ]);
        }

        if (!$applicant->isActive()) {
            throw ValidationException::withMessages([
                'phone' => 'This account has been suspended. Please contact the Lands office.',
            ]);
        }

        $remember = $request->boolean('remember');
        $portal = $this->portalFor($request);

        // Accounts with no reachable contact at all are let through on the
        // password — requiredFor() explains why — and so is every account when
        // the master switch is off.
        if (!$this->otp->requiredFor($applicant)) {
            return $this->completeLogin($request, $applicant, $remember, $portal);
        }

        // NOT signed in yet. begin() regenerates the session id and parks the
        // pending sign-in in it, so the browser holds a guest session until the
        // code comes back.
        $result = $this->otp->begin($request, $applicant, $remember, $portal);

        return redirect()->route('laas.login.otp')
            ->with($result['sent'] ? 'status' : 'error', $result['message']);
    }

    /**
     * The one place an applicant session is actually created.
     *
     * Reached from login() when no code is required, and from
     * LaasLoginOtpController::verify() once one has been typed back. The
     * session id is regenerated again here: begin() gave the guest one id for
     * the pending state, and the signed-in session must not keep it.
     */
    public function completeLogin(Request $request, LaasApplicant $applicant, bool $remember, ?string $portal = null)
    {
        $portal ??= $this->otp->portal($request);

        Auth::guard('laas')->login($applicant, $remember);
        $applicant->forceFill(['last_login_at' => now()])->save();
        $request->session()->regenerate();

        // Marks THIS session as having satisfied the policy, so the backstop
        // middleware does not bounce it straight back to the code screen.
        $this->otp->markPassed($request, $applicant);
        $this->otp->clear($request);

        return redirect()->intended(route($portal === LaasLoginOtpService::PORTAL_ALAES
            ? 'alaes_portal.dashboard'
            : 'laas.dashboard'));
    }

    /**
     * Which front door this request came through.
     *
     * The two portals post to the same action but have their own sign-in
     * screens and their own dashboards, so an applicant who started at
     * /alaes-portal must not be handed back to /laas halfway through.
     */
    private function portalFor(Request $request): string
    {
        return $request->is('alaes-portal*')
            ? LaasLoginOtpService::PORTAL_ALAES
            : LaasLoginOtpService::PORTAL_LAAS;
    }

    public function showRegister()
    {
        if (Auth::guard('laas')->check()) {
            return redirect()->route('laas.dashboard');
        }

        return view('laas.auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:200'],
            'email'    => ['required', 'email', 'max:150'],
            'phone'    => ['required', 'string', 'max:30'],
            'nin'      => ['nullable', 'string', 'max:30'],
            'address'  => ['nullable', 'string', 'max:500'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $phone = $this->normalizePhone($data['phone']);

        if (!$phone) {
            throw ValidationException::withMessages([
                'phone' => 'Enter a valid Nigerian phone number, e.g. 08031234567.',
            ]);
        }

        // Checked by hand rather than with the `unique` rule: both columns live
        // on the sqlsrv connection, and the phone must be compared in its
        // normalised form, not as the applicant typed it.
        if (LaasApplicant::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages(['email' => 'An account already exists for this email address.']);
        }

        if (LaasApplicant::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => 'An account already exists for this phone number.']);
        }

        $applicant = LaasApplicant::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $phone,
            'nin'      => $data['nin'] ?? null,
            'address'  => $data['address'] ?? null,
            'password' => Hash::make($data['password']),
            'status'   => 'active',
        ]);

        // Signed in without a code, on purpose: the account was created by this
        // request, so a code proving the applicant can receive messages would
        // be proving something they have not claimed yet. It does mean the
        // number is only ever proved when they later CHANGE it
        // (LaasProfileController) or sign in again — see the note in
        // LaasLoginOtpService.
        Auth::guard('laas')->login($applicant);
        $request->session()->regenerate();

        // Without this the backstop middleware would bounce a brand-new
        // applicant to the code screen on their very first page.
        $this->otp->markPassed($request, $applicant);

        return redirect()->route('laas.apply.form')
            ->with('status', 'Your account is ready. You can now fill your land allocation application.');
    }

    public function logout(Request $request)
    {
        Auth::guard('laas')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('laas.landing');
    }

    /**
     * Storage form for phone numbers — see LaasApplicant::normalizePhone().
     * A thin pass-through so sign-in, registration and the profile screen can
     * never drift apart on what counts as the same number.
     */
    private function normalizePhone(string $phone): ?string
    {
        return LaasApplicant::normalizePhone($phone);
    }
}
