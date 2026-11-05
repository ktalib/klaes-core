<?php

namespace App\Http\Controllers\SurveyorPortal;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyorPortalAccount as Account;
use App\Services\Survey\SurveyorAccounts;
use App\Services\Survey\SurveySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Registration and sign-in for the Surveyor General's Office portal (guard: surveyor). Modelled on
 * LAAS, with one difference: an account waits for the SG, so registering never signs anyone in.
 */
class AuthController extends Controller
{
    public function __construct(private SurveyorAccounts $accounts)
    {
    }

    private function guard()
    {
        return Auth::guard('surveyor');
    }

    public function showLogin()
    {
        return $this->guard()->check() ? redirect()->route('surveyor-portal.dashboard') : view('surveyor_portal.auth.login');
    }

    public function login(Request $r)
    {
        $data = $r->validate(['login' => 'required|string|max:150', 'password' => 'required|string'],
            ['login.required' => 'Enter your phone number, email or Surveyor ID.']);

        $account = $this->find($data['login']);
        if (! $account || ! Hash::check($data['password'], $account->password)) {
            throw ValidationException::withMessages(['login' => 'These details do not match our records.']);
        }

        if (! $account->isActive()) {
            throw ValidationException::withMessages(['login' => match ($account->status) {
                Account::PENDING  => "Your registration {$account->registration_id} is waiting for the Surveyor General. We will text you when you can sign in.",
                Account::REJECTED => 'Your registration was not approved. Please contact the Office of the Surveyor General.',
                default           => 'This account is not active. Please contact the Office of the Surveyor General.',
            }]);
        }

        $this->guard()->login($account, $r->boolean('remember'));
        $account->forceFill(['last_login_at' => now()])->save();
        $r->session()->regenerate();

        return redirect()->intended(route('surveyor-portal.dashboard'));
    }

    public function showRegister()
    {
        return $this->guard()->check()
            ? redirect()->route('surveyor-portal.dashboard')
            : view('surveyor_portal.auth.register', ['companies' => SurveySettings::companies()]);
    }

    public function register(Request $r)
    {
        $data = $r->validate([
            'surveyor_type'  => ['required', Rule::in(['Government', 'Private'])],
            'company_choice' => 'nullable|string|max:30',
            'company_other'  => 'nullable|string|max:255',
            'name'           => 'required|string|max:255',
            'phone'          => 'required|string|max:30',
            'email'          => 'required|email|max:150',
            'surcon_number'  => 'nullable|string|max:50',
            'password'       => 'required|string|min:8|confirmed',
        ], ['surveyor_type.required' => 'Choose Public or Private.']);

        $phone = Account::normalizePhone($data['phone']);
        if (! $phone) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid Nigerian phone number, e.g. 08031234567.']);
        }
        $email = mb_strtolower(trim($data['email']));
        if (Account::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => 'An account already exists for this phone number.']);
        }
        if (Account::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'An account already exists for this email address.']);
        }

        [$companyId, $companyName] = $this->company($data);

        $account = $this->accounts->register([
            'surveyor_type' => $data['surveyor_type'],
            'company_id'    => $companyId,
            'company_name'  => $companyName,
            'name'          => preg_replace('/\s+/', ' ', trim($data['name'])),
            'phone'         => $phone,
            'email'         => $email,
            'surcon_number' => trim((string) ($data['surcon_number'] ?? '')) ?: null,
            'password'      => $data['password'],
        ]);

        return redirect()->route('surveyor-portal.registered')->with('registration_id', $account->registration_id);
    }

    public function registered()
    {
        $id = session('registration_id');

        return $id ? view('surveyor_portal.auth.registered', ['registrationId' => $id]) : redirect()->route('surveyor-portal.login');
    }

    /** A Private surveyor names a company: a listed one, or a typed one the SG adds when issuing. */
    private function company(array $data): array
    {
        if ($data['surveyor_type'] !== 'Private') return [null, null];

        $choice = (string) ($data['company_choice'] ?? '');
        if ($choice === '') {
            throw ValidationException::withMessages(['company_choice' => 'Choose your company.']);
        }
        if ($choice === 'other') {
            $name = preg_replace('/\s+/', ' ', trim((string) ($data['company_other'] ?? '')));
            if ($name === '') throw ValidationException::withMessages(['company_other' => "Type your company's name."]);
            $listed = DB::connection('sqlsrv')->table('survey_companies')->whereRaw('UPPER(name) = ?', [mb_strtoupper($name)])->first(['id', 'name']);

            return $listed ? [$listed->id, $listed->name] : [null, mb_substr($name, 0, 255)];
        }

        $company = ctype_digit($choice) ? DB::connection('sqlsrv')->table('survey_companies')->find($choice) : null;
        if (! $company) throw ValidationException::withMessages(['company_choice' => 'Choose a company from the list, or Other.']);

        return [$company->id, $company->name];
    }

    /** Ends this portal's sign-in only: a staff session in the same browser shares the cookie and stays. */
    public function logout(Request $r)
    {
        $this->guard()->logout();
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

    /** By Surveyor ID, then email, then phone. */
    private function find(string $login): ?Account
    {
        $login = trim($login);
        if ($login === '') return null;

        if ($found = Account::whereRaw('UPPER(surveyor_id_no) = ?', [mb_strtoupper($login)])->first()) return $found;
        if (str_contains($login, '@')) return Account::where('email', mb_strtolower($login))->first();

        $phone = Account::normalizePhone($login);

        return $phone ? Account::where('phone', $phone)->first() : null;
    }
}
