<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = new App\Http\Controllers\DcivGenerationController();
$request = Illuminate\Http\Request::create('/dciv/generation/data', 'GET', [
    'draw' => 1,
    'start' => 0,
    'length' => 20,
    'source' => 'all',
    'order' => [ ['column' => 9, 'dir' => 'desc'] ],
]);
$payload = $controller->data($request)->getData(true);
$counts = [];
foreach (($payload['data'] ?? []) as $row) {
    $k = $row['source_table'] ?? 'NULL';
    $counts[$k] = ($counts[$k] ?? 0) + 1;
}
echo 'FIRST_PAGE_COUNTS=' . json_encode($counts) . PHP_EOL;
foreach (array_slice($payload['data'] ?? [], 0, 8) as $idx => $row) {
    echo ($idx+1) . '. ' . ($row['source_table'] ?? 'NULL') . ' | ' . strip_tags($row['source'] ?? '') . PHP_EOL;
}
