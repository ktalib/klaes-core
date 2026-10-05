<?php

chdir(dirname(__DIR__, 3));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection('sqlsrv');
$files = ['AG-2026-8', 'AG-2026-9'];
$batch = 'OPB-20261004-1791134680';
$opIds = [222004, 222005];
$praIds = [222004, 222005, 222006, 222007];
$propIds = ['294542', '294543'];
$trackingIds = ['TRK-F199F4C2-5A210', 'TRK-17DF8E59-52FF5'];
$tempFiles = ['TEMP-153369', 'TEMP-153370'];
$catalogue = $db->select("SELECT c.TABLE_SCHEMA, c.TABLE_NAME, c.COLUMN_NAME, c.DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS c JOIN INFORMATION_SCHEMA.TABLES t ON c.TABLE_SCHEMA=t.TABLE_SCHEMA AND c.TABLE_NAME=t.TABLE_NAME WHERE t.TABLE_TYPE='BASE TABLE' AND c.TABLE_SCHEMA='dbo'");
$tables = [];
foreach ($catalogue as $column) $tables[$column->TABLE_NAME][strtolower($column->COLUMN_NAME)] = [$column->COLUMN_NAME, $column->DATA_TYPE];
$matches = [];
foreach ($tables as $table => $columns) {
    $terms = [];
    foreach ($columns as $name => [$actual, $type]) {
        if (in_array($type, ['text','ntext','image','xml','varbinary','binary'], true)) continue;
        if (in_array($name, ['file_number','full_file_number','file_no','fileno','mlsfno','mls_file_no','primary_file_number','resolved_fileno','source_file_no','source_file_number','related_fileno','existing_file_no','mother_file_no','parent_file_no','file_reference','file_reference_number'], true)) $terms[$actual] = $files;
        if (in_array($name, ['temp_fileno','temp_file_no','temporary_file_number'], true)) $terms[$actual] = $tempFiles;
        if (in_array($name, ['prop_id','parent_prop_id','source_prop_id','property_id'], true)) $terms[$actual] = $propIds;
        if (in_array($name, ['tracking_id','file_tracking_id'], true)) $terms[$actual] = $trackingIds;
        if ($name === 'op_batch') $terms[$actual] = [$batch];
        if ($name === 'batch_no') $terms[$actual] = ['BATCH-20261004-1791134808'];
        if ($name === 'op_serial_number') $terms[$actual] = ['46000000','400000'];
        if (in_array($name, ['pra_id','pra_record_id','source_pra_id'], true)) $terms[$actual] = $praIds;
        if (in_array($name, ['file_indexing_id','file_indexings_id','from_file_indexing_id','to_file_indexing_id'], true)) $terms[$actual] = [201430,201431];
        if ($name === 'mls_file_no_id') $terms[$actual] = [49802,49803];
        if ($name === 'oss_application_id') $terms[$actual] = [37603,37604];
        if ($name === 'file_tracker_id') $terms[$actual] = [135505,135506];
        if ($name === 'source_op_id' && isset($columns['source_op_table'])) $terms[$actual] = $opIds;
    }
    foreach ($terms as $column => $values) {
        $type = $columns[strtolower($column)][1];
        if (in_array($type, ['int','bigint','smallint','tinyint','decimal','numeric','float','real','bit'], true)) {
            $terms[$column] = array_values(array_filter($values, 'is_numeric'));
            if (!$terms[$column]) unset($terms[$column]);
        }
    }
    if (!$terms) continue;
    $rows = $db->table($table)->where(function ($q) use ($terms) {
        foreach ($terms as $column => $values) $q->orWhereIn($column, $values);
    })->get()->map(fn ($row) => (array) $row)->all();
    if ($rows) $matches[$table] = $rows;
}
$report = ['files'=>$files,'op_batch'=>$batch,'matches'=>$matches,'columns'=>$tables,
    'serial_control'=>$db->table('mls_serial_control')->where('land_use','AG')->where('year',2026)->first()];
$path = storage_path('app/maintenance/ag-test-cleanup-before.json');
file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE));
foreach ($matches as $table => $rows) {
    echo $table.' '.count($rows).' '.json_encode(array_map(fn($r)=>array_intersect_key($r,array_flip(['id','file_number','mlsFNo','mlsfNo','fileno','file_no','prop_id','tracking_id','source_table','source_record_id','file_indexing_id','scanning_id','op_serial_number','status','created_at'])), $rows)).PHP_EOL;
}
echo 'snapshot '.$path.PHP_EOL;
echo 'AG files '.json_encode($db->table('mls_file_no')->where('land_use','AG')->where('year',2026)->orderByDesc('id')->limit(8)->get(['id','full_file_number','created_at'])).PHP_EOL;
echo 'serial control '.json_encode($report['serial_control']).PHP_EOL;
