<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\\Contracts\\Console\\Kernel');
$rows = $app->make('db')->connection('sqlsrv')->select('SELECT TOP 5 registry_batch_no, [year], registry FROM grouping');
var_export($rows);
