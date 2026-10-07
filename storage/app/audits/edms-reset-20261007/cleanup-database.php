<?php
// Deliberately separate from backup/preparation. No writes unless explicitly invoked.
ini_set('memory_limit','1536M');
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$run=trim(file_get_contents(__DIR__.'/active-backup-root.txt'));
$mode=$argv[1]??'';
if (!in_array($mode,['--rehearse','--execute'],true)) throw new RuntimeException('Specify --rehearse or --execute');
$backup=json_decode(ltrim(file_get_contents($run.'/database-backup-verified.json'),"\xEF\xBB\xBF"),true);
if(empty($backup['backup_complete']) || empty($backup['restore_verified'])) throw new RuntimeException('Completed checksum-verified database backup required');
$rehearse=$mode==='--rehearse';
$audit=$run.($rehearse?'/rehearsal-audit':'/final-audit');
if($rehearse){
 $database=$backup['test_restore_database'];
 if(!preg_match('/^klas_edms_restore_[0-9_]+$/',$database))throw new RuntimeException('Unexpected rehearsal database');
 config(['database.connections.sqlsrv.database'=>$database]);
 Illuminate\Support\Facades\DB::purge('sqlsrv');
}else{
 $gate=json_decode(ltrim(file_get_contents($run.'/ready-for-cleanup.json'),"\xEF\xBB\xBF"),true);
 if(($gate['strategy']??'')==='database_only_keep_all_files'){
  if(empty($gate['fresh_database_backup_verified']) || empty($gate['keep_all_files']) || ($gate['backup_state_sha256']??'')!==hash_file('sha256',$run.'/database-backup-verified.json'))throw new RuntimeException('Database-only backup gate failed');
  foreach(['summary.json','scannings-manifest.csv','pagetypings-manifest.csv'] as $file){if(($gate['audit_hashes'][$file]??'')!==hash_file('sha256',$audit.'/'.$file))throw new RuntimeException('Database-only audit manifest changed');}
 }else{
  if(empty($gate['folders_verified']) || empty($gate['fresh_database_backup_verified']) || empty($gate['files_manifest_verified']))throw new RuntimeException('Backup verification gates have not passed');
  if(($gate['files_manifest_sha256']??'')!==hash_file('sha256',$run.'/verified-file-actions.csv') || ($gate['final_audit_sha256']??'')!==hash_file('sha256',$audit.'/summary.json'))throw new RuntimeException('Verified manifest changed');
  if(!is_file(storage_path('framework/down')))throw new RuntimeException('Application must be in maintenance mode');
 }
 if(is_file($run.'/cleanup-committed.json'))throw new RuntimeException('This cleanup has already committed');
}
$db=Illuminate\Support\Facades\DB::connection('sqlsrv');
$name=$db->selectOne('SELECT DB_NAME() n')->n;
if(!$rehearse && $name!=='klas')throw new RuntimeException('Unexpected live database');
$summary=json_decode(file_get_contents($audit.'/summary.json'),true);
$operation=$run.($rehearse?'/rehearsal':'/execution');
if(!is_dir($operation))mkdir($operation,0777,true);
function ident($v){return '['.str_replace(']',']]',$v).']';}
function exportRows($db,$table,$path,$exclude=[],$where=''){
 $h=fopen($path,'wb');$hash=hash_init('sha256');$n=0;
 foreach($db->cursor('SELECT * FROM '.ident($table).$where.' ORDER BY id') as $row){
  $a=(array)$row;$line=json_encode($a,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);fwrite($h,$line."\n");
  foreach($exclude as $col)unset($a[$col]);hash_update($hash,json_encode($a,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)."\n");$n++;
 }
 fclose($h);return ['rows'=>$n,'sha256'=>$hash?hash_final($hash):null];
}
function hashRows($db,$table,$exclude=[],$where=''){
 $hash=hash_init('sha256');$n=0;
 foreach($db->cursor('SELECT * FROM '.ident($table).$where.' ORDER BY id') as $row){$a=(array)$row;foreach($exclude as $col)unset($a[$col]);hash_update($hash,json_encode($a,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)."\n");$n++;}
 return ['rows'=>$n,'sha256'=>hash_final($hash)];
}
function loadManifest($db,$table,$path){
 $db->unprepared('CREATE TABLE '.ident($table).' (id int PRIMARY KEY, keep_row bit NOT NULL, path nvarchar(512) NULL)');
 $f=fopen($path,'r');$headers=fgetcsv($f);$batch=[];
 $insert=function($rows)use($db,$table){$db->insert('INSERT INTO '.ident($table).' (id,keep_row,path) SELECT id,keep_row,path FROM OPENJSON(CAST(? AS nvarchar(max))) WITH (id int,keep_row bit,path nvarchar(512))',[json_encode($rows,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)]);};
 while(($values=fgetcsv($f))!==false){$r=array_combine($headers,$values);$batch[]=['id'=>(int)$r['id'],'keep_row'=>$r['decision']==='KEEP'?1:0,'path'=>$r['file_path']];if(count($batch)===5000){$insert($batch);$batch=[];}}
 if($batch)$insert($batch);fclose($f);
 $db->unprepared('CREATE INDEX path_lookup ON '.ident($table).' (path,keep_row)');
}
loadManifest($db,'#scan_plan',$audit.'/scannings-manifest.csv');
loadManifest($db,'#page_plan',$audit.'/pagetypings-manifest.csv');
$result=['database'=>$name,'rehearsal'=>$rehearse,'started_at'=>date(DATE_ATOM),'script_sha256'=>hash_file('sha256',__FILE__),'committed'=>false];
$db->beginTransaction();
try{
 $db->statement('SET XACT_ABORT ON');
 foreach(['file_indexings','blind_scannings','scannings','pagetypings','scan_reassignment_logs','scan_image_versions','page_typing_tool_logs','thumbnails','cadastral_documents','parcel_upload_edms_log'] as $table){
  $db->select('SELECT COUNT_BIG(*) n FROM '.ident($table).' WITH (TABLOCKX,HOLDLOCK)');
 }
 foreach(['scannings'=>['#scan_plan','document_path'],'pagetypings'=>['#page_plan','file_path']] as $table=>[$plan,$path]){
  $count=(int)$db->table($table)->count();
  if($count !== (int)$summary[$table]['total'])throw new RuntimeException($table.' changed since final audit');
  $matched=(int)$db->selectOne('SELECT COUNT_BIG(*) n FROM '.ident($table).' t JOIN '.ident($plan).' p ON t.id=p.id AND ISNULL(t.'.ident($path).",'')=ISNULL(p.path,'')")->n;
  if($matched!==$count)throw new RuntimeException($table.' IDs/paths differ from final audit');
 }
 if((int)$db->selectOne("SELECT COUNT_BIG(*) n FROM scannings s JOIN #scan_plan p ON p.id=s.id WHERE p.keep_row=0 AND s.document_type='Passport Photograph'")->n)throw new RuntimeException('A passport was incorrectly selected for removal');
 // Copy all affected table data before mutation, even though the full backup exists.
 $snapshots=[];
 foreach(['scannings','pagetypings','file_indexings','blind_scannings','scan_reassignment_logs','scan_image_versions','page_typing_tool_logs','thumbnails','cadastral_documents','parcel_upload_edms_log'] as $table){
  $exclude=$table==='blind_scannings'?['status']:($table==='file_indexings'?['status','workflow_status']:[]);
  $snapshots[$table]=exportRows($db,$table,$operation.'/'.$table.'-before.jsonl',$exclude);
 }
 $keepScans=hashRows($db,'scannings',[],' WHERE id IN (SELECT id FROM #scan_plan WHERE keep_row=1)');
 $keepPages=hashRows($db,'pagetypings',[],' WHERE id IN (SELECT id FROM #page_plan WHERE keep_row=1)');
 // Legacy conversion rewrote scan paths, so match the indexed file as well.
 // Requeue already-orphaned conversions too; wholly preserved files stay intact.
 $db->unprepared("SELECT b.id INTO #blind_reset FROM blind_scannings b WHERE b.status='converted' AND NOT EXISTS (SELECT 1 FROM #scan_plan p WHERE p.keep_row=1 AND p.path=b.document_path) AND (EXISTS (SELECT 1 FROM scannings s JOIN #scan_plan p ON p.id=s.id WHERE p.keep_row=0 AND s.file_indexing_id=b.file_indexing_id) OR NOT EXISTS (SELECT 1 FROM scannings s WHERE s.file_indexing_id=b.file_indexing_id))");
 $result['blind_reset_ids']=$db->table('#blind_reset')->orderBy('id')->pluck('id')->all();
 // These tables were audited as empty/no protected external scan references.
 $blocked=(int)$db->selectOne('SELECT COUNT_BIG(*) n FROM cadastral_documents d JOIN #scan_plan p ON p.id=d.scanning_id WHERE p.keep_row=0')->n;
 if($blocked)throw new RuntimeException('New cadastral document references need review');
 foreach(['page_typing_tool_logs','scan_image_versions','thumbnails'] as $table){$result['deleted'][$table]=$db->delete('DELETE d FROM '.ident($table).' d JOIN #scan_plan p ON p.id=d.scanning_id WHERE p.keep_row=0');}
 $result['deleted']['scan_reassignment_logs']=(int)$db->selectOne('SELECT COUNT_BIG(*) n FROM scan_reassignment_logs d JOIN #scan_plan p ON p.id=d.scanning_id WHERE p.keep_row=0')->n;
 $result['deleted']['pagetypings']=$db->delete('DELETE d FROM pagetypings d JOIN #page_plan p ON p.id=d.id WHERE p.keep_row=0');
 $result['deleted']['scannings']=$db->delete('DELETE d FROM scannings d JOIN #scan_plan p ON p.id=d.id WHERE p.keep_row=0');
 $result['blind_status_updated']=$db->update("UPDATE b SET status='pending' FROM blind_scannings b JOIN #blind_reset r ON r.id=b.id");
 $result['file_indexings_updated']=$db->update("UPDATE file_indexings SET status='Indexed',workflow_status='indexed' WHERE status IS NULL OR status COLLATE Latin1_General_100_BIN2 <> 'Indexed' OR workflow_status IS NULL OR workflow_status COLLATE Latin1_General_100_BIN2 <> 'indexed'");
 foreach(['scannings','pagetypings'] as $table){
  if((int)$db->table($table)->count()!==(int)$summary[$table]['keep'])throw new RuntimeException($table.' after-count mismatch');
  if($result['deleted'][$table] !== (int)$summary[$table]['candidate'])throw new RuntimeException($table.' deletion-count mismatch');
 }
 if(hashRows($db,'scannings')!==$keepScans || hashRows($db,'pagetypings')!==$keepPages)throw new RuntimeException('Protected scan/page values changed');
 if(hashRows($db,'blind_scannings',['status'])!==$snapshots['blind_scannings'])throw new RuntimeException('Blind scan non-status values changed');
 if(hashRows($db,'file_indexings',['status','workflow_status'])!==$snapshots['file_indexings'])throw new RuntimeException('Indexing non-status values changed');
 foreach(['cadastral_documents','parcel_upload_edms_log'] as $table){if(hashRows($db,$table)!==$snapshots[$table])throw new RuntimeException('Protected dependency changed: '.$table);}
 $badIndex=(int)$db->selectOne("SELECT COUNT_BIG(*) n FROM file_indexings WHERE status IS NULL OR status COLLATE Latin1_General_100_BIN2 <> 'Indexed' OR workflow_status IS NULL OR workflow_status COLLATE Latin1_General_100_BIN2 <> 'indexed'")->n;
 if($badIndex)throw new RuntimeException('Not all file indexes reset');
 $result['after']=['scannings'=>$db->table('scannings')->count(),'pagetypings'=>$db->table('pagetypings')->count(),'blind_scannings'=>$db->table('blind_scannings')->count(),'file_indexings'=>$db->table('file_indexings')->count()];
 $result['baseline']=['reset_at'=>now()->format('Y-m-d H:i:s.v'),'scannings_max_id'=>(int)$db->table('#scan_plan')->max('id'),'pagetypings_max_id'=>(int)$db->table('#page_plan')->max('id')];
 $result['snapshots']=$snapshots;
 if($rehearse){$db->rollBack();$result['rehearsal_passed']=true;}
 else {
  $baselinePath=storage_path('app/edms-workflow-reset.json');
  if(file_put_contents($baselinePath.'.pending',json_encode($result['baseline'],JSON_PRETTY_PRINT))===false)throw new RuntimeException('Cannot stage workflow baseline');
  $db->commit();$result['committed']=true;
  if(!rename($baselinePath.'.pending',$baselinePath))throw new RuntimeException('Cannot activate workflow baseline after commit');
 }
}catch(Throwable $e){if($db->transactionLevel())$db->rollBack();$result['error']=$e->getMessage();}
$result['finished_at']=date(DATE_ATOM);
file_put_contents($operation.'/result.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
if(!$rehearse && $result['committed'])file_put_contents($run.'/cleanup-committed.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
unset($result['blind_reset_ids'],$result['snapshots']);echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
if(isset($result['error']))exit(1);
