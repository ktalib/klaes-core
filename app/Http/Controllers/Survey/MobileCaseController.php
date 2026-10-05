<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Models\Survey\SurveyBeneficiary;
use App\Models\Survey\SurveyCaseTree;
use App\Models\Survey\SurveyCompCase;
use App\Models\Survey\SurveyProject;
use App\Models\User;
use App\Services\LoginOtpService;
use App\Support\AddressBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Survey Mobile: a dashboard and the Register Compensation Case form, for survey
 * officers in the field.
 *
 * The same register as the desktop screen, laid out for a phone and reached from
 * its own URL with its own login (like the VFC and File Tracker mobile apps).
 * Validation and saving are CaseController's, so a case registered here is
 * indistinguishable from one registered at a desk.
 */
class MobileCaseController extends CaseController
{
    /** Modules that may use the field register; the route gate maps survey-module.* to the first. */
    private const MODULES = ['Survey - Records', 'Supper Admin'];

    public function loginForm()
    {
        if (Auth::check()) {
            return redirect()->route('survey-module.mobile.index');
        }

        return view('survey_module.mobile.login');
    }

    /**
     * Password check, then the same sign-in code the main login asks for. The
     * account is not signed in until any code is verified; once it is, the code
     * screen hands back to the mobile register through url.intended.
     */
    public function login(Request $r)
    {
        $r->validate([
            'identifier' => 'required|string',
            'password'   => 'required|string',
        ]);

        $identifier = trim($r->input('identifier'));
        $user = User::where('username', $identifier)->orWhere('email', $identifier)->first();

        if (! $user || ! Hash::check($r->input('password'), $user->password)) {
            return back()->withInput($r->only('identifier'))
                ->withErrors(['identifier' => 'Invalid username or password.']);
        }
        if ((string) $user->is_active === '0') {
            return back()->withInput($r->only('identifier'))
                ->withErrors(['identifier' => 'Your account is inactive. Contact your administrator.']);
        }
        if (empty($user->email_verified_at)) {
            return back()->withInput($r->only('identifier'))
                ->withErrors(['identifier' => 'Verify your email address before signing in.']);
        }
        if (! $this->mayRegister($user)) {
            return back()->withInput($r->only('identifier'))
                ->withErrors(['identifier' => 'Your account does not have access to the Survey module.']);
        }

        $r->session()->put('url.intended', route('survey-module.mobile.index'));

        $otp = app(LoginOtpService::class);
        if ($otp->requiredFor($user)) {
            $result = $otp->begin($r, $user, $r->boolean('remember'));

            return redirect()->route('login.otp')
                ->with($result['sent'] ? 'success' : 'error', $result['message']);
        }

        return app(AuthenticatedSessionController::class)
            ->completeLogin($r, $user, $r->boolean('remember'));
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('survey-module.mobile.login');
    }

    /**
     * The officer's dashboard: their own figures, the active projects to register
     * under, and their cases with a status filter and search.
     */
    public function index(Request $r)
    {
        $mine = fn () => SurveyCompCase::where('created_by', Auth::id());
        $myIds = fn () => $mine()->select('id');

        $byStatus = $mine()->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');

        $stats = [
            'total'     => (int) $byStatus->sum(),
            'draft'     => (int) ($byStatus[self::STATUS_DRAFT] ?? 0),
            'review'    => (int) ($byStatus[self::STATUS_REVIEW] ?? 0),
            'active'    => (int) ($byStatus['Active'] ?? 0),
            'completed' => (int) ($byStatus['Completed'] ?? 0),
            'rejected'  => (int) ($byStatus['Rejected'] ?? 0),
            'today'     => $mine()->whereDate('created_at', today())->count(),
            'month'     => $mine()->where('created_at', '>=', now()->startOfMonth())->count(),
            'farmers'   => SurveyBeneficiary::whereIn('survey_comp_case_id', $myIds())->count(),
            'cash'      => (float) SurveyCaseTree::whereIn('survey_comp_case_id', $myIds())->sum('line_total'),
            'plots'     => (int) $mine()->where('scheme_type', SurveyProject::SCHEME_LAND)->sum('num_plots'),
            'hectares'  => (float) $mine()->sum('area_ha'),
        ];

        $status = in_array($r->query('status'), self::STATUSES, true) ? $r->query('status') : null;
        $term   = trim((string) $r->query('q', ''));

        $cases = $mine()
            ->with('project')
            ->withCount('beneficiaries')
            ->withSum('trees', 'line_total')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($term !== '', fn ($q) => $q->where(function ($w) use ($term) {
                $w->where('case_ref', 'like', "%$term%")
                  ->orWhere('prop_district', 'like', "%$term%")
                  ->orWhere('prop_district_other', 'like', "%$term%")
                  ->orWhere('prop_lga', 'like', "%$term%")
                  ->orWhereHas('project', fn ($p) => $p->where('name', 'like', "%$term%")
                                                       ->orWhere('project_code', 'like', "%$term%"));
            }))
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $projects = SurveyProject::where('status', 'Active')
            ->withCount('cases')
            ->orderBy('name')
            ->limit(12)
            ->get();

        return view('survey_module.mobile.dashboard', compact('stats', 'cases', 'projects', 'status', 'term'));
    }

