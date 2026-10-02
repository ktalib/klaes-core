<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Models\RequestPurpose;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MobileController extends Controller
{
    /**
     * Show the mobile login form.
     */
    public function loginForm()
    {
        if (Auth::check()) {
            return redirect()->route('mobile.dashboard');
        }
        return view('mobile.login');
    }

    /**
     * Handle login form submission using standard web session auth.
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

        return redirect()->route('mobile.dashboard');
    }

    /**
     * Render the main dashboard Blade view, injecting data needed for dropdowns.
     */
    public function dashboard()
    {
        $offices = Office::where('is_active', true)
            ->orderBy('office_name')
            ->get(['id', 'office_code', 'office_name', 'office_abbreviation', 'department']);

        $officers = User::where('is_active', 1)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'username', 'department_id', 'profile', 'passport_photo_path']);

        // Origin registries (+ short codes) for the File Search request dropdown —
        // mirrors the Registry selector on Create File Tracker.
        $registries = DB::connection('sqlsrv')->table('physical_registries')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['name', 'registry_code']);

        // Requester cascade for the File Search request (mirrors Quick Search):
        // Requester Office (Departments) → Requester Office → Requester Officer.
        $departments = $offices->pluck('department')->filter()->unique()->sort()->values();

        // Map department NAME → id (officers are linked to departments.id) so the
        // officer dropdown can be filtered by the chosen department.
        $departmentIds = DB::connection('sqlsrv')
            ->table('departments')
            ->select('id', 'name')
            ->get();

        $user = Auth::user();
        // Super Admins included — mirrors MobileFileSearchController::isScbMonitor(),
        // which already serves them the File Requests endpoints (unscoped).
        $isScbMonitor = $user->isSuperAdmin() || ($user->fr_permissions ?? '') === 'SCB';
        // OFS (Office Priority Search): a ranked officer (users.rank matches the
        // hierarchy) who may raise prioritised File/Blind Requests from File Search.
        $isOfs = $user->isOfs();

        // Request Purpose + its default turnaround — captured on the OFS "Send File
        // Search Request" form so it can carry all the way through to Create File
        // Tracker once the file is logged, same as the web Quick Search flow.
        $requestPurposes = RequestPurpose::active()->orderBy('name')->get(['id', 'name', 'turnaround_days']);

        return view('mobile.dashboard', compact('offices', 'officers', 'registries', 'departments', 'departmentIds', 'user', 'isScbMonitor', 'isOfs', 'requestPurposes'));
    }

    /**
     * Render the Digital File Request mobile page.
     */
    public function digitalRequest()
    {
        $user = Auth::user();
        // Super Admins can skip the mandatory destination-office selection.
        $isSuperAdmin = $user->isSuperAdmin();
        // Role flags drive the bottom navigation (mirrors the dashboard tab bar).
        $isScbMonitor = $isSuperAdmin || ($user->fr_permissions ?? '') === 'SCB';
        $isOfs        = $user->isOfs();

        return view('mobile.digital_request', compact('user', 'isSuperAdmin', 'isScbMonitor', 'isOfs'));
    }

    /**
     * Log the user out and return to the mobile login page.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('mobile.login');
    }
}
