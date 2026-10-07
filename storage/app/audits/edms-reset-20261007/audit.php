<?php
// Read-only database audit. Writes local reports only; no deletion/backup implementation.
ini_set('memory_limit', '1536M');
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (isset($argv[2])) {
 if (!preg_match('/^klas_edms_restore_[0-9_]+$/', $argv[2])) throw new RuntimeException('Invalid rehearsal database');
 config(['database.connections.sqlsrv.database'=>$argv[2]]);
 Illuminate\Support\Facades\DB::purge('sqlsrv');
}
$db=Illuminate\Support\Facades\DB::connection('sqlsrv');
$out=$argv[1] ?? __DIR__;
if (!is_dir($out) && !mkdir($out, 0777, true)) throw new RuntimeException('Cannot create audit output directory');
$root=rtrim(config('filesystems.disks.public.root'), '/\\');
$summary=['started_at'=>date(DATE_ATOM),'public_root'=>$root];
function norm($p) {return strtolower(str_replace('\\','/',trim((string)$p)));}
function saveJson($name,$data) {global $out;file_put_contents($out.'/'.$name.'.json',json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));}
$st=$db->select("SELECT f.id,f.file_number,f.registry,f.source FROM file_indexings f WHERE f.registry='ST Registry' OR f.main_application_id IS NOT NULL OR f.subapplication_id IS NOT NULL OR NULLIF(LTRIM(RTRIM(f.st_fillno)),'') IS NOT NULL OR f.source LIKE 'ST %' OR f.file_number LIKE 'ST-%' OR f.file_number LIKE 'ST.%' OR EXISTS (SELECT 1 FROM mother_applications m WHERE NULLIF(f.file_number,'') IN (m.fileno,m.np_fileno,m.applied_file_number)) OR EXISTS (SELECT 1 FROM subapplications a WHERE NULLIF(f.file_number,'') IN (a.fileno,a.np_fileno,a.npFileno,a.mls_fileno))");
$stIds=array_fill_keys(array_map(fn($r)=>(int)$r->id,$st),true);
saveJson('st-indexing-evidence',$st);
$external=[];$parcelPaths=[];
$sources=[
 'change_of_purpose_applications'=>['site_plan','rec_page_application','rec_page_planning','rec_page_site_plan'],
 'plot_subdivision_applications'=>['site_plan','ownership_document','application_letter','means_of_id','tax_clearance'],
 'plot_merger_applications'=>['site_plan','ownership_document','application_letter','means_of_id','tax_clearance'],
 'plot_extension_applications'=>['site_plan','ownership_document','application_letter','means_of_id','tax_clearance'],
 'plot_separation_applications'=>['site_plan','ownership_document','application_letter','means_of_id','tax_clearance'],
 'duplex_parcel_updates'=>['site_plan'],
];
foreach($sources as $table=>$cols) foreach($cols as $col) {
 foreach($db->table($table)->whereNotNull($col)->where($col,'<>','')->get(['id',$col]) as $r) {
  $path=$r->$col;$parcelPaths[norm($path)]=true;
  $external[]=['table'=>$table,'id'=>$r->id,'column'=>$col,'path'=>$path,'keep'=>'parcel_upload'];
 }
}
foreach(['land_recommendation_batch_documents'=>'mother_recommendation','land_recommendation_documents'=>'extant_recommendation'] as $table=>$why) {
 foreach($db->table($table)->get(['id','path']) as $r) $external[]=['table'=>$table,'id'=>$r->id,'column'=>'path','path'=>$r->path,'keep'=>$why];
}
foreach($db->table('oss_applications')->whereNotNull('passport_photo')->where('passport_photo','<>','')->get(['id','passport_photo']) as $r) $external[]=['table'=>'oss_applications','id'=>$r->id,'column'=>'passport_photo','path'=>$r->passport_photo,'keep'=>'oss_passport'];
foreach(['mother_applications'=>['passport','multiple_owners_passport','id_document','rc_document','documents','survey_plan'], 'subapplications'=>['passport','multiple_owners_passport','id_document','documents']] as $table=>$cols) {
 foreach($cols as $col) foreach($db->table($table)->whereNotNull($col)->where($col,'<>','')->get(['id',$col]) as $r) {
  $value=$r->$col; $decoded=json_decode($value,true);
  $paths=[];
  $walk=function($v,$key=null) use (&$walk,&$paths) {if(is_array($v)){foreach($v as $k=>$x)$walk($x,$k);} elseif(is_string($v) && (preg_match('/\.(pdf|jpe?g|png|tiff?|webp|docx?)$/i',$v)) && (str_contains($v,'/') || str_contains($v,'\\'))) {$paths[]=$v;}};
  if(is_array($decoded)) $walk($decoded); else $paths[]=$value;
  foreach($paths as $path) $external[]=['table'=>$table,'id'=>$r->id,'column'=>$col,'path'=>$path,'keep'=>'st_application_upload'];
 }
}
$externalProtected=[];foreach($external as $r) $externalProtected[norm($r['path'])]=$r['keep'];
$mls=[]; foreach($db->table('mls_file_no')->orderBy('id')->get(['full_file_number','system_sub_type']) as $r) $mls[norm($r->full_file_number)]=$r->system_sub_type ?? 'UNSTAMPED';
$scan=[];$keeps=[];$origins=[];$scanReasons=[];
foreach($db->cursor('SELECT s.id,s.file_indexing_id,s.document_path,s.document_type,CAST(s.notes AS varchar(1000)) notes,s.parent_scan_id,s.registry,f.file_number FROM scannings s LEFT JOIN file_indexings f ON f.id=s.file_indexing_id') as $r) {
 $id=(int)$r->id;$reasons=[];$path=norm($r->document_path);
 if(isset($stIds[(int)$r->file_indexing_id]) || $r->registry==='ST Registry' || preg_match('~/(ST[_ ]Registry|ST[-.][^/]+)/~i',$path)) $reasons[]='ST';
 if($r->document_type==='Passport Photograph') $reasons[]='passport';
 if(isset($parcelPaths[$path]) || stripos($r->notes??'','parcel update upload')!==false) $reasons[]='parcel_upload';
 if(isset($externalProtected[$path])) $reasons[]=$externalProtected[$path];
 $scan[$id]=[(int)$r->file_indexing_id,$r->document_path,$r->document_type,(int)$r->parent_scan_id,$r->file_number];
 if($reasons) {$keeps[$id]=true;$scanReasons[$id]=array_unique($reasons);}
 $origin=$mls[norm($r->file_number)]??'NO MLS MATCH';
 $origins[$origin]['scans']=($origins[$origin]['scans']??0)+1;
 if($r->document_type==='Passport Photograph')$origins[$origin]['passports']=($origins[$origin]['passports']??0)+1;
}
$pages=[];$keepPages=[];$pageReasons=[];$orphanPages=0;$missingIndexPages=0;
foreach($db->cursor('SELECT id,file_indexing_id,scanning_id,file_path,registry,deleted_at FROM pagetypings') as $r) {
 $id=(int)$r->id;$sid=(int)$r->scanning_id;$reasons=[];
 if(isset($stIds[(int)$r->file_indexing_id]) || $r->registry==='ST Registry' || preg_match('~/(ST[_ ]Registry|ST[-.][^/]+)/~i',norm($r->file_path)))$reasons[]='ST';
 if(isset($keeps[$sid])) $reasons=array_merge($reasons,$scanReasons[$sid]);
 if(isset($externalProtected[norm($r->file_path)])) $reasons[]=$externalProtected[norm($r->file_path)];
 $pages[$id]=[(int)$r->file_indexing_id,$sid,$r->file_path,$r->deleted_at];
 if($sid && !isset($scan[$sid]))$orphanPages++;
 if($reasons){$keepPages[$id]=true;$pageReasons[$id]=array_unique($reasons);if(isset($scan[$sid])&&!isset($keeps[$sid])){$keeps[$sid]=true;$scanReasons[$sid]=['scan_of_protected_page'];}}
}
do {$changed=false;foreach($keeps as $id=>$_){$parent=$scan[$id][3];if($parent && isset($scan[$parent])&&!isset($keeps[$parent])){$keeps[$parent]=true;$scanReasons[$parent]=['parent_of_protected_scan'];$changed=true;}}}while($changed);
foreach($pages as $id=>$p) if(isset($keeps[$p[1]])&&!isset($keepPages[$id])){$keepPages[$id]=true;$pageReasons[$id]=$scanReasons[$p[1]];}
$protectedPaths=[];
foreach(['scannings'=>$scan,'pagetypings'=>$pages] as $table=>$rows) {
 $fp=fopen($out.'/'.$table.'-manifest.csv','w');
 fputcsv($fp,['id','file_indexing_id','linked_scan_or_parent','file_path','decision','reason']);
 $groups=[];
 foreach($rows as $id=>$r){$keep=$table==='scannings'?isset($keeps[$id]):isset($keepPages[$id]);$reasons=$table==='scannings'?($scanReasons[$id]??[]):($pageReasons[$id]??[]);$path=$table==='scannings'?$r[1]:$r[2];
  fputcsv($fp,[$id,$r[0],$table==='scannings'?$r[3]:$r[1],$path,$keep?'KEEP':'CANDIDATE',implode('|',$reasons)]);
  foreach($reasons as $reason)$groups[$reason]=($groups[$reason]??0)+1;
  if($keep)$protectedPaths[norm($path)]=$path;
 }
 fclose($fp);$summary[$table]=['total'=>count($rows),'keep'=> $table==='scannings'?count($keeps):count($keepPages),'candidate'=>count($rows)-($table==='scannings'?count($keeps):count($keepPages)),'overlapping_reasons'=>$groups];
}
$summary['commissioning_file_membership']=$origins;
$summary['orphan_pagetypings_with_nonzero_scanning_id']=$orphanPages;
$externalCounts=[];
foreach($external as &$r){$path=$r['path'];$r['exists_on_public_disk']=is_file($root.'/'.ltrim(str_replace('\\','/',$path),'/'));$externalCounts[$r['table']][$r['column']]['references']=($externalCounts[$r['table']][$r['column']]['references']??0)+1;if(!$r['exists_on_public_disk'])$externalCounts[$r['table']][$r['column']]['missing']=($externalCounts[$r['table']][$r['column']]['missing']??0)+1;$protectedPaths[norm($path)]=$path;}unset($r);
saveJson('external-upload-evidence',$external);$summary['external_uploads']=$externalCounts;
$missing=[];$bytes=0;foreach($protectedPaths as $path){$abs=$root.'/'.ltrim(str_replace('\\','/',$path),'/');if(is_file($abs))$bytes+=filesize($abs);else $missing[]=$path;}
saveJson('protected-missing-paths',$missing);
$summary['protected_disk_paths']=['unique'=>count($protectedPaths),'missing'=>count($missing),'existing_bytes'=>$bytes];
$summary['blind_scannings']=$db->select('SELECT registry_type,status,COUNT_BIG(*) n FROM blind_scannings GROUP BY registry_type,status');
$summary['references']=[];
foreach(['cadastral_documents','page_typing_tool_logs','parcel_upload_edms_log','scan_image_versions','scan_reassignment_logs','thumbnails'] as $table){$counts=['total'=>0,'linked_to_candidate_scan'=>0,'linked_to_kept_scan'=>0,'missing_scan'=>0];foreach($db->table($table)->select('scanning_id')->cursor() as $r){$counts['total']++;$id=(int)$r->scanning_id;if(isset($keeps[$id]))$counts['linked_to_kept_scan']++;elseif(isset($scan[$id]))$counts['linked_to_candidate_scan']++;else $counts['missing_scan']++;}$summary['references'][$table]=$counts;}
$summary['backup_history']=$db->select("SELECT TOP 3 b.backup_finish_date,b.type,b.backup_size,b.is_copy_only,m.physical_device_name FROM msdb.dbo.backupset b JOIN msdb.dbo.backupmediafamily m ON b.media_set_id=m.media_set_id WHERE b.database_name=DB_NAME() ORDER BY b.backup_finish_date DESC");
$summary['completed_at']=date(DATE_ATOM);
saveJson('summary',$summary);echo json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
