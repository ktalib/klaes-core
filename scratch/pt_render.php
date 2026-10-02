<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$ctl = app(\App\Http\Controllers\PropertySearchController::class);
foreach ([['file_number'=>$argv[1] ?? 'RES-1985-1547'],['prop_id'=>$argv[2] ?? '30471']] as $q) {
  $label = json_encode($q);
  try {
    $req = \Illuminate\Http\Request::create('/property-search/timeline','GET',$q+['mode'=>'partial']);
    $html = $ctl->timeline($req)->render();
    preg_match_all('/tl-source-tag tl-source-tag-([a-z0-9_\-]*)/i',$html,$m);
    printf("%-40s rendered %d bytes; classes=%s\n",$label,strlen($html),implode(',',array_unique($m[1])) ?: '(none)');
    if (strpos($html,'RoT:')!==false) echo "    RoT badge present\n";
  } catch(\Throwable $e){ printf("%-40s ERROR: %s\n",$label,substr($e->getMessage(),0,90)); }
}
