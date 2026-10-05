<?php
require 'C:/xampp/htdocs/klas/vendor/autoload.php';
$app=require 'C:/xampp/htdocs/klas/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::connection('sqlsrv');
$cols=$db->select("SELECT TABLE_NAME,COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='dbo'");
file_put_contents('C:/xampp/htdocs/klas/storage/app/audits/ownership-schema.json',json_encode($cols));
$tables=[];foreach($cols as $c){if(preg_match('/history|\bfh\b|file_index|op_|audit|migration|backup|legacy/i',$c->TABLE_NAME))$tables[$c->TABLE_NAME][]=$c->COLUMN_NAME;}
echo json_encode($tables,JSON_PRETTY_PRINT);
