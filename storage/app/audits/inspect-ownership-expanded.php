<?php
require 'C:/xampp/htdocs/klas/vendor/autoload.php';$app=require 'C:/xampp/htdocs/klas/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();$db=Illuminate\Support\Facades\DB::connection('sqlsrv');
$schema=json_decode(file_get_contents(__DIR__.'/ownership-schema.json'));$tables=[];foreach($schema as $c)$tables[$c->TABLE_NAME][]=$c->COLUMN_NAME;
$files=['RES-2025-1097','RES-2022-2320','RES-2025-6064','IND-2026-209','TEMP-153160','TEMP-153321','TEMP-153057'];
$out=[];foreach($tables as $t=>$cols){$keys=array_values(array_filter($cols,fn($c)=>in_array(strtolower($c),['file_no','mlsfno','fileno','temp_fileno','resolved_fileno','kangisfileno','newkangisfileno','full_file_number','file_number','mls_file_no','mls_file_number','target_file_number','record_file_number','related_fileno','old_fileno'])));if(!$keys)continue;
$rows=$db->table($t)->where(function($q)use($keys,$files){foreach($keys as $k)$q->orWhereIn($k,$files);})->limit(100)->get()->all();if($rows)$out[$t]=$rows;}
file_put_contents(__DIR__.'/ownership-expanded-evidence.json',json_encode($out,JSON_PRETTY_PRINT|JSON_INVALID_UTF8_SUBSTITUTE));foreach($out as $t=>$rows){echo "$t ".count($rows).PHP_EOL;if(!in_array($t,['pra','oss_applications']))foreach($rows as $r)echo json_encode(array_filter((array)$r,fn($v)=>$v!==null&&$v!==''),JSON_INVALID_UTF8_SUBSTITUTE).PHP_EOL;}
