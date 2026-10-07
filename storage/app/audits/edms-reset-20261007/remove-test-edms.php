<?php
require getcwd().'/vendor/autoload.php';$app=require getcwd().'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::connection('sqlsrv');
$numbers=['RES-1991-10003','CON-COM-2001-35','RES-RC-1986-28'];
$files=$db->table('file_indexings')->whereIn('file_number',$numbers)->get(['id','file_number','status','workflow_status','is_updated']);
$ids=$files->pluck('id')->all();
$scans=$db->table('scannings')->whereIn('file_indexing_id',$ids)->get();$scanIds=$scans->pluck('id')->all();
$pages=$db->table('pagetypings')->whereIn('file_indexing_id',$ids)->orWhereIn('scanning_id',$scanIds)->get();
$summary=['files'=>$files,'scans'=>$scans->count(),'pages'=>$pages->count(),'blind_rows'=>$db->table('blind_scannings')->whereIn('file_indexing_id',$ids)->count(),'dependencies'=>[]];
foreach(['scan_reassignment_logs','scan_image_versions','page_typing_tool_logs','thumbnails','cadastral_documents','parcel_upload_edms_log'] as $table){
 if(Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn($table,'scanning_id'))$summary['dependencies'][$table]=$db->table($table)->whereIn('scanning_id',$scanIds)->count();
}
echo json_encode($summary,JSON_PRETTY_PRINT),PHP_EOL;
if(($argv[1]??'')!=='--execute')exit;
if($files->count()!==3 || $files->pluck('file_number')->unique()->count()!==3)throw new RuntimeException('Expected exactly one indexing record per named file');
$directory=storage_path('app/audits/test-edms-removal-'.date('Ymd-His'));
if(!mkdir($directory,0777,true))throw new RuntimeException('Cannot create backup directory');
$db->beginTransaction();
try{
 $locked=$db->table('file_indexings')->whereIn('id',$ids)->lockForUpdate()->get();
 $currentScans=$db->table('scannings')->whereIn('file_indexing_id',$ids)->lockForUpdate()->get();
 if($currentScans->pluck('id')->sort()->values()->all()!==$scans->pluck('id')->sort()->values()->all())throw new RuntimeException('Scan set changed');
 $currentPages=$db->table('pagetypings')->whereIn('file_indexing_id',$ids)->orWhereIn('scanning_id',$scanIds)->lockForUpdate()->get();
 foreach($currentPages as $p){if(!in_array($p->file_indexing_id,$ids))throw new RuntimeException('Page belongs to another indexed file');}
 $backup=['file_indexings'=>$locked,'scannings'=>$currentScans,'pagetypings'=>$currentPages,'dependencies'=>[]];
 foreach(array_keys($summary['dependencies']) as $table)$backup['dependencies'][$table]=$db->table($table)->whereIn('scanning_id',$scanIds)->lockForUpdate()->get();
 if($backup['dependencies']['cadastral_documents']->count())throw new RuntimeException('Unexpected cadastral business reference');
 $json=json_encode($backup,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
 if(file_put_contents($directory.'/before.json',$json)!==strlen($json))throw new RuntimeException('Backup write failed');
 $deleted=[];
 foreach(['scan_reassignment_logs','scan_image_versions','page_typing_tool_logs','thumbnails','parcel_upload_edms_log'] as $table)$deleted[$table]=$db->table($table)->whereIn('scanning_id',$scanIds)->delete();
 $deleted['pagetypings']=$db->table('pagetypings')->whereIn('id',$currentPages->pluck('id')->all())->delete();
 $deleted['scannings']=$db->table('scannings')->whereIn('id',$scanIds)->delete();
 if($db->table('scannings')->whereIn('file_indexing_id',$ids)->exists() || $db->table('pagetypings')->whereIn('file_indexing_id',$ids)->exists())throw new RuntimeException('EDMS records remain');
 if(json_encode($db->table('file_indexings')->whereIn('id',$ids)->get())!==json_encode($locked))throw new RuntimeException('Indexing business records changed');
 $db->commit();
 $result=['committed'=>true,'files'=>$numbers,'deleted'=>$deleted,'backup_directory'=>$directory,'physical_files_changed'=>false,'file_indexing_records_preserved'=>3,'completed_at'=>date(DATE_ATOM)];
 file_put_contents($directory.'/result.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
}catch(Throwable $e){if($db->transactionLevel())$db->rollBack();throw $e;}
