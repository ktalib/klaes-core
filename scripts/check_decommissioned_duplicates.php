<?php
// Read-only regression check against the configured database; never decommissions a file.
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection('sqlsrv');
$controller = app(App\Http\Controllers\FileDecommissioningController::class);
$assert = function ($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: $message" . PHP_EOL;
};
$archiveMethod = new ReflectionMethod($controller, 'archiveListingQuery');
$archiveMethod->setAccessible(true);
$groups = $db->table('decommissioned_files')
    ->where('decommissioning_reason', 'like', '%grant%')
    ->where(fn ($q) => $q->where('false_decommissioning', '<>', 1)->orWhereNull('false_decommissioning'))
    ->whereNotNull('mls_file_no')->where('mls_file_no', '<>', '')
    ->groupBy('mls_file_no')->havingRaw('COUNT(*) > 1')->pluck('mls_file_no');
foreach ($groups as $number) {
    $assert($archiveMethod->invoke($controller)->where('mls_file_no', $number)->count() === 1, "One listing row for $number");
}
$number = 'RES-2015-7156';
$before = $db->table('decommissioned_files')->where('mls_file_no', $number)->count();
$request = Illuminate\Http\Request::create('/', 'GET', ['draw' => 1, 'start' => 0, 'length' => 10, 'search' => ['value' => $number]]);
$response = $controller->getDecommissionedFilesData($request);
$payload = $response->getData(true);
$assert($response->getStatusCode() === 200, 'DataTables endpoint succeeds');
$assert((int) $payload['recordsFiltered'] === 1 && count($payload['data']) === 1, 'Example has one displayed row and one filtered record');
$stats = $controller->getStatistics()->getData(true);
$assert($stats['success'] && (int) $stats['data']['decommissioned_files'] === (int) $payload['recordsTotal'], 'Summary count matches table total');
$row = (array) $db->table('decommissioned_files')->where('mls_file_no', $number)->first();
$service = app(App\Services\PlotWorkflowService::class);
$eventMethod = new ReflectionMethod($service, 'hasArchivedEvent');
$eventMethod->setAccessible(true);
$assert($eventMethod->invoke($service, $row), 'Existing event detected as a retry');
$row['decommissioning_reason'] = '__read_only_different_event__';
$assert(!$eventMethod->invoke($service, $row), 'Different event is not suppressed');
$assert($db->table('decommissioned_files')->where('mls_file_no', $number)->count() === $before, 'Original archive records preserved');
