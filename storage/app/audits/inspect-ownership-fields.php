<?php
require 'C:/xampp/htdocs/klas/vendor/autoload.php';
$app = require 'C:/xampp/htdocs/klas/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::connection('sqlsrv');
$files=['RES-2025-1097','RES-2022-2320','RES-2025-6064','IND-2026-209'];
$out=[];
foreach(['pra','instrument_capture','oss_applications','file_indexings','mls_file_no','op_verifications','op_propid_matches'] as $table){
$cols=$db->getSchemaBuilder()->getColumnListing($table);
$out['columns'][$table]=$cols;
$keys=array_values(array_intersect($cols,['file_no','mlsFNo','fileno','temp_fileno','resolved_fileno','kangisFileNo','NewKANGISFileno','full_file_number']));
if(!$keys)continue;
$out['records'][$table]=$db->table($table)->where(function($q)use($keys,$files){foreach($keys as $key)$q->orWhereIn($key,$files);})->get()->all();
}
file_put_contents('C:/xampp/htdocs/klas/storage/app/audits/ownership-missing-fields-20261004.json',json_encode($out,JSON_PRETTY_PRINT|JSON_INVALID_UTF8_SUBSTITUTE));
foreach($out['records'] as $t=>$rows){echo "$t ".count($rows).PHP_EOL;foreach($rows as $r){$a=array_filter((array)$r,fn($v)=>$v!==null&&$v!=='');echo json_encode($a,JSON_INVALID_UTF8_SUBSTITUTE).PHP_EOL;}}
