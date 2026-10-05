<?php

chdir(dirname(__DIR__, 3));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection('sqlsrv');
$files = ['COM-2026-1', 'COM-2026-2', 'COM-2026-3', 'COM-2026-4', 'COM-2026-5', 'COM-2026-6', 'COM-2026-7', 'COM-2026-27', 'COM-2026-28', 'COM-2026-34', 'COM-2026-59', 'COM-2026-60', 'COM-2026-362', 'COM-2026-363'];
$mls = $db->table('mls_file_no')->whereIn('full_file_number',$files)->get();
$pra = $db->table('pra')->whereIn('mlsFNo',$files)->get();
$indexings = $db->table('file_indexings')->whereIn('file_number',$files)->get();
$trackers = $db->table('file_tracker')->whereIn('file_number',$files)->get();
$batch = $pra->pluck('op_batch')->filter()->unique()->values()->all();
$opIds = $mls->pluck('source_pra_id')->filter()->values()->all();
$praIds = $db->table('pra')->whereIn('prop_id',$mls->pluck('prop_id')->all())->pluck('id')->all();
$propIds = $mls->pluck('prop_id')->filter()->values()->all();
$trackingIds = $trackers->pluck('tracking_id')->merge($mls->pluck('tracking_id'))->filter()->unique()->values()->all();
$tempFiles = $pra->pluck('temp_fileno')->filter()->unique()->values()->all();
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
        if ($name === 'op_batch') $terms[$actual] = $batch;
        if ($name === 'batch_no') $terms[$actual] = $mls->pluck('batch_no')->filter()->unique()->values()->all();
        
        if (in_array($name, ['pra_id','pra_record_id','source_pra_id'], true)) $terms[$actual] = $praIds;
        if (in_array($name, ['file_indexing_id','file_indexings_id','from_file_indexing_id','to_file_indexing_id'], true)) $terms[$actual] = $indexings->pluck('id')->all();
        if ($name === 'mls_file_no_id') $terms[$actual] = $mls->pluck('id')->all();
        if ($name === 'oss_application_id') $terms[$actual] = $db->table('oss_applications')->whereIn('file_no',$files)->pluck('id')->all();
        if ($name === 'file_tracker_id') $terms[$actual] = $trackers->pluck('id')->all();
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
    'serial_control'=>$db->table('mls_serial_control')->where('land_use','COM')->where('year',2026)->first()];
$path = storage_path('app/maintenance/com-test-batch-before.json');
file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE));
foreach ($matches as $table => $rows) {
    echo $table.' '.count($rows).' '.json_encode(array_map(fn($r)=>array_intersect_key($r,array_flip(['id','file_number','mlsFNo','mlsfNo','fileno','file_no','prop_id','tracking_id','source_table','source_record_id','file_indexing_id','scanning_id','op_serial_number','status','created_at'])), $rows)).PHP_EOL;
}
echo 'snapshot '.$path.PHP_EOL;
echo 'COM files '.json_encode($db->table('mls_file_no')->where('land_use','COM')->where('year',2026)->orderByDesc('id')->limit(8)->get(['id','full_file_number','created_at'])).PHP_EOL;
echo 'serial control '.json_encode($report['serial_control']).PHP_EOL;
