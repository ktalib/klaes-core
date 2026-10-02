<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$svc = app(\App\Services\LegalSearchService::class);
$conn = DB::connection('sqlsrv');

$ref = new ReflectionMethod($svc,'isForeignIndexingPropId');
$ref->setAccessible(true);
$vars = new ReflectionMethod($svc,'fileNumberVariants'); $vars->setAccessible(true);

foreach ([['CON-RES-1985-523','16669'],['CON-RES-1985-523','12481'],['CON-RES-2018-489','16669']] as [$fn,$pid]) {
  $v = $vars->invoke($svc,$fn);
  printf("%-18s pid=%-8s foreign=%s\n",$fn,$pid,$ref->invoke($svc,$conn,$v,$pid)?'YES (rejected)':'no (trusted)');
}
