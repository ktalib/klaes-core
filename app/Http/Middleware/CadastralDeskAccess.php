<?php

namespace App\Http\Middleware;

use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralFieldJacket;
use App\Services\CadastralDept\DeskAccess;
use Closure;
use Illuminate\Http\Request;

/**
 * Cadastral desk roles on the cadastral-dept.* routes (user, 2026-10-15).
 *
 * - A desk page needs that desk's role (or the department role).
 * - A write on a case needs the role of the desk acting on the case's current step.
 * - Registering a file (intake) is Registry's.
 * GET pages other than desks (case file, prints, lists, lookups) are not checked here.
 * Not checked: the FMC QR log (whoever physically moves the file scans it) and Master
 * Delete (gated by its own Super Admin check).
 */
class CadastralDeskAccess
{
    private const UNCHECKED = ['cadastral-dept.fmc.log.save', 'cadastral-dept.cases.master-destroy'];

    public function __construct(private DeskAccess $access)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $route = $request->route();
        $name = (string) ($route?->getName() ?? '');
        $user = $request->user();

        if ($name === 'cadastral-dept.desks.show') {
            $desk = (string) $route->parameter('desk');
            if (isset(DeskAccess::deskRoles()[$desk]) && !$this->access->canWorkDesk($user, $desk)) {
                return $this->deny($request, 'You do not have the ' . DeskAccess::deskRoles()[$desk] . ' role for this desk.');
            }

            return $next($request);
        }

        if ($request->isMethodSafe() || in_array($name, self::UNCHECKED, true)) {
            return $next($request);
        }

        if ($name === 'cadastral-dept.cases.store') {
            if (!$this->access->canWorkDesk($user, 'registry')) {
                return $this->deny($request, 'Only Registry registers files (role ' . (DeskAccess::deskRoles()['registry'] ?? 'Cad - Registry') . ').');
            }

            return $next($request);
        }

        $case = null;
        if ($route->hasParameter('case')) {
            $case = CadastralCase::find((int) $route->parameter('case'));
        } elseif ($route->hasParameter('jacket')) {
            $case = CadastralFieldJacket::find((int) $route->parameter('jacket'))?->case;
        }

        // A missing case is the controller's 404 to give.
        if ($case && !$this->access->canActOn($user, $case)) {
            $desk = $this->access->actingDesk($case);
            $role = DeskAccess::deskRoles()[$desk] ?? DeskAccess::departmentRole();

            return $this->deny($request, "{$case->case_ref} is with " . $case->deskShort() . "; acting on it needs the {$role} role.");
        }

        return $next($request);
    }

    private function deny(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }
        if ($request->isMethodSafe()) {
            abort(403, $message);
        }

        return back()->withInput()->with('error', $message);
    }
}
