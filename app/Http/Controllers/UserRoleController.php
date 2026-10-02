<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModules;
use App\Models\Department;
use App\Models\User;
use App\Models\UserRole;
use App\Support\Permissions\ModuleName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * The module registry — user_roles rows, which name the areas of the system a user can be
 * given. Despite the table name these are modules ("Survey - Records", "Print File Labels"),
 * not job roles; a user holds a CSV of their names in users.assign_role.
 *
 * Authorization was previously `Auth::user()->can('manage user')` and friends. Those resolve
 * through User::can(), which answers against Spatie tables this app never populated — so in
 * practice every one of them was false for everybody except a Super Admin, and the screen was
 * Super-Admin-only by accident rather than by decision. It is now gated on the 'User Roles'
 * module like any other screen.
 */
class UserRoleController extends Controller
{
    use AuthorizesModules;

    /** The module this controller administers. */
    private const MODULE = 'User Roles';

    public function index()
    {
        $this->authorizeModule(self::MODULE, 'view');

        $PageTitle = 'User Roles';
        $PageDescription = 'List of all user roles';
        $userRoles = UserRole::with('department')->orderBy('name', 'asc')->get();

        return view('user_role.index', compact('userRoles', 'PageTitle', 'PageDescription'));
    }

    public function create()
    {
        $this->authorizeModule(self::MODULE, 'create');

        $departments = Department::where('is_active', 1)->pluck('name', 'id');

        return view('user_role.create', compact('departments'));
    }

    public function store(Request $request)
    {
        $this->authorizeModule(self::MODULE, 'create');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        UserRole::create([
            'name' => $request->name,
            'guard_name' => 'web',
            'department_id' => $request->department_id,
            'description' => $request->description,
            'level' => $request->level,
            'user_type' => $request->user_type,
            'is_active' => $request->has('is_active') ? 1 : 0,
        ]);

        return redirect()->route('user-roles.index')->with('success', __('User role created successfully'));
    }

    public function edit($id)
    {
        $this->authorizeModule(self::MODULE, 'edit');

        $userRole = UserRole::findOrFail($id);
        $departments = Department::where('is_active', 1)->pluck('name', 'id');

        return view('user_role.edit', compact('userRole', 'departments'));
    }

    public function update(Request $request, $id)
    {
        $this->authorizeModule(self::MODULE, 'edit');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $userRole = UserRole::findOrFail($id);
        $userRole->update([
            'name' => $request->name,
            'department_id' => $request->department_id,
            'description' => $request->description,
            'level' => $request->level,
            'user_type' => $request->user_type,
            'is_active' => $request->has('is_active') ? 1 : 0,
        ]);

        return redirect()->route('user-roles.index')->with('success', __('User role updated successfully'));
    }

    public function destroy($id)
    {
        $this->authorizeModule(self::MODULE, 'delete');

        $userRole = UserRole::findOrFail($id);

        if (($inUse = $this->usersHolding($userRole->name)) > 0) {
            return redirect()->back()->with('error', __(
                'Cannot delete ":name" — :count user(s) still hold it.',
                ['name' => $userRole->name, 'count' => $inUse]
            ));
        }

        $userRole->delete();

        return redirect()->route('user-roles.index')->with('success', __('User role deleted successfully'));
    }

    public function bulkDelete(Request $request)
    {
        $this->authorizeModule(self::MODULE, 'delete');

        $ids = $request->ids;

        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => __('No roles selected.')]);
        }

        $deletedCount = 0;
        $skippedCount = 0;

        foreach ($ids as $id) {
            $userRole = UserRole::find($id);

            if (!$userRole) {
                continue;
            }

            if ($this->usersHolding($userRole->name) > 0) {
                $skippedCount++;
                continue;
            }

            $userRole->delete();
            $deletedCount++;
        }

        $message = __(':deletedCount roles deleted successfully.', ['deletedCount' => $deletedCount]);

        if ($skippedCount > 0) {
            $message .= ' ' . __(':skippedCount roles skipped because they are assigned to users.', ['skippedCount' => $skippedCount]);
        }

        return response()->json(['success' => true, 'message' => $message]);
    }

    /**
     * How many users hold this module.
     *
     * Matches on the NAME, because that is what users.assign_role stores. The previous guard
     * compared the role's numeric id against that column —
     *
     *     assign_role LIKE '%,{$id},%'
     *
     * — and assign_role has never held ids, so the guard never fired once: a module in active
     * use could be deleted, silently stripping it from every user holding it. Every name is
     * normalized before comparison so an en-dash in the registry still matches a hyphen in a
     * user's grant.
     */
    private function usersHolding(string $roleName): int
    {
        $target = ModuleName::normalize($roleName);

        if ($target === '') {
            return 0;
        }

        // Narrow in SQL on a distinctive fragment, then compare exactly in PHP: SQL Server
        // cannot do the dash/spacing folding, and a LIKE alone would count "Survey - Records"
        // as a holder of "Records".
        $fragment = trim(explode('-', $roleName)[0]);
        $fragment = $fragment !== '' ? $fragment : $roleName;

        return User::query()
            ->whereNotNull('assign_role')
            ->where('assign_role', '<>', '')
            ->where('assign_role', 'like', '%' . $fragment . '%')
            ->get(['id', 'assign_role'])
            ->filter(fn ($user) => in_array($target, ModuleName::listFrom($user->assign_role), true))
            ->count();
    }

    /**
     * Roles for a department, for the dependent dropdown on the user modal.
     */
    public function getByDepartment(Request $request)
    {
        try {
            $departmentId = $request->department_id;

            $departmentRoles = UserRole::where('department_id', $departmentId)
                ->where('is_active', 1)
                ->get(['id', 'name', 'description']);

            $generalRoles = UserRole::whereNull('department_id')
                ->where('is_active', 1)
                ->get(['id', 'name', 'description']);

            return response()->json($departmentRoles->merge($generalRoles));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to load roles: ' . $e->getMessage()], 500);
        }
    }
}