    /** The six-step register. ?project= preselects a project from the dashboard. */
    public function create(Request $r)
    {
        $project = ($id = $r->old('survey_project_id', $r->query('project'))) ? SurveyProject::find($id) : null;

        $case = new SurveyCompCase([
            'survey_project_id'   => $project?->id,
            'scheme_type'         => $project?->scheme_type,
            'purpose'             => $project?->purpose,
            'status'              => self::STATUS_DRAFT,
            'case_date'           => now()->toDateString(),
            'survey_officer'      => Auth::user()?->name,
            'prop_district'       => $project?->prop_district,
            'prop_district_other' => $project?->prop_district_other,
            'prop_lga'            => $project?->prop_lga,
            'prop_state'          => $project?->prop_state ?? 'Kano',
        ]);

        return view('survey_module.mobile.case_register', $this->formData($case) + [
            'lgas'   => LookupController::options('lgas'),
            'states' => LookupController::options('states'),
        ]);
    }

    public function store(Request $r)
    {
        // The field register always starts a case as a draft; Save & Submit moves it on.
        $r->merge(['status' => self::STATUS_DRAFT]);

        [$case, $msg, $error] = $this->registerCase($r);

        return redirect()
            ->route('survey-module.mobile.index')
            ->with($error ? 'error' : 'success', $msg)
            ->with('saved_ref', $case->case_ref)
            ->with('saved_id', $case->id);
    }

    /** Read-only case card. Editing stays on the desktop register. */
    public function show(SurveyCompCase $case)
    {
        $case->load(['project', 'beneficiaries', 'trees']);

        return view('survey_module.mobile.case_show', [
            'case'      => $case,
            'location'  => AddressBuilder::propertyLocation(AddressBuilder::fromPrefixed($case, 'prop_')),
            'split'     => $case->isMonetary() ? null : $case->plotSplit(),
            'cash'      => (float) $case->trees->sum('line_total'),
            'canSubmit' => $case->status === self::STATUS_DRAFT && $this->owns($case),
        ]);
    }

    /** Send a complete draft for review, from the case card. */
    public function submit(Request $r, SurveyCompCase $case)
    {
        $back = redirect()->route('survey-module.mobile.cases.show', $case);

        if (! $this->owns($case)) {
            return $back->with('error', 'Only the officer who registered this case can submit it from here.');
        }
        if ($case->status !== self::STATUS_DRAFT) {
            return $back->with('error', "{$case->case_ref} is not a draft, so there is nothing to submit.");
        }
        if ($problem = $this->submissionProblem($case)) {
            return $back->with('error', "{$case->case_ref} cannot be submitted yet: {$problem} Complete it on the desktop register.");
        }

        $case->update(['status' => self::STATUS_REVIEW]);

        return $back->with('success', "{$case->case_ref} submitted. It is now Pending Review.");
    }

    private function owns(SurveyCompCase $case): bool
    {
        return (int) $case->created_by === (int) Auth::id() || Auth::user()->canDo('Supper Admin', 'view');
    }

    private function mayRegister(User $user): bool
    {
        foreach (self::MODULES as $module) {
            if ($user->canDo($module, 'view')) return true;
        }

        return false;
    }
}
