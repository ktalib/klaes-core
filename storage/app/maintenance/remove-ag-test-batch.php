<?php

chdir(dirname(__DIR__, 3));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection('sqlsrv');
$backupPath = storage_path('app/maintenance/ag-test-cleanup-before.json');
$backup = json_decode(file_get_contents($backupPath), true, 512, JSON_THROW_ON_ERROR);
$plan = [
    'customers_staging'=>[172140,172141], 'entities_staging'=>[172022,172023],
    'oss_applications'=>[37603,37604], 'file_tracker'=>[135505,135506],
    'pra'=>[222006,222007,222004,222005], 'file_indexings'=>[201430,201431],
    'fileNumber'=>[181443,181444], 'mls_file_no'=>[49802,49803],
    'PropID_Master'=>[299432,299433],
];
$result = ['backup'=>$backupPath, 'files'=>$backup['files'], 'deleted'=>[], 'serial_before'=>9, 'serial_after'=>7];
$db->transaction(function () use ($db, $backup, $plan, &$result) {
    $control = $db->table('mls_serial_control')->where('id',4)->where('land_use','AG')->where('year',2026)->lockForUpdate()->first();
    if (!$control || (int)$control->last_serial !== 9) throw new RuntimeException('AG counter changed; refusing to rewind.');
    foreach (['mls_file_no'=>'full_file_number', 'fileNumber'=>'mlsfNo', 'file_indexings'=>'file_number'] as $table=>$field) {
        foreach ($db->table($table)->where($field,'like','AG-2026-%')->lockForUpdate()->pluck($field) as $file) {
            if (preg_match('/^AG-2026-(\d+)$/', $file, $m) && (int)$m[1]>9) throw new RuntimeException('A later AG file exists.');
        }
    }
    foreach ($plan as $table=>$ids) {
        $rows = $db->table($table)->whereIn('id',$ids)->lockForUpdate()->get()->map(fn($r)=>(array)$r)->keyBy('id')->all();
        if (count($rows)!==count($ids)) throw new RuntimeException("Unexpected row count in {$table}");
        foreach ($backup['matches'][$table] as $old) {
            if (in_array((int)$old['id'],$ids,true) && ($rows[$old['id']]??null)!=$old) throw new RuntimeException("Record changed in {$table}: {$old['id']}");
        }
    }
    foreach (['scannings','pagetypings','file_trackings','indexed_file_trackers','scan_reassignment_logs'] as $table) {
        if ($db->getSchemaBuilder()->hasTable($table) && $db->getSchemaBuilder()->hasColumn($table,'file_indexing_id')
            && $db->table($table)->whereIn('file_indexing_id',[201430,201431])->exists()) throw new RuntimeException("New dependent records in {$table}");
    }
    foreach ($plan as $table=>$ids) {
        $affected = $db->table($table)->whereIn('id',$ids)->delete();
        if ($affected!==count($ids)) throw new RuntimeException("Delete count mismatch in {$table}");
        $result['deleted'][$table]=$affected;
    }
    if ($db->table('mls_serial_control')->where('id',4)->where('last_serial',9)->update(['last_serial'=>7,'updated_at'=>now()])!==1) throw new RuntimeException('Serial reset failed.');
    $result['deleted_count']=array_sum($result['deleted']);
    file_put_contents(storage_path('app/maintenance/ag-test-cleanup-result.json'),json_encode($result,JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
});
foreach (['mls_fileno_total_count','mls_fileno_stats','dashboard.all_stats','mls_fileno_page_stats_v2',
    'file_numbers_total_v4_New','file_numbers_total_v4_All','file_numbers_total_v4_Captured'] as $key) Illuminate\Support\Facades\Cache::forget($key);
Illuminate\Support\Facades\Cache::forever('file_numbers_datatable_version',(string)microtime(true));
(new App\Http\Controllers\FileNumberController())->clearCache();
Illuminate\Support\Facades\Log::info('Authorized cleanup of test AG commissioning batch', $result);
echo json_encode($result,JSON_PRETTY_PRINT).PHP_EOL;
