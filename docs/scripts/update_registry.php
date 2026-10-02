<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$connection = Illuminate\Support\Facades\DB::connection('sqlsrv');

$connection->statement('SET NOCOUNT ON');

$connection->transaction(function ($conn) {
    echo 'Running registry updates...'.PHP_EOL;

    $affected1 = $conn->update(
        'UPDATE g SET registry = ? FROM grouping AS g WHERE g.year BETWEEN 1981 AND 1991',
        ['Registry 1']
    );
    echo 'Step 1 complete'.PHP_EOL;

    $affected2 = $conn->update(
        'UPDATE g SET registry = ? FROM grouping AS g WHERE g.year BETWEEN 1992 AND 2025',
        ['Registry 2']
    );
    echo 'Step 2 complete'.PHP_EOL;

    $affected3 = $conn->update(
        "UPDATE g SET registry = ? FROM grouping AS g WHERE UPPER(g.awaiting_fileno) LIKE 'CON%'",
        ['Registry 3']
    );
    echo 'Step 3 complete'.PHP_EOL;

    echo PHP_EOL.'Updates applied:'.PHP_EOL;
    echo 'Registry 1 (1981-1991): '.$affected1.' rows'.PHP_EOL;
    echo 'Registry 2 (1992-2025): '.$affected2.' rows'.PHP_EOL;
    echo 'Registry 3 (Awaiting CON%): '.$affected3.' rows'.PHP_EOL;

    $counts = $conn->select('SELECT registry, COUNT(*) AS total FROM grouping GROUP BY registry ORDER BY registry');

    echo PHP_EOL.'Current registry distribution:'.PHP_EOL;
    foreach ($counts as $row) {
        $label = $row->registry ?? 'NULL';
        echo $label.' => '.$row->total.PHP_EOL;
    }
});
