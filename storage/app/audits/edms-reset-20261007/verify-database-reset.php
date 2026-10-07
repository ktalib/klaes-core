<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$run=trim(file_get_contents(__DIR__.'/active-backup-root.txt'));
$committed=json_decode(file_get_contents($run.'/cleanup-committed.json'),true);
if(empty($committed['committed']))throw new RuntimeException('No committed cleanup');
$db=Illuminate\Support\Facades\DB::connection('sqlsrv');
$result=['checked_at'=>date(DATE_ATOM),'counts'=>[]];
foreach(['scannings','pagetypings','blind_scannings','file_indexings','PageType','land_recommendation_batch_documents'] as $table)$result['counts'][$table]=$db->table($table)->count();
$result['blind_statuses']=$db->table('blind_scannings')->select('status',Illuminate\Support\Facades\DB::raw('COUNT(*) total'))->groupBy('status')->get();
$result['index_statuses']=$db->table('file_indexings')->select('status','workflow_status',Illuminate\Support\Facades\DB::raw('COUNT(*) total'))->groupBy('status','workflow_status')->get();
$ids=$db->table('scannings')->whereNotNull('file_indexing_id')->distinct()->limit(10)->pluck('file_indexing_id');
$result['display_status_sample']=App\Models\FileIndexing::on('sqlsrv')->whereIn('id',$ids)->get()->map(fn($row)=>['id'=>$row->id,'status'=>$row->status])->all();
$result['maintenance_enabled']=is_file(storage_path('framework/down'));
$result['blind_folder_present']=is_dir('F:/storage/app/public/EDMS/BLIND_SCAN');
$result['folder_archive_created']=is_dir('F:/storage/app/public/EDMS_RESET_ARCHIVE_20261007');
file_put_contents($run.'/post-reset-verification.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
