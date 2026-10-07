$ErrorActionPreference='Stop'
$runRoot=(Get-Content -LiteralPath (Join-Path $PSScriptRoot 'active-backup-root.txt')).Trim()
if(!(Test-Path -LiteralPath (Join-Path $runRoot 'cleanup-committed.json'))){throw 'Database cleanup has not committed.'}
$verification=Get-Content -LiteralPath (Join-Path $runRoot 'file-verification.json') -Raw | ConvertFrom-Json
$manifest=Join-Path $runRoot 'verified-file-actions.csv'
if(!$verification.complete -or (Get-FileHash -LiteralPath $manifest -Algorithm SHA256).Hash -ne $verification.manifest_sha256){throw 'File verification manifest is invalid.'}
$roots=@{'F-public'='F:\storage\app\public';'C-public'='C:\xampp\htdocs\klas\storage\app\public'}
$state=[ordered]@{started_at=(Get-Date).ToString('o');moved=0;already_moved=0;bytes=0;complete=$false}
$journal=New-Object System.IO.StreamWriter (Join-Path $runRoot 'quarantine-journal.csv'),$true
function Csv-Cell($v){return '"'+([string]$v).Replace('"','""')+'"'}
try {
    Import-Csv -LiteralPath $manifest | ForEach-Object {
        $row=$_
        if(!$roots.ContainsKey($row.root)){throw 'Unexpected source root.'}
        $relative=$row.relative_path.Replace('/','\')
        if($relative.StartsWith('EDMS\BLIND_SCAN\',[StringComparison]::OrdinalIgnoreCase)){throw 'Raw blind scans must never be moved.'}
        $sourceRoot=[IO.Path]::GetFullPath($roots[$row.root]).TrimEnd('\')+'\'
        $source=[IO.Path]::GetFullPath($row.source_path)
        $expected=[IO.Path]::GetFullPath((Join-Path $sourceRoot $relative))
        $quarantineRoot=[IO.Path]::GetFullPath((Join-Path $runRoot ('quarantine\'+$row.root))).TrimEnd('\')+'\'
        $destination=[IO.Path]::GetFullPath((Join-Path $quarantineRoot $relative))
        if(!$source.StartsWith($sourceRoot,[StringComparison]::OrdinalIgnoreCase) -or $source -ine $expected -or !$destination.StartsWith($quarantineRoot,[StringComparison]::OrdinalIgnoreCase)){throw 'Path is outside the verified source/quarantine boundaries.'}
        if(!(Test-Path -LiteralPath $source -PathType Leaf)){
            if((Test-Path -LiteralPath $destination -PathType Leaf) -and (Get-FileHash -LiteralPath $destination -Algorithm SHA256).Hash -eq $row.sha256){$state.already_moved++;return}
            throw ('Verified source file is missing: '+$source)
        }
        if(Test-Path -LiteralPath $destination){throw 'Quarantine destination already exists.'}
        $item=Get-Item -LiteralPath $source
        $mtime=([DateTimeOffset]$item.LastWriteTimeUtc).ToUnixTimeSeconds()
        if($item.Length -ne [long]$row.size -or $mtime -ne [long]$row.source_mtime){throw ('Source changed after verification: '+$source)}
        $backupItem=Get-Item -LiteralPath $row.backup_path
        if($backupItem.Length -ne [long]$row.size){throw 'Verified backup size changed.'}
        New-Item -ItemType Directory -Force -Path (Split-Path -Parent $destination) | Out-Null
        Move-Item -LiteralPath $source -Destination $destination
        $journal.WriteLine(((Csv-Cell $source)+','+(Csv-Cell $destination)+','+(Csv-Cell $row.sha256)))
        $state.moved++;$state.bytes+=[long]$row.size
        if($state.moved%1000 -eq 0){$journal.Flush();$state | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $runRoot 'quarantine-state.json') -Encoding UTF8}
    }
    if(($state.moved+$state.already_moved) -ne [long]$verification.files_verified){throw 'Quarantine count does not match verified manifest.'}
    $state.complete=$true
} catch {$state.error=$_.Exception.Message}
finally {$journal.Dispose();$state.finished_at=(Get-Date).ToString('o');$state | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $runRoot 'quarantine-state.json') -Encoding UTF8}
if(!$state.complete){throw $state.error}
