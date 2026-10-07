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
  foreach($paths as $path){if($r['decision']==='KEEP')$protected[keyFor($path)]=normalized($path);else $candidates[$path]=true;}
 }
 fclose($f);
}
foreach(json_decode(file_get_contents($audit.'/external-upload-evidence.json'),true) as $r)$protected[keyFor($r['path'])]=normalized($r['path']);
// All blind-scan file pointers survive, including converted rows pointing to SCAN_UPLOAD.
foreach($db->table('blind_scannings')->select('document_path')->cursor() as $r)$protected[keyFor($r->document_path)]=normalized($r->document_path);
$excluded=['scannings','pagetypings','blind_scannings','scan_reassignment_logs','page_typing_tool_logs','scan_image_versions'];
$columns=$db->select("SELECT c.TABLE_NAME,c.COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS c JOIN INFORMATION_SCHEMA.TABLES t ON t.TABLE_SCHEMA=c.TABLE_SCHEMA AND t.TABLE_NAME=c.TABLE_NAME WHERE t.TABLE_TYPE='BASE TABLE' AND c.TABLE_SCHEMA='dbo' AND c.DATA_TYPE IN ('varchar','nvarchar','text','ntext') AND (c.COLUMN_NAME LIKE '%path%' OR c.COLUMN_NAME LIKE '%photo%' OR c.COLUMN_NAME LIKE '%document%' OR c.COLUMN_NAME LIKE '%passport%' OR c.COLUMN_NAME LIKE '%image%' OR c.COLUMN_NAME LIKE '%attachment%' OR c.COLUMN_NAME LIKE '%site_plan%' OR c.COLUMN_NAME LIKE 'rec_page_%' OR c.COLUMN_NAME IN ('application_letter','tax_clearance','means_of_id'))");
$referenceColumns=0;
foreach($columns as $c){
 if(in_array($c->TABLE_NAME,$excluded,true))continue;
 $col=quoted($c->COLUMN_NAME);$sql='SELECT '.$col.' value FROM '.quoted($c->TABLE_NAME).' WHERE '.$col.' IS NOT NULL AND (CHARINDEX(\'/\',CAST('.$col.' AS nvarchar(max)))>0 OR CHARINDEX(CHAR(92),CAST('.$col.' AS nvarchar(max)))>0)';
 foreach($db->cursor($sql) as $r){
  $value=(string)$r->value;$json=json_decode($value,true);
  if(is_array($json))array_walk_recursive($json,function($v)use(&$protected){if(is_string($v))$protected[keyFor($v)]=normalized($v);});
  else $protected[keyFor($value)]=normalized($value);
 }
 $referenceColumns++;
}
$roots=['F-public'=>'F:/storage/app/public','C-public'=>base_path('storage/app/public')];
$folders=['SCAN_UPLOAD','PAGETYPING','ARCHIVE_Doc_WARE'];
$plan=['created_at'=>date(DATE_ATOM),'directories'=>[],'files'=>[],'missing_protected_paths'=>[]];
foreach($roots as $label=>$root){
 foreach($folders as $folder){
  $source=$root.'/EDMS/'.$folder;
  if(!is_dir($source))continue;
  $archive=$root.'/EDMS_RESET_ARCHIVE_20261007/'.$folder;
  if(file_exists($archive))throw new RuntimeException('Archive already exists: '.$archive);
  $resolved=str_replace('\\','/',realpath($source));
  if(strtolower($resolved)!==strtolower(str_replace('\\','/',realpath($root)).'/EDMS/'.$folder))throw new RuntimeException('Unexpected source directory target');
  $plan['directories'][]=['source'=>$source,'archive'=>$archive,'root'=>$root];
  $prefix='edms/'.strtolower($folder).'/';
  foreach($protected as $relative){
   if(!is_string($relative) || !str_starts_with($relative,$prefix))continue;
   if(preg_match('~(^|/)\.\.(/|$)~',$relative))throw new RuntimeException('Unsafe protected path');
   $path=$root.'/'.$relative;
   if(!is_file($path)){$plan['missing_protected_paths'][]=$path;continue;}
   $actual=str_replace('\\','/',realpath($path));
   if(!str_starts_with(strtolower($actual),strtolower($resolved).'/'))throw new RuntimeException('Protected file outside source directory');
   $suffix=substr($actual,strlen($resolved)+1);
   $plan['files'][]=['source'=>$source.'/'.$suffix,'archive'=>$archive.'/'.$suffix,'size'=>filesize($path)];
  }
 }
}
if(file_put_contents($run.'/archive-plan.json',json_encode($plan,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES))===false)throw new RuntimeException('Cannot save archive plan');
echo json_encode(['directories'=>count($plan['directories']),'protected_files'=>count($plan['files']),'missing_references'=>count($plan['missing_protected_paths'])]),PHP_EOL;
