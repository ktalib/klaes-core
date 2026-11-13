<?php

namespace App\Http\Controllers\SurveyorPortal;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyorPortalAccount as Account;
use App\Services\Cadastral\CadastralAddress;
use App\Services\Survey\SurveyorAccounts;
use App\Services\Survey\SurveyorLoginOtpService;
use App\Services\Survey\SurveyorSecurityQuestions;
use App\Services\Survey\SurveySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Registration and sign-in for the Surveyor General's Office portal (guard: surveyor). Modelled on
 * LAAS, with one difference: an account waits for the SG, so registering never signs anyone in.
 */
class AuthController extends Controller
{
    public function __construct(private SurveyorAccounts $accounts, private SurveyorLoginOtpService $otp)
    {
    }

    private function guard()
    {
        return Auth::guard('surveyor');
    }

    /** The portal's front page. Signed-in surveyors see it too, with a way back to their dashboard. */
    public function landing()
    {
        return view('surveyor_portal.landing', ['signedIn' => $this->guard()->check()]);
    }

    /** /login asks "company or surveyor?"; /login/company and /login/surveyor are the two sign-in pages (user, 2026-11-11). */
    public function showLogin(?string $as = null)
    {
        if ($this->guard()->check()) {
            return redirect()->route('surveyor-portal.dashboard');
        }

        return $as === null
            ? view('surveyor_portal.auth.login_choice')
            : view('surveyor_portal.auth.login', ['as' => $as]);
    }

    public function login(Request $r)
    {
        // Username and password (user, 2026-11-09), on the company's or the surveyor's page (2026-11-11).
        $data = $r->validate([
            'username' => 'required|string|max:150',
            'password' => 'required|string',
            'as'       => 'required|in:company,surveyor',
        ], ['username.required' => 'Enter your username.', 'as.*' => 'Choose whether you are signing in as a company or a surveyor.']);

        $username = trim($data['username']);
        $account = $username === '' ? null : Account::whereRaw('LOWER(username) = ?', [mb_strtolower($username)])->first();
        if (! $account || ! Hash::check($data['password'], $account->password)) {
            throw ValidationException::withMessages(['username' => 'The username or password is wrong.']);
        }

        // Each page lets in its own kind of login only; said once the password is right, so it never tells a
        // stranger which usernames exist.
        if ($data['as'] === 'company' && ! $account->isCompany()) {
            throw ValidationException::withMessages(['username' => 'This is a surveyor\'s login. Use "Sign in as a surveyor".']);
        }
        if ($data['as'] === 'surveyor' && $account->isCompany()) {
            throw ValidationException::withMessages(['username' => 'This is a company\'s login. Use "Sign in as a company".']);
        }

        if (! $account->isActive()) {
            throw ValidationException::withMessages(['username' => match ($account->status) {
                Account::PENDING  => "Your registration {$account->registration_id} is waiting for the Surveyor General. We will text you when you can sign in.",
                Account::REJECTED => 'Your registration was not approved. Please contact the Office of the Surveyor General.',
                default           => 'This account is not active. Please contact the Office of the Surveyor General.',
            }]);
        }

        // The password is right; a sign-in code comes next (user, 2026-11-10). Not signed in until it is typed.
        if ($this->otp->requiredFor($account)) {
            $result = $this->otp->begin($r, $account, $r->boolean('remember'));

            return redirect()->route('surveyor-portal.login.otp')->with($result['sent'] ? 'status' : 'error', $result['message']);
        }
        if ($this->otp->enabled()) {
            // Nowhere to send a code: the password alone, rather than an account nobody can ever open again.
            \Illuminate\Support\Facades\Log::warning('MLPP-SG sign-in without a code: no usable phone or email', ['account_id' => $account->id]);
        }

        return $this->completeLogin($r, $account, $r->boolean('remember'));
    }

    /**
     * Sign the surveyor in: after the password when no code is needed, otherwise from
     * LoginOtpController (code typed) or SecurityQuestionsController (questions answered).
     */
    public function completeLogin(Request $r, Account $account, bool $remember)
    {
        $this->guard()->login($account, $remember);
        $account->forceFill(['last_login_at' => now()])->save();
        $r->session()->regenerate();
        $this->otp->markPassed($r, $account);
        // From this sign-in on, the security questions card holds the portal until answers are set.
        $r->session()->put(SurveyorSecurityQuestions::CHECK_KEY, true);
        $r->session()->forget(SurveyorSecurityQuestions::DONE_KEY);

        return redirect()->intended(route('surveyor-portal.dashboard'));
    }

