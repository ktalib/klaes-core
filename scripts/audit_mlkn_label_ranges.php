<?php
// Read-only audit. No labels are generated or marked printed.
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection('sqlsrv');
$ranges = [[41,46,'1','A1'],[205,219,'5','A5'],[219,249,'5','A5'],[253,273,'6','A6'],[905,949,'19','A19'],[2252,2284,'46','C6'],[2431,2442,'49','C9'],[3052,3452,'',''],[3602,3633,'73','D9'],[3701,3715,'75','D11']];
$wanted = [];
foreach ($ranges as [$from,$to]) foreach (range($from,$to) as $n) $wanted[$n]=true;
$serial = static function ($v) {
    return preg_match('/^MLKN[\s-]*0*(\d+)$/i', trim((string)$v), $m) ? (int)$m[1] : null;
};
$fetch = function ($table, $cols, $select) use ($db) {
    return $db->table($table)->where(function ($q) use ($cols) {
        foreach ($cols as $col) $q->orWhere($col, 'like', 'MLKN%');
    })->get(array_values(array_unique(array_merge(['id'], $cols, $select))));
};
$indexCols = ['file_number','kangis_file_no','kangis_fileno_placeholder','kangis_fileno_resolved'];
$index = []; $indexIds = [];
foreach ($fetch('file_indexings',$indexCols,['file_title','is_deleted','is_decommissioned','registry_batch_no','shelf_location','batch_generated']) as $r) {
    foreach ($indexCols as $col) {
        $n=$serial($r->$col);
        if (isset($wanted[$n])) {$index[$n][$r->id]=$r; $indexIds[$r->id][$n]=true;}
    }
}
$groupCols=['kangis_awaiting_fileno','kangis_fileno','kangis_fileno_placeholder','kangis_fileno_resolved'];
$group=[];
foreach($fetch('kangis_grouping',$groupCols,['is_indexed','registry_batch_no','shelf_rack','is_decommissioned']) as$r) foreach($groupCols as$c){$n=$serial($r->$c);if(isset($wanted[$n]))$group[$n][$r->id]=$r;}
$history=[];
foreach(['deprecated_records'=>['file_number'],'decommissioned_files'=>['file_no','mls_file_no','kangis_file_no']] as$t=>$cols) {
    foreach($fetch($t,$cols,[]) as$r) foreach($cols as$c){$n=$serial($r->$c);if(isset($wanted[$n]))$history[$n]=true;}
}
$prints=[];
foreach(['kangis_print_label_batch_items','print_label_batch_items'] as$t){
    $q=$db->table($t)->where(function($q)use($indexIds,$t){$q->where('file_number','like','MLKN%');if($t==='kangis_print_label_batch_items')$q->orWhere('file_title','like','MLKN%');if($indexIds)$q->orWhereIn('file_indexing_id',array_keys($indexIds));});
    foreach($q->get(['id','batch_id','file_indexing_id','file_number','file_title','is_printed','printed_at','qr_code_data'])as$r){
        $ns=array_keys($indexIds[$r->file_indexing_id]??[]);
        foreach([$r->file_number,$t==='kangis_print_label_batch_items'?$r->file_title:null]as$v){$n=$serial($v);if(isset($wanted[$n]))$ns[]=$n;}
        foreach(array_unique($ns)as$n)$prints[$n][]=['source'=>$t,'batch'=>$r->batch_id,'printed'=>(bool)$r->is_printed||$r->printed_at!==null,'date'=>$r->printed_at,'qr'=>!empty($r->qr_code_data)];
    }
}
if($indexIds)foreach($db->table('barcodes')->whereIn('file_indexing_id',array_keys($indexIds))->get(['file_indexing_id','printed_at','qr_payload','batch_id'])as$r){foreach(array_keys($indexIds[$r->file_indexing_id])as$n)$prints[$n][]=['source'=>'barcodes','batch'=>$r->batch_id,'printed'=>$r->printed_at!==null,'date'=>$r->printed_at,'qr'=>!empty($r->qr_payload)];}
$details=[];$summaries=[];
foreach($ranges as$i=>[$from,$to,$batch,$rack]){
    $summary=['range'=>'MLKN '.$from.'–'.$to,'batch'=>$batch,'rack'=>$rack,'total'=>$to-$from+1,'indexed_printed'=>0,'indexed_no_print'=>0,'not_currently_indexed'=>0,'historical_only'=>0,'ready_numbers'=>[]];
    foreach(range($from,$to)as$n){
        $active=array_filter($index[$n]??[],fn($r)=>!$r->is_deleted&&!$r->is_decommissioned);
        $events=$prints[$n]??[];$printed=count(array_filter($events,fn($r)=>$r['printed']))>0;
        $historical=isset($history[$n])||(!empty($index[$n])&&!$active);
        if($active){$summary[$printed?'indexed_printed':'indexed_no_print']++;if(!$printed)$summary['ready_numbers'][]='MLKN '.$n;}
        else{$summary['not_currently_indexed']++;if($historical)$summary['historical_only']++;}
        $details[]=['range_no'=>$i+1,'file_number'=>'MLKN '.$n,'requested_batch'=>$batch,'requested_rack'=>$rack,'indexing_status'=>$active?'Indexed':($historical?'Historical/decommissioned/deleted record':'No indexing record found'),'indexing_ids'=>implode(';',array_keys($active)),'stored_file_numbers'=>implode(';',array_unique(array_map(fn($r)=>$r->file_number,$active))),'print_status'=>$printed?'Print recorded':($events?'Label/QR record exists; no print recorded':'No label print record found'),'print_batches'=>implode(';',array_unique(array_map(fn($e)=>$e['source'].':'.$e['batch'],$events))),'printed_dates'=>implode(';',array_unique(array_filter(array_column($events,'date')))),'grouping_ids'=>implode(';',array_keys($group[$n]??[])),'grouping_indexed'=>implode(';',array_unique(array_map(fn($r)=>(int)$r->is_indexed,$group[$n]??[]))),'actual_registry_batches'=>implode(';',array_unique(array_map(fn($r)=>(string)$r->registry_batch_no,$group[$n]??[])))];
    }
    $summaries[]=$summary;
}
echo json_encode(in_array('--details',$argv,true)?$details:['summary'=>array_map(function($s){unset($s['ready_numbers']);return $s;},$summaries),'unique_file_numbers'=>count($wanted)],JSON_UNESCAPED_UNICODE);
