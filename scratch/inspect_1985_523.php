<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$c = DB::connection('sqlsrv');
$nos = ['CON-RES-1985-523','CON-RES-2018-489'];

echo "=== PropID_Master ===\n";
foreach ($nos as $n) {
  $r = $c->table('PropID_Master')->where('primary_file_number',$n)->orWhere('mlsFNo',$n)->get();
  if(!count($r)) echo "  $n => (none)\n";
  foreach ($r as $x) printf("  %-18s id=%-5s prop_id=%-10s src=%-18s mlsFNo=%s kangis=%s\n",$n,$x->id,$x->prop_id,$x->source_table,$x->mlsFNo,$x->kangisFileNo);
}
echo "\n=== file_indexings ===\n";
foreach ($nos as $n) {
  foreach ($c->table('file_indexings')->where('file_number',$n)->get() as $x)
    printf("  %-18s id=%-5s prop_id=%-10s parent=%-10s ancestral=%-10s reg=%-8s title=%s\n",$x->file_number,$x->id,$x->prop_id ?? '-',$x->parent_prop_id ?? '-',$x->ancestral_prop_id ?? '-',$x->registry ?? '-',substr($x->file_title??'',0,30));
}
echo "\n=== pra ===\n";
foreach ($nos as $n) {
  foreach ($c->table('pra')->where('fileno',$n)->orWhere('mlsFNo',$n)->get() as $x)
    printf("  %-18s id=%-5s prop_id=%-10s parent=%-10s instr=%-22s p1=%-24s p2=%s\n",$x->fileno ?: $x->mlsFNo,$x->id,$x->prop_id ?? '-',$x->parent_prop_id ?? '-',substr($x->instrument_type??'',0,22),substr($x->party_1 ?: $x->Grantor ?: $x->Assignor,0,24),substr($x->party_2 ?: $x->Grantee ?: $x->Assignee,0,24));
}
echo "\n=== mls_file_no ===\n";
foreach ($nos as $n) {
  foreach ($c->table('mls_file_no')->where('fileno',$n)->get() as $x) printf("  %s => %s\n",$n,json_encode($x));
}
