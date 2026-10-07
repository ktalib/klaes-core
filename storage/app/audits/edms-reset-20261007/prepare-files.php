<?php
// Read-only verification of the exact files eligible for removal from active storage.
ini_set('memory_limit','1536M');
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$run=trim(file_get_contents(__DIR__.'/active-backup-root.txt'));
$audit=$run.'/final-audit';
$db=Illuminate\Support\Facades\DB::connection('sqlsrv');
function normalized($p){
 $p=str_replace('\\','/',trim((string)$p));
 if(preg_match('~^https?://~i',$p))$p=rawurldecode(parse_url($p,PHP_URL_PATH)??'');
 foreach(['F:/storage/app/public/','C:/xampp/htdocs/klas/storage/app/public/','storage/app/public/','app/public/','public/','storage/'] as $prefix){if(str_starts_with(strtolower(ltrim($p,'/')),strtolower($prefix))){$p=substr(ltrim($p,'/'),strlen($prefix));break;}}
 return strtolower(ltrim($p,'/'));
}
function keyFor($p){return hash('sha256',normalized($p));}
function quoted($v){return '['.str_replace(']',']]',$v).']';}
$protected=[];$candidates=[];
foreach(['scannings','pagetypings'] as $table){
 $f=fopen($audit.'/'.$table.'-manifest.csv','r');$headers=fgetcsv($f);
 while(($values=fgetcsv($f))!==false){
  $r=array_combine($headers,$values);$paths=[$r['file_path']];
  if(str_starts_with(str_replace('\\','/',$r['file_path']),'EDMS/PAGETYPING/'))$paths[]=str_replace('EDMS/PAGETYPING/','EDMS/ARCHIVE_Doc_WARE/',$r['file_path']);
  foreach($paths as $path){if($r['decision']==='KEEP')$protected[keyFor($path)]=true;else $candidates[$path]=true;}
 }
 fclose($f);
}
foreach(json_decode(file_get_contents($audit.'/external-upload-evidence.json'),true) as $r)$protected[keyFor($r['path'])]=true;
// All blind-scan file pointers survive, including converted rows pointing to SCAN_UPLOAD.
foreach($db->table('blind_scannings')->select('document_path')->cursor() as $r)$protected[keyFor($r->document_path)]=true;
$excluded=['scannings','pagetypings','blind_scannings','scan_reassignment_logs','page_typing_tool_logs','scan_image_versions'];
$columns=$db->select("SELECT c.TABLE_NAME,c.COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS c JOIN INFORMATION_SCHEMA.TABLES t ON t.TABLE_SCHEMA=c.TABLE_SCHEMA AND t.TABLE_NAME=c.TABLE_NAME WHERE t.TABLE_TYPE='BASE TABLE' AND c.TABLE_SCHEMA='dbo' AND c.DATA_TYPE IN ('varchar','nvarchar','text','ntext') AND (c.COLUMN_NAME LIKE '%path%' OR c.COLUMN_NAME LIKE '%photo%' OR c.COLUMN_NAME LIKE '%document%' OR c.COLUMN_NAME LIKE '%passport%' OR c.COLUMN_NAME LIKE '%image%' OR c.COLUMN_NAME LIKE '%attachment%' OR c.COLUMN_NAME LIKE '%site_plan%' OR c.COLUMN_NAME LIKE 'rec_page_%' OR c.COLUMN_NAME IN ('application_letter','tax_clearance','means_of_id'))");
$referenceColumns=0;
foreach($columns as $c){
 if(in_array($c->TABLE_NAME,$excluded,true))continue;
 $col=quoted($c->COLUMN_NAME);$sql='SELECT '.$col.' value FROM '.quoted($c->TABLE_NAME).' WHERE '.$col.' IS NOT NULL AND (CHARINDEX(\'/\',CAST('.$col.' AS nvarchar(max)))>0 OR CHARINDEX(CHAR(92),CAST('.$col.' AS nvarchar(max)))>0)';
 foreach($db->cursor($sql) as $r){
  $value=(string)$r->value;$json=json_decode($value,true);
  if(is_array($json))array_walk_recursive($json,function($v)use(&$protected){if(is_string($v))$protected[keyFor($v)]=true;});
  else $protected[keyFor($value)]=true;
 }
 $referenceColumns++;
}
$roots=['F-public'=>'F:/storage/app/public','C-public'=>base_path('storage/app/public')];
$summary=['started_at'=>date(DATE_ATOM),'reference_columns_checked'=>$referenceColumns,'files_verified'=>0,'bytes_verified'=>0,'protected_candidate_paths'=>0,'missing_candidate_paths'=>0,'unsafe_paths'=>0,'complete'=>false];
$cache=[];
if(is_file($run.'/verified-file-actions.csv')){
 $old=fopen($run.'/verified-file-actions.csv','r');$headers=fgetcsv($old);
 while(($values=fgetcsv($old))!==false){$r=array_combine($headers,$values);if(isset($r['source_mtime'],$r['backup_mtime']))$cache[$r['root'].'/'.$r['relative_path']]=$r;}
 fclose($old);
}
$summary['cached_hashes_reused']=0;
$handle=fopen($run.'/verified-file-actions.csv','w');fputcsv($handle,['root','relative_path','source_path','backup_path','size','sha256','source_mtime','backup_mtime']);
try{
 foreach(array_keys($candidates) as $path){
  if(isset($protected[keyFor($path)]) || str_starts_with(normalized($path),'edms/blind_scan/')){$summary['protected_candidate_paths']++;continue;}
  $relative=str_replace('\\','/',$path);
  if($relative==='' || preg_match('~(^/|^[a-zA-Z]:|(^|/)\.\.(/|$))~',$relative)){$summary['unsafe_paths']++;continue;}
  $found=false;
  foreach($roots as $label=>$root){
   $source=$root.'/'.$relative;if(!is_file($source))continue;$found=true;
   $actual=realpath($source);$resolvedRoot=rtrim(str_replace('\\','/',realpath($root)),'/').'/';
   if(!$actual || !str_starts_with(strtolower(str_replace('\\','/',$actual)),strtolower($resolvedRoot)))throw new RuntimeException('Candidate resolves outside its source root');
   $backup=$run.'/files/'.$label.'/'.$relative;
   if(!is_file($backup) || filesize($source)!==filesize($backup))throw new RuntimeException('Backup missing or size mismatch: '.$label.'/'.$relative);
   $beforeSourceMtime=filemtime($source);$beforeBackupMtime=filemtime($backup);$size=filesize($source);
   $prior=$cache[$label.'/'.$relative]??null;
   if($prior && (int)$prior['size']===$size && (int)$prior['source_mtime']===$beforeSourceMtime && (int)$prior['backup_mtime']===$beforeBackupMtime){$hash=$prior['sha256'];$summary['cached_hashes_reused']++;}
   else{
    $hash=hash_file('sha256',$source);
    if($hash!==hash_file('sha256',$backup))throw new RuntimeException('Backup hash mismatch: '.$label.'/'.$relative);
    clearstatcache(true,$source);clearstatcache(true,$backup);
    if(filemtime($source)!==$beforeSourceMtime || filemtime($backup)!==$beforeBackupMtime || filesize($source)!==$size || filesize($backup)!==$size)throw new RuntimeException('File changed during verification: '.$relative);
   }
   fputcsv($handle,[$label,$relative,$actual,realpath($backup),$size,$hash,$beforeSourceMtime,$beforeBackupMtime]);
   $summary['files_verified']++;$summary['bytes_verified']+=filesize($source);
   if($summary['files_verified']%1000===0)file_put_contents($run.'/file-verification.json',json_encode($summary,JSON_PRETTY_PRINT));
  }
  if(!$found)$summary['missing_candidate_paths']++;
 }
 $summary['complete']=true;
}catch(Throwable $e){$summary['error']=$e->getMessage();}
fclose($handle);$summary['completed_at']=date(DATE_ATOM);$summary['manifest_sha256']=hash_file('sha256',$run.'/verified-file-actions.csv');
file_put_contents($run.'/file-verification.json',json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
if(!$summary['complete'])exit(1);
