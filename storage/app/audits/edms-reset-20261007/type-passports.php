<?php
ini_set('memory_limit','512M');
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::connection('sqlsrv');
$paths=app(App\Services\Edms\EdmsDocumentPathResolver::class);
$run=trim(file_get_contents(__DIR__.'/active-backup-root.txt')).'/passport-typing';
if(!is_dir($run))mkdir($run,0777,true);
$execute=($argv[1]??'')==='--execute';
$users=$db->table('users')->get(['id','first_name','last_name']);$valid=[];$names=[];
foreach($users as $user){$valid[(int)$user->id]=true;$name=strtolower(trim($user->first_name.' '.$user->last_name));if($name!=='')$names[$name][]=(int)$user->id;}
$scans=$db->table('scannings as s')->where('s.document_type','Passport Photograph')->whereNotExists(function($q){$q->selectRaw('1')->from('pagetypings as p')->whereColumn('p.scanning_id','s.id');})->orderBy('s.id')->get(['s.*']);
$plan=[];$issues=[];
foreach($scans as $scan){
 $index=$db->table('file_indexings')->where('id',$scan->file_indexing_id)->first();
 if(!$index || !trim((string)$index->file_number)){$issues[]=['scanning_id'=>$scan->id,'reason'=>'missing_file_indexing'];continue;}
 $actor=(int)$scan->uploaded_by;$origin='scannings.uploaded_by';
 if(!isset($valid[$actor])){
  $commission=$db->table('mls_file_no')->where('full_file_number',$index->file_number)->orderByDesc('id')->first(['created_by']);
  $raw=trim((string)($commission->created_by??''));$matches=$names[strtolower($raw)]??[];
  $actor=ctype_digit($raw)&&isset($valid[(int)$raw])?(int)$raw:(count($matches)===1?$matches[0]:0);$origin='mls_file_no.created_by_exact_match';
 }
 if(!$actor){$issues[]=['scanning_id'=>$scan->id,'reason'=>'unverified_uploader_or_commissioner'];continue;}
 $source=$paths->absolute($scan->document_path);
 if(!is_file($source)){$issues[]=['scanning_id'=>$scan->id,'reason'=>'missing_source_file','path'=>$scan->document_path];continue;}
 $plan[]=['scanning_id'=>(int)$scan->id,'file_indexing_id'=>(int)$index->id,'typed_by'=>$actor,'attribution_source'=>$origin,'document_path'=>$scan->document_path];
}
file_put_contents($run.'/plan.json',json_encode(['started_at'=>date(DATE_ATOM),'pending'=>$scans->count(),'ready'=>count($plan),'issues'=>$issues,'rows'=>$plan],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
if(!$execute){echo json_encode(['pending'=>$scans->count(),'ready'=>count($plan),'issues_by_reason'=>array_count_values(array_column($issues,'reason'))]),PHP_EOL;exit;}
$type=$db->table('PageType')->where('PageType','Image')->value('id');
$sub=$db->table('PageSubType')->where('PageTypeId',$type)->where('PageSubType','Passport')->value('id');
$cover=$db->table('CoverType')->where('Name','Front Cover')->value('Id');
if(!$type || !$sub || !$cover)throw new RuntimeException('Missing passport classification');
$result=['started_at'=>date(DATE_ATOM),'created'=>0,'already_typed'=>0,'issues'=>$issues,'failures'=>[]];
$journal=fopen($run.'/created-pages.jsonl','ab');$before=fopen($run.'/scans-before.jsonl','ab');
foreach($plan as $item){
 $copies=[];
 try{
  $db->beginTransaction();
  $index=$db->table('file_indexings')->where('id',$item['file_indexing_id'])->lockForUpdate()->first();
  $scan=$db->table('scannings')->where('id',$item['scanning_id'])->lockForUpdate()->first();
  if(!$index || !$scan || $scan->document_path!==$item['document_path'] || (int)$scan->file_indexing_id!==$item['file_indexing_id'])throw new RuntimeException('Source changed since audit');
  if($db->table('pagetypings')->where('scanning_id',$scan->id)->exists()){$db->rollBack();$result['already_typed']++;continue;}
  if(fwrite($before,json_encode($scan,JSON_UNESCAPED_SLASHES)."\n")===false)throw new RuntimeException('Could not save scan before-image');fflush($before);
  $registry=$paths->registryName($scan->registry??$index->registry??null);
  $fileType=App\Services\Edms\EdmsFileType::normalize($scan->edms_file_type??$index->edms_file_type??null);
  $position=max(1,(int)$db->table('pagetypings')->where('file_indexing_id',$index->id)->max('page_number')+1,(int)$scan->definition);
  $extension=strtolower(pathinfo($scan->document_path,PATHINFO_EXTENSION)?:'jpg');
  do{
   $code=$position.'-FC-I-P-0a';$filename=$code.'.'.$extension;
   $typed=$paths->pageTypingPath($registry,$index->file_number,$scan->paper_size?:'A4',$filename,$fileType);
   $archive=$paths->archivePath($registry,$index->file_number,$scan->paper_size?:'A4',$filename,$fileType);
   $occupied=is_file($paths->absolute($typed))||is_file($paths->absolute($archive));
   if($occupied)$position++;
  }while($occupied);
  $originalHash=hash_file('sha256',$paths->absolute($scan->document_path));
  foreach([$typed,$archive] as $destination){
   $copies[]=$destination;
   if(!$paths->copyWithin($scan->document_path,$destination) || hash_file('sha256',$paths->absolute($destination))!==$originalHash)throw new RuntimeException('Passport copy verification failed');
  }
  $page=App\Models\PageTyping::on('sqlsrv')->create(['file_indexing_id'=>$index->id,'scanning_id'=>$scan->id,'cover_type_id'=>$cover,'page_type'=>(string)$type,'page_subtype'=>(string)$sub,'serial_number'=>'0','serial_suffix'=>'a','page_code'=>'FC-I-P-0a','definition'=>$position,'definition_code'=>$code,'page_number'=>$position,'file_path'=>$typed,'typed_by'=>$item['typed_by'],'source'=>App\Models\PageTyping::SOURCE_IMAGE_COPY,'registry'=>$registry,'edms_file_type'=>$fileType,'qc_status'=>'pending','qc_overridden'=>false,'has_qc_issues'=>false,'is_booklet_page'=>false,'is_bcfc_page'=>false]);
  $db->table('scannings')->where('id',$scan->id)->update(['status'=>'completed','definition'=>$position,'definition_code'=>$code,'updated_at'=>now()]);
  $db->commit();
  fwrite($journal,json_encode(['scanning_id'=>$scan->id,'page_id'=>$page->id,'typed_by'=>$item['typed_by'],'attribution_source'=>$item['attribution_source'],'file_path'=>$typed,'archive_path'=>$archive,'sha256'=>$originalHash],JSON_UNESCAPED_SLASHES)."\n");fflush($journal);
  $result['created']++;
 }catch(Throwable $e){
  if($db->transactionLevel())$db->rollBack();
  foreach($copies as $copy)Illuminate\Support\Facades\Storage::disk('public')->delete($copy);
  $result['failures'][]=['scanning_id'=>$item['scanning_id'],'error'=>$e->getMessage()];
 }
 if(($result['created']+count($result['failures']))%50===0)file_put_contents($run.'/result.json',json_encode($result,JSON_PRETTY_PRINT));
}
fclose($journal);fclose($before);$result['finished_at']=date(DATE_ATOM);
file_put_contents($run.'/result.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
