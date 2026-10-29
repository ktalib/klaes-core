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
 * Central Admin (2026-10-26) passes every desk check; a write that passes only through it is
 * written to the audit log as a Central Admin intervention.
 */
class CadastralDeskAccess
{
    private const UNCHECKED = ['cadastral-dept.fmc.log.save', 'cadastral-dept.cases.master-destroy'];

    /** Writes allowed on a file that is waiting to be accepted (CadastralFileTracking). */
    private const BEFORE_ACCEPT = ['cadastral-dept.cases.accept.save', 'cadastral-dept.cases.jump.save', 'cadastral-dept.cases.master-destroy'];

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

        if ($name === 'cadastral-dept.cases.store' || $name === 'cadastral-dept.cases.batch.store') {
            if (!$this->access->canWorkDesk($user, 'registry')) {
                return $this->deny($request, 'Only Registry registers files (role ' . (DeskAccess::deskRoles()['registry'] ?? 'Cad - Registry') . ').');
            }

            return $this->passOn($request, $next, $user, 'registry', null);
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
            $role = DeskAccess::deskRoles()[$desk] ?? DeskAccess::centralAdminRole();

            return $this->deny($request, "{$case->case_ref} is with " . $case->deskShort() . "; acting on it needs the {$role} role.");
        }

        // Sent to a unit and not accepted there yet: nothing is done on the file until it is
        // accepted (user, 2026-10-27). Accept itself, Master Delete and the admin's move-to-step pass.
        if ($case && !in_array($name, self::BEFORE_ACCEPT, true)
            && \App\Services\CadastralDept\CadastralFileTracking::awaiting($case)) {
            return $this->deny($request, "Accept {$case->case_ref} first: it was sent to {$case->deskShort()} and has not been accepted there yet.");
        }

        return $case ? $this->passOn($request, $next, $user, $this->access->actingDesk($case), $case) : $next($request);
    }

    /** Runs the request; when only Central Admin let it through, logs the intervention after it succeeds. */
    private function passOn(Request $request, Closure $next, $user, ?string $desk, ?CadastralCase $case)
    {
        $viaCentral = !$this->access->officerCanWorkDesk($user, $desk) && DeskAccess::isCentralAdmin($user);
        $response = $next($request);

        $failed = $response->getStatusCode() >= 400 || ($request->hasSession() && $request->session()->has('error'));
        if ($viaCentral && !$failed) {
            try {
                app(\App\Services\AuditService::class)->logAction('Cadastral Central Admin Action', 'CadastralCase', $case?->id, null,
                    ['route' => $request->route()?->getName(), 'desk' => $desk, 'outcome' => $request->input('outcome'), 'task' => $request->input('task')],
                    ($case ? "{$case->case_ref} " : '') . 'acted on by Central Admin for ' . ($desk ?? 'the department'));
            } catch (\Throwable $e) {
                report($e);   // the action itself has gone through; a missing audit row must not undo it
            }
        }

        return $response;
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