    public function showRegister(Request $request)
    {
        if ($this->guard()->check()) {
            return redirect()->route('surveyor-portal.dashboard');
        }

        if ($request->query('type') !== null) {
            abort_unless($request->query('type') === 'Private', 404);
        }

        $company = $request->query('company');
        if ($company === 'no') {
            return redirect()->route('surveyor-portal.company.register');
        }
        if ($company !== 'yes') {
            return view('surveyor_portal.auth.register_choice');
        }

        return view('surveyor_portal.auth.register', ['companies' => SurveySettings::companies()]);
    }

    public function register(Request $r)
    {
        $account = $this->registerSurveyor($r, null);

        return redirect()->route('surveyor-portal.registered')->with('registration_id', $account->registration_id);
    }

    /**
     * Our Surveyors > Add Surveyor (user, 2026-11-13): a company signs a surveyor up with the same form. The company
     * is its own and cannot be changed; the account waits for the SG's approval like any registration.
     */
    public function companyCreateSurveyor()
    {
        $me = $this->companyAccount();
        $company = DB::connection('sqlsrv')->table('survey_companies')->where('id', $me->company_id)->first();
        abort_unless($company, 404);

        return view('surveyor_portal.company.surveyor_create', ['company' => $company]);
    }

    public function companyStoreSurveyor(Request $r)
    {
        $me = $this->companyAccount();
        $account = $this->registerSurveyor($r, (int) $me->company_id);

        return redirect()->route('surveyor-portal.company.surveyors')->with('success',
            "{$account->name} is registered under your company (Registration ID {$account->registration_id}). The Surveyor General approves the account; "
            . 'the surveyor is texted when they can sign in.');
    }

    private function companyAccount(): Account
    {
        $me = $this->guard()->user();
        abort_unless($me && $me->isCompany() && $me->company_id, 403);

        return $me;
    }

