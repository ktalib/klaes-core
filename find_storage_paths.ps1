$results = @()
$files = Get-ChildItem -Path "\\DC-02\htdocs\klas\app" -Recurse -Filter "*.php" -ErrorAction SilentlyContinue | Where-Object { $_.FullName -notlike "*vendor*" -and $_.FullName -notlike "*RecycleBin*" }

foreach ($file in $files) {
    $lineNum = 0
    foreach ($line in (Get-Content $file.FullName -ErrorAction SilentlyContinue)) {
        $lineNum++
        if ($line -match "storage_path\([`"'`']app/") {
            $rel = $file.FullName.Replace("\\DC-02\htdocs\klas\", "")
            $results += "${rel}:${lineNum}: $($line.Trim())"
        }
    }
}

# Also check root-level PHP files
$rootFiles = Get-ChildItem -Path "\\DC-02\htdocs\klas" -Filter "*.php" -ErrorAction SilentlyContinue
foreach ($file in $rootFiles) {
    $lineNum = 0
    foreach ($line in (Get-Content $file.FullName -ErrorAction SilentlyContinue)) {
        $lineNum++
        if ($line -match "storage_path\([`"'`']app/") {
            $rel = $file.FullName.Replace("\\DC-02\htdocs\klas\", "")
            $results += "${rel}:${lineNum}: $($line.Trim())"
        }
    }
}

# Check routes, config, resources, database
$otherDirs = @("routes", "config", "resources", "database")
foreach ($dir in $otherDirs) {
    $dirPath = "\\DC-02\htdocs\klas\$dir"
    if (Test-Path $dirPath) {
        $dirFiles = Get-ChildItem -Path $dirPath -Recurse -Filter "*.php" -ErrorAction SilentlyContinue
        foreach ($file in $dirFiles) {
            $lineNum = 0
            foreach ($line in (Get-Content $file.FullName -ErrorAction SilentlyContinue)) {
                $lineNum++
                if ($line -match "storage_path\([`"'`']app/") {
                    $rel = $file.FullName.Replace("\\DC-02\htdocs\klas\", "")
                    $results += "${rel}:${lineNum}: $($line.Trim())"
                }
            }
        }
    }
}

$results | Out-File -FilePath "\\DC-02\htdocs\klas\sp_complete_results.txt" -Encoding utf8
Write-Host "Total matches: $($results.Count)"
