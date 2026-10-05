<?php
chdir(dirname(__DIR__,3));require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Auth::setUser(App\Models\User::findOrFail(1));
Illuminate\Support\Facades\View::share('errors',new Illuminate\Support\ViewErrorBag());
$queries=[];Illuminate\Support\Facades\DB::listen(function($event)use(&$queries){$queries[]=['ms'=>$event->time,'sql'=>$event->sql];});
$request=Illuminate\Http\Request::create('/lands-one-stop-shop/applications/op-resettlement','GET',['source'=>'lands-one-stop-shop','type'=>'change-of-name','record_type'=>'fc']);
$app->instance('request',$request);$start=microtime(true);
$view=(new App\Http\Controllers\LandsOneStopShop\OpResettlementApplicationController())->index($request);
$queryMs=(microtime(true)-$start)*1000;$renderStart=microtime(true);$html=$view->render();$renderMs=(microtime(true)-$renderStart)*1000;file_put_contents(storage_path('app/maintenance/oss-fc-rendered.html'),$html);
usort($queries,fn($a,$b)=>$b['ms']<=>$a['ms']);
$report=['controller_ms'=>$queryMs,'render_ms'=>$renderMs,'html_bytes'=>strlen($html),'option_count'=>substr_count($html,'<option'),'rows'=>$view->getData()['records']->count(),'total'=>$view->getData()['totalRecords'],'top_queries'=>array_slice($queries,0,8)];
file_put_contents(storage_path('app/maintenance/oss-fc-profile-'.date('His').'.json'),json_encode($report,JSON_PRETTY_PRINT|JSON_INVALID_UTF8_SUBSTITUTE));
foreach($report as $key=>$value)if($key!=='top_queries')echo $key.': '.$value.PHP_EOL;
foreach(array_slice($queries,0,6)as$q)echo round($q['ms']).'ms '.substr(preg_replace('/\s+/',' ',$q['sql']),0,200).PHP_EOL;
preg_match_all('/<select\b[^>]*>.*?<\/select>/s',$html,$selects);$weights=[];foreach($selects[0]as$select){preg_match('/\bid="([^"]*)"/',$select,$id);$weights[]=['id'=>$id[1]??'', 'bytes'=>strlen($select),'options'=>substr_count($select,'<option')];}usort($weights,fn($a,$b)=>$b['bytes']<=>$a['bytes']);echo 'SELECTS '.json_encode(array_slice($weights,0,18)).PHP_EOL;
preg_match_all('/<script\b[^>]*>(.*?)<\/script>/s',$html,$scripts);$weights=[];foreach($scripts[1]as$s)$weights[]=['bytes'=>strlen($s),'start'=>substr(trim($s),0,90)];usort($weights,fn($a,$b)=>$b['bytes']<=>$a['bytes']);echo 'SCRIPTS '.json_encode(array_slice($weights,0,5)).PHP_EOL;
