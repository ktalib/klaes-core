<?php
// Read-only installation and rendering smoke test. No login events or writes.
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$schema = Illuminate\Support\Facades\Schema::connection('sqlsrv');
foreach (['revenue_items', 'instrument_fee_mappings', 'instrument_fee_items', 'instrument_workflow_steps', 'instrument_workflow_action_roles', 'instrument_applications', 'billing_types', 'billing_type_items', 'file_schedules', 'schedule_file_formats', 'instrument_number_vaults', 'land_use_rates', 'alaes_logos'] as $table) {
    echo $table . ': ' . ($schema->hasTable($table) ? 'present' : 'MISSING') . PHP_EOL;
}
$db = Illuminate\Support\Facades\DB::connection('sqlsrv');
echo 'Vault counters fingerprint: ' . hash('sha256', json_encode($db->table('instrument_number_vaults')->orderBy('id')->get(['id', 'instrument_type', 'current_volume', 'current_page', 'current_serial']))) . PHP_EOL;
echo 'Grouping rows: ' . $db->selectOne("SELECT SUM(rows) AS total FROM sys.partitions WHERE object_id = OBJECT_ID('dbo.grouping') AND index_id IN (0,1)")->total . PHP_EOL;

if (!in_array('--render', $argv, true)) {
    exit(0);
}
$user = new App\Models\User();
$user->forceFill(['id' => -1, 'type' => 'super admin', 'first_name' => 'Configuration', 'last_name' => 'Smoke Test']);
Illuminate\Support\Facades\Auth::setUser($user);
Illuminate\Support\Facades\View::share('errors', new Illuminate\Support\ViewErrorBag());
$failures = 0;
foreach (['revenue', 'billing', 'departments', 'units', 'user-roles', 'fileno', 'volume', 'pipeline'] as $tab) {
    try {
        $request = Illuminate\Http\Request::create('/system-admin/configurable-entries', 'GET', ['tab' => $tab]);
        $request->setLaravelSession($app['session.store']);
        $request->setUserResolver(fn () => $user);
        $route = $app['router']->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        $app->instance('request', $request);
        $view = $app->call([app(App\Http\Controllers\SystemAdmin\ConfigurableEntriesController::class), 'index'], ['request' => $request]);
        $html = $view->render();
        if (!str_contains($html, 'Configurable Entries')) {
            throw new RuntimeException('Page title missing');
        }
        $sidebar = view('admin.menu.partials.modules.system_admin', ['hasRole' => fn ($role) => true])->render();
        if (preg_match('/@(if|endif|php|endphp)\b|\{\{/', $sidebar)) {
            throw new RuntimeException('Unprocessed Blade code in sidebar');
        }
        echo "PASS $tab (" . strlen($html) . " bytes)" . PHP_EOL;
    } catch (Throwable $e) {
        $failures++;
        echo "FAIL $tab: " . $e->getMessage() . PHP_EOL;
    }
}
exit($failures ? 1 : 0);
