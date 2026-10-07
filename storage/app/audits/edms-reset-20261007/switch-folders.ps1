param([switch]$Rollback)
$ErrorActionPreference='Stop'
$runRoot=(Get-Content -LiteralPath (Join-Path $PSScriptRoot 'active-backup-root.txt')).Trim()
$plan=Get-Content -LiteralPath (Join-Path $runRoot 'archive-plan.json') -Raw | ConvertFrom-Json
$roots=@('F:\storage\app\public','C:\xampp\htdocs\klas\storage\app\public')
foreach($dir in $plan.directories){
 $root=[IO.Path]::GetFullPath($dir.root)
 $source=[IO.Path]::GetFullPath($dir.source)
 $archive=[IO.Path]::GetFullPath($dir.archive)
 $name=Split-Path -Leaf $source
 if($root -notin $roots -or $name -notin @('SCAN_UPLOAD','PAGETYPING','ARCHIVE_Doc_WARE') -or $source -ine (Join-Path $root ('EDMS\'+$name)) -or $archive -ine (Join-Path $root ('EDMS_RESET_ARCHIVE_20261007\'+$name))){throw 'Folder boundary validation failed.'}
 if($Rollback){
  if(Test-Path -LiteralPath $archive){
   if(Test-Path -LiteralPath $source){Move-Item -LiteralPath $source -Destination ($source+'_RESET_FAILED_'+(Get-Date -Format yyyyMMddHHmmss))}
   Move-Item -LiteralPath $archive -Destination $source
  }
 }else{
  if(Test-Path -LiteralPath $archive){throw 'Archive destination already exists.'}
  New-Item -ItemType Directory -Force -Path (Split-Path -Parent $archive) | Out-Null
  Move-Item -LiteralPath $source -Destination $archive
  New-Item -ItemType Directory -Path $source | Out-Null
 }
}
if($Rollback){return}
$rows=New-Object System.Collections.Generic.List[object]
foreach($file in $plan.files){
 $source=[IO.Path]::GetFullPath($file.source)
 $archive=[IO.Path]::GetFullPath($file.archive)
 $valid=$false
 foreach($dir in $plan.directories){
  $sourcePrefix=[IO.Path]::GetFullPath($dir.source).TrimEnd('\')+'\'
  $archivePrefix=[IO.Path]::GetFullPath($dir.archive).TrimEnd('\')+'\'
  if($source.StartsWith($sourcePrefix,[StringComparison]::OrdinalIgnoreCase) -and $archive -ieq ($archivePrefix+$source.Substring($sourcePrefix.Length))){$valid=$true;break}
 }
 if(!$valid){throw 'Protected file path validation failed.'}
 New-Item -ItemType Directory -Force -Path (Split-Path -Parent $source) | Out-Null
 Copy-Item -LiteralPath $archive -Destination $source
 $hash=(Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash
 if($hash -ne (Get-FileHash -LiteralPath $source -Algorithm SHA256).Hash){throw 'Protected file copy verification failed.'}
 $rows.Add([pscustomobject]@{source=$source;archive=$archive;sha256=$hash;size=(Get-Item -LiteralPath $source).Length})
}
$rows | Export-Csv -LiteralPath (Join-Path $runRoot 'verified-file-actions.csv') -NoTypeInformation
@{complete=$true;strategy='same_volume_folder_archive';protected_files=$rows.Count;archive_plan_sha256=(Get-FileHash -LiteralPath (Join-Path $runRoot 'archive-plan.json') -Algorithm SHA256).Hash;completed_at=(Get-Date).ToString('o')} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $runRoot 'folder-switch-result.json') -Encoding UTF8
