<?php
require getcwd().'/vendor/autoload.php';$app=require getcwd().'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::connection('sqlsrv');
$run=trim(file_get_contents(__DIR__.'/active-backup-root.txt')).'/passport-typing';
$expected=[];foreach(file($run.'/created-pages.jsonl',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){$row=json_decode($line,true);$expected[$row['page_id']]=$row;}
$errors=[];$verified=0;
foreach(array_chunk(array_keys($expected),500) as $ids){
 $rows=$db->table('pagetypings as p')->join('scannings as s','s.id','=','p.scanning_id')->leftJoin('users as u','u.id','=','p.typed_by')->leftJoin('PageType as t','t.id','=','p.page_type')->leftJoin('PageSubType as st','st.id','=','p.page_subtype')->whereIn('p.id',$ids)->get(['p.id','p.scanning_id','p.typed_by','s.uploaded_by','u.id as user_id','t.PageType as type_name','st.PageSubType as subtype_name','p.serial_number','p.serial_suffix','p.qc_status']);
 foreach($rows as $r){$e=$expected[$r->id];if((int)$r->typed_by!==$e['typed_by'] || !$r->user_id || $r->type_name!=='Image' || $r->subtype_name!=='Passport' || (string)$r->serial_number!=='0' || $r->serial_suffix!=='a' || $r->qc_status!=='pending')$errors[]=$r->id;else $verified++;}
}
$pending=$db->table('scannings as s')->where('s.document_type','Passport Photograph')->whereNotExists(function($q){$q->selectRaw('1')->from('pagetypings as p')->whereColumn('p.scanning_id','s.id');})->count();
$result=['verified_at'=>date(DATE_ATOM),'created_pages'=>count($expected),'verified_classification_and_user'=>$verified,'errors'=>$errors,'remaining_untyped_passports'=>$pending,'maintenance_enabled'=>is_file(storage_path('framework/down'))];
file_put_contents($run.'/verification.json',json_encode($result,JSON_PRETTY_PRINT));echo json_encode($result,JSON_PRETTY_PRINT),PHP_EOL;
if($errors || $verified!==count($expected))exit(1);
