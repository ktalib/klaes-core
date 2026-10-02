<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$c = DB::connection('sqlsrv');
// file_indexings rows whose prop_id disagrees with PropID_Master for the same file number
$rows = $c->select("
  SELECT COUNT(*) AS n FROM file_indexings fi
  JOIN PropID_Master pm ON pm.primary_file_number = fi.file_number
  WHERE fi.prop_id IS NOT NULL AND fi.prop_id <> '' AND fi.prop_id <> pm.prop_id
    AND (fi.is_deleted IS NULL OR fi.is_deleted = 0)
");
echo "file_indexings.prop_id != PropID_Master.prop_id : ".$rows[0]->n."\n\n";
// of those, how many collide with ANOTHER file's prop_id
$rows2 = $c->select("
  SELECT TOP 20 fi.file_number, fi.prop_id AS wrong_pid, pm.prop_id AS correct_pid,
         (SELECT TOP 1 primary_file_number FROM PropID_Master WHERE prop_id = fi.prop_id) AS pid_belongs_to
  FROM file_indexings fi
  JOIN PropID_Master pm ON pm.primary_file_number = fi.file_number
  WHERE fi.prop_id IS NOT NULL AND fi.prop_id <> '' AND fi.prop_id <> pm.prop_id
    AND (fi.is_deleted IS NULL OR fi.is_deleted = 0)
  ORDER BY fi.id
");
foreach ($rows2 as $r) printf("  %-20s has %-8s (correct %-8s) -> that pid belongs to %s\n",$r->file_number,$r->wrong_pid,$r->correct_pid,$r->pid_belongs_to ?? '(nothing)');
