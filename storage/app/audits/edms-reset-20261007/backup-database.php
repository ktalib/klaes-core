<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::connection('sqlsrv');
if($db->selectOne('SELECT DB_NAME() n')->n !== 'klas') throw new RuntimeException('Unexpected database');
$root=trim(file_get_contents(__DIR__.'/active-backup-root.txt'));
$base=$db->selectOne("SELECT CAST(SERVERPROPERTY('InstanceDefaultBackupPath') AS nvarchar(500)) p")->p;
$path=rtrim($base,'\\/').'\\klas_'.basename(str_replace('\\','/',$root)).'.bak';
$state=['database'=>'klas','server_backup_path'=>$path,'started_at'=>date(DATE_ATOM),'backup_complete'=>false,'restore_verified'=>false];
file_put_contents($root.'/database-backup.json',json_encode($state,JSON_PRETTY_PRINT));
try {
 $db->unprepared("BACKUP DATABASE [klas] TO DISK = N'".str_replace("'","''",$path)."' WITH COPY_ONLY, CHECKSUM, STATS = 10");
 $state['backup_complete']=true;$state['backup_finished_at']=date(DATE_ATOM);
 file_put_contents($root.'/database-backup.json',json_encode($state,JSON_PRETTY_PRINT));
 echo 'Full COPY_ONLY backup finished.',PHP_EOL;
 try {$db->unprepared("RESTORE VERIFYONLY FROM DISK = N'".str_replace("'","''",$path)."' WITH CHECKSUM");$state['restore_verified']=true;} catch(Throwable $e){$state['verify_error']=$e->getMessage();}
 $state['metadata']=$db->select("SELECT TOP 1 backup_start_date,backup_finish_date,backup_size,has_backup_checksums,is_copy_only FROM msdb.dbo.backupset WHERE database_name=DB_NAME() ORDER BY backup_finish_date DESC");
}catch(Throwable $e){$state['error']=$e->getMessage();}
$state['completed_at']=date(DATE_ATOM);
file_put_contents($root.'/database-backup.json',json_encode($state,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo json_encode($state,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
