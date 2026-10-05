<?php
chdir(dirname(__DIR__,3));require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::connection('sqlsrv');
$backupPath=storage_path('app/maintenance/com-test-batch-before.json');
$backup=json_decode(file_get_contents($backupPath),true,512,JSON_THROW_ON_ERROR);
$plan=json_decode('{"customers_staging": [172145, 172146, 172152, 172153, 172147, 172154, 172157, 172158, 172148, 172149, 172155, 172150, 172156, 172151], "entities_staging": [172027, 172028, 172029, 172030, 172031, 172032, 172033, 172034, 172035, 172036, 172037, 172038, 172039, 172040], "oss_applications": [37608, 37609, 37610, 37611, 37612, 37613, 37614, 37615, 37616, 37617, 37618, 37619, 37620, 37621], "file_tracker": [135510, 135511, 135512, 135513, 135514, 135515, 135516, 135517, 135518, 135519, 135520, 135521, 135522, 135523], "pra": [222014, 222015, 222016, 222017, 222018, 222019, 222020, 222021, 222022, 222023, 222024, 222025, 222026, 222027, 222028, 222029, 222030, 222031, 222032, 222033, 222034, 222035, 222036, 222037, 222038, 222039, 222040, 222041], "file_indexings": [201435, 201436, 201437, 201438, 201439, 201440, 201441, 201442, 201443, 201444, 201445, 201446, 201447, 201448], "fileNumber": [181448, 181449, 181450, 181451, 181452, 181453, 181454, 181455, 181456, 181457, 181458, 181459, 181460, 181461], "mls_file_no": [49807, 49808, 49809, 49810, 49811, 49812, 49813, 49814, 49815, 49816, 49817, 49818, 49819, 49820], "PropID_Master": [299437, 299438, 299439, 299440, 299441, 299442, 299443, 299444, 299445, 299446, 299447, 299448, 299449, 299450]}',true,512,JSON_THROW_ON_ERROR);
$result=['files'=>$backup['files'],'backup'=>$backupPath,'deleted'=>[]];
$db->transaction(function()use($db,$backup,$plan,&$result){
 foreach($backup['matches'] as $table=>$oldRows){
  $ids=array_column($oldRows,'id');
  $current=$db->table($table)->whereIn('id',$ids)->lockForUpdate()->get()->map(fn($r)=>(array)$r)->keyBy('id')->all();
  foreach($oldRows as $row)if(($current[$row['id']]??null)!=$row)throw new RuntimeException("Row changed in {$table}: {$row['id']}");
 }
 if($db->table('mls_file_no')->where('batch_no','BATCH-20261004-1791138813')->count()!==14)throw new RuntimeException('Batch changed');
 if($db->table('pra')->where('op_batch','OPB-20261004-1791138515')->count()!==28)throw new RuntimeException('OP batch changed');
 foreach(['scannings','pagetypings','file_trackings','indexed_file_trackers','scan_reassignment_logs']as$table){
  if($db->getSchemaBuilder()->hasTable($table)&&$db->getSchemaBuilder()->hasColumn($table,'file_indexing_id')&&$db->table($table)->whereIn('file_indexing_id',$plan['file_indexings'])->exists())throw new RuntimeException("New child records in {$table}");
 }
 foreach($plan as$table=>$ids){
  $deleted=$db->table($table)->whereIn('id',$ids)->delete();
  if($deleted!==count($ids))throw new RuntimeException("Delete count mismatch: {$table}");
  $result['deleted'][$table]=$deleted;
 }
});
$result['deleted_count']=array_sum($result['deleted']);
$verified=[];
foreach($plan as$table=>$ids){$verified[$table]=$db->table($table)->whereIn('id',$ids)->count();if($verified[$table]!==0)throw new RuntimeException("Remaining test rows: {$table}");}
foreach($backup['matches']as$table=>$oldRows){
 foreach($oldRows as$row){
  if(in_array((int)$row['id'],$plan[$table]??[],true))continue;
  $remaining=$db->table($table)->where('id',$row['id'])->first();
  if(!$remaining||(array)$remaining!=$row)throw new RuntimeException("Older row changed: {$table}:{$row['id']}");
 }
}
$verified['file_tracking_by_number']=$db->table('file_tracker')->whereIn('file_number',$backup['files'])->count();
$verified['saved_op_batch']=$db->table('pra')->where('op_batch','OPB-20261004-1791138515')->count();
$verified['mls_batch']=$db->table('mls_file_no')->where('batch_no','BATCH-20261004-1791138813')->count();
$verified['older_rows_preserved']=true;
foreach(['mls_fileno_total_count','mls_fileno_stats','dashboard.all_stats','mls_fileno_page_stats_v2','file_numbers_total_v4_New','file_numbers_total_v4_All','file_numbers_total_v4_Captured']as$key)Illuminate\Support\Facades\Cache::forget($key);
Illuminate\Support\Facades\Cache::forever('file_numbers_datatable_version',(string)microtime(true));
(new App\Http\Controllers\FileNumberController())->clearCache();
Illuminate\Support\Facades\Log::info('Authorized cleanup of commercial OP test batch',$result);
file_put_contents(storage_path('app/maintenance/com-test-batch-result.json'),json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
file_put_contents(storage_path('app/maintenance/com-test-batch-verified.json'),json_encode($verified,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(['result'=>$result,'verified'=>$verified],JSON_PRETTY_PRINT).PHP_EOL;