    /** The surveyor registration, from the public form or a company's Add Surveyor ($lockedCompanyId). */
    private function registerSurveyor(Request $r, ?int $lockedCompanyId): Account
    {
        if ($lockedCompanyId) {
            $r->merge(['company_choice' => (string) $lockedCompanyId]);
        }
        $data = $r->validate([
            'company_choice' => ['required', 'string', 'max:30'],
            'name'           => 'required|string|max:255',
            'phone'          => 'required|string|max:30',
            'email'          => 'required|email|max:150',
            'surcon_number'  => 'nullable|string|max:50',
            // Signs in with this (user, 2026-11-09). Letters, digits and . _ - @ so an email works too.
            'username'       => ['required', 'string', 'min:3', 'max:150', 'regex:/^[A-Za-z0-9._@-]+$/'],
            'password'       => 'required|string|min:8|confirmed',
            // Passport photograph (user, 2026-11-05). 2 MB, as at MLPP commissioning.
            'passport'       => 'required|file|image|mimes:jpg,jpeg,png|max:2048',
            // The address builder, one value each: the Cadastral directory's rules, except that District is
            // optional here (user, 2026-11-05).
            'addr_district'  => 'nullable|string|max:255',
        ] + CadastralAddress::rules('addr_'), CadastralAddress::messages('addr_') + [
            'company_choice.required' => 'Choose your approved surveying company.',
            'username.required' => 'Choose a username.',
            'username.min' => 'The username must be at least 3 characters.',
            'username.regex' => 'Use only letters, numbers and . _ - @ in the username (no spaces).',
            'passport.required' => 'Upload your passport photograph.',
            'passport.image' => 'The passport photograph must be a JPG or PNG picture.',
            'passport.mimes' => 'The passport photograph must be a JPG or PNG picture.',
            'passport.max' => 'The passport photograph must be 2 MB or smaller.',
        ]);
        if (! CadastralAddress::lgaBelongsToState($data['addr_lga'] ?? null, $data['addr_state'] ?? null)) {
            throw ValidationException::withMessages([
                'addr_lga' => 'Choose an LGA that belongs to the selected state.',
            ]);
        }

        $phone = Account::normalizePhone($data['phone']);
        if (! $phone) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid Nigerian phone number, e.g. 08031234567.']);
        }
        $email = mb_strtolower(trim($data['email']));
        if (Account::surveyors()->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => 'An account already exists for this phone number.']);
        }
        if (Account::surveyors()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'An account already exists for this email address.']);
        }
        $username = trim($data['username']);
        if (Account::whereRaw('LOWER(username) = ?', [mb_strtolower($username)])->exists()) {
            throw ValidationException::withMessages(['username' => 'That username is taken. Choose another.']);
        }

        $data['surveyor_type'] = 'Private';
        [$companyId, $companyName, $companyRc] = $this->company($data);

        // Kept on the private disk, never under public/: only the SG's page streams it.
        $file = $r->file('passport');
        $passport = $file ? $file->storeAs('surveyor_portal/passports', \Illuminate\Support\Str::uuid() . '.' . strtolower($file->extension() ?: 'jpg'), 'local') : null;

        $address = array_intersect_key(CadastralAddress::normalise($data, 'addr_'), array_flip(Account::addressColumns('addr_')));

        try {
            $account = $this->accounts->register($address + [
                'passport_path' => $passport,
                'surveyor_type' => $data['surveyor_type'],
                'company_id'    => $companyId,
                'company_name'  => $companyName,
                'company_rc_no' => $companyRc,
                'name'          => preg_replace('/\s+/', ' ', trim($data['name'])),
                'phone'         => $phone,
                'email'         => $email,
                'username'      => $username,
                'surcon_number' => trim((string) ($data['surcon_number'] ?? '')) ?: null,
                'password'      => $data['password'],
            ]);
        } catch (\Throwable $e) {
            if ($passport) \Illuminate\Support\Facades\Storage::disk('local')->delete($passport);   // no account, no photo left behind
            throw $e;
        }

        return $account;
    }

    public function registered()
    {
        $id = session('registration_id');

        return $id ? view('surveyor_portal.auth.registered', ['registrationId' => $id]) : redirect()->route('surveyor-portal.login');
    }

    private function company(array $data): array
    {
        if ($data['surveyor_type'] !== 'Private') return [null, null, null];

        $choice = (string) ($data['company_choice'] ?? '');
        if ($choice === '') {
            throw ValidationException::withMessages(['company_choice' => 'Choose your company.']);
        }

        $query = DB::connection('sqlsrv')->table('survey_companies')->where('id', $choice)->where('is_active', 1);
        if (Schema::connection('sqlsrv')->hasColumn('survey_companies', 'status')) {
            $query->where(fn ($q) => $q->where('status', \App\Services\Survey\SurveyCompanyRegistry::APPROVED)->orWhereNull('status'));
        }
        $company = ctype_digit($choice) ? $query->first() : null;
        if (! $company) {
            throw ValidationException::withMessages(['company_choice' => 'Choose an approved company from the list.']);
        }

        return [$company->id, $company->name, $company->company_rc_no ?? null];
    }

    /** Ends this portal's sign-in only: a staff session in the same browser shares the cookie and stays. */
    public function logout(Request $r)
    {
        $this->guard()->logout();
        $r->session()->forget([SurveyorLoginOtpService::PASSED_KEY, SurveyorSecurityQuestions::CHECK_KEY, SurveyorSecurityQuestions::DONE_KEY]);
        $r->session()->regenerateToken();

        return redirect()->route('surveyor-portal.login');
    }

    public function showForgot()
    {
        return view('surveyor_portal.auth.forgot');
    }

    public function sendCode(Request $r)
    {
        $data = $r->validate(['login' => 'required|string|max:150'], ['login.required' => 'Enter your phone number or email.']);
        $account = $this->find($data['login']);
        if ($account && $account->isActive()) {
            $this->accounts->sendResetCode($account);
        }
        session(['surveyor_reset_login' => $data['login']]);

        // The same answer either way, so this page cannot be used to find out who has an account.
        return redirect()->route('surveyor-portal.password.reset')->with('status', 'If that account exists, a reset code has been sent to its phone.');
    }

    public function showReset()
    {
        return session('surveyor_reset_login') ? view('surveyor_portal.auth.reset') : redirect()->route('surveyor-portal.password.forgot');
    }

    public function reset(Request $r)
    {
        $data = $r->validate(['code' => 'required|string|max:10', 'password' => 'required|string|min:8|confirmed']);
        $account = $this->find((string) session('surveyor_reset_login'));
        if (! $account) {
            throw ValidationException::withMessages(['code' => 'That code is wrong or has expired. Ask for a new one.']);
        }
        $this->accounts->resetPassword($account, trim($data['code']), $data['password']);
        session()->forget('surveyor_reset_login');

        return redirect()->route('surveyor-portal.login')->with('status', 'Your password has been changed. Please sign in.');
    }

    /**
     * By Surveyor ID, then email, then phone, then username. A company login shares its phone and email with its
     * principal surveyor, so those find the surveyor; the company resets by its username (user, 2026-11-10).
     */
    private function find(string $login): ?Account
    {
        $login = trim($login);
        if ($login === '') return null;

        if ($found = Account::whereRaw('UPPER(surveyor_id_no) = ?', [mb_strtoupper($login)])->first()) return $found;
        $byUsername = fn () => Account::whereRaw('LOWER(username) = ?', [mb_strtolower($login)])->first();
        if (str_contains($login, '@')) {
            return Account::where('email', mb_strtolower($login))->orderByRaw("CASE WHEN surveyor_type = 'Company' THEN 1 ELSE 0 END")->first() ?: $byUsername();
        }

        $phone = Account::normalizePhone($login);
        $found = $phone ? Account::where('phone', $phone)->orderByRaw("CASE WHEN surveyor_type = 'Company' THEN 1 ELSE 0 END")->first() : null;

        return $found ?: $byUsername();
    }
}
