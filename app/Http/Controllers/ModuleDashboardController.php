<?php

namespace App\Http\Controllers;

use App\Services\ModuleDashboards;
use Illuminate\Http\Request;

/**
 * A module's Dashboard (KLAES sidebar outline, 2026-11-10): live figure cards and the
 * module's quick links. Opens for the same module names that show the module on the sidebar.
 */
class ModuleDashboardController extends Controller
{
    public function show(Request $request, string $module, ModuleDashboards $dashboards)
    {
        abort_unless(ModuleDashboards::exists($module), 404);
        abort_unless($dashboards->canView($request->user(), $module), 403, 'This dashboard is for the module\'s own users.');

        $d = $dashboards->build($module, $request->user());

        return view('module_dashboards.show', ['d' => $d, 'PageTitle' => $d['title'] . ' Dashboard', 'PageDescription' => $d['description']]);
    }
}
