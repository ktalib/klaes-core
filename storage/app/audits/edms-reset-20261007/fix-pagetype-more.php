<?php
require getcwd().'/vendor/autoload.php';$app=require getcwd().'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::connection('sqlsrv');
$run=trim(file_get_contents(__DIR__.'/active-backup-root.txt'));
$sql="SELECT f.id,f.is_updated, (SELECT COUNT(*) FROM scannings s WHERE s.file_indexing_id=f.id AND NOT EXISTS (SELECT 1 FROM pagetypings p WHERE p.scanning_id=s.id)) untyped FROM file_indexings f WHERE f.is_updated=1 AND EXISTS (SELECT 1 FROM pagetypings p WHERE p.file_indexing_id=f.id)";
$rows=$db->select($sql);
echo json_encode(['card_count'=>count($rows),'fully_typed'=>count(array_filter($rows,fn($r)=>(int)$r->untyped===0)),'actually_untyped'=>count(array_filter($rows,fn($r)=>(int)$r->untyped>0))]),PHP_EOL;
if(($argv[1]??'')!=='--execute')exit;
$db->beginTransaction();
try{
 $db->select('SELECT COUNT(*) n FROM scannings WITH (TABLOCKX,HOLDLOCK)');
 $db->select('SELECT COUNT(*) n FROM pagetypings WITH (TABLOCKX,HOLDLOCK)');
 $rows=$db->select($sql);$ids=array_map(fn($r)=>(int)$r->id,array_filter($rows,fn($r)=>(int)$r->untyped===0));
 if(file_put_contents($run.'/pagetype-more-flags-before.json',json_encode($rows,JSON_PRETTY_PRINT))===false)throw new RuntimeException('Cannot save flag snapshot');
 $changed=0;foreach(array_chunk($ids,500) as $chunk)$changed+=$db->table('file_indexings')->whereIn('id',$chunk)->where('is_updated',1)->update(['is_updated'=>0]);
 $remaining=$db->select($sql);
 if(count(array_filter($remaining,fn($r)=>(int)$r->untyped===0)))throw new RuntimeException('Stale flags remain');
 $db->commit();
 $controller=app(App\Http\Controllers\PageTypingController::class);$method=new ReflectionMethod($controller,'getPageTypeMoreCount');$method->setAccessible(true);
 $result=['changed'=>$changed,'actual_controller_count'=>$method->invoke($controller),'completed_at'=>date(DATE_ATOM)];
 file_put_contents($run.'/pagetype-more-flags-result.json',json_encode($result,JSON_PRETTY_PRINT));echo json_encode($result),PHP_EOL;
}catch(Throwable $e){if($db->transactionLevel())$db->rollBack();throw $e;}
