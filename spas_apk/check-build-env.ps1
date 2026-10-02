# =============================================================================
#  SPAS APK - build environment check
# -----------------------------------------------------------------------------
#  Run this ON THE BUILD MACHINE (the one with Android Studio / SDK), then send
#  back the whole output. It only reads - it installs nothing and changes nothing.
#
#  HOW TO RUN
#    1. Open PowerShell in this folder
#    2. If it refuses to run:   Set-ExecutionPolicy -Scope Process Bypass
#    3. .\check-build-env.ps1
#
#  Written for Windows PowerShell 5.1 (no ternary, no ?? operator) so it runs
#  on a stock Windows box without upgrading PowerShell first.
# =============================================================================

$ErrorActionPreference = 'SilentlyContinue'
$script:Fails = 0
$script:Warns = 0
$script:Report = @()

function Say($status, $label, $detail) {
    if ($status -eq 'OK')   { $tag = '[ OK ]'; $color = 'Green'  }
    if ($status -eq 'WARN') { $tag = '[WARN]'; $color = 'Yellow'; $script:Warns++ }
    if ($status -eq 'FAIL') { $tag = '[FAIL]'; $color = 'Red';    $script:Fails++ }
    Write-Host ("{0} {1,-22} {2}" -f $tag, $label, $detail) -ForegroundColor $color
    $script:Report += ("{0} {1,-22} {2}" -f $tag, $label, $detail)
}

function Have($cmd) {
    $c = Get-Command $cmd -ErrorAction SilentlyContinue
    if ($c) { return $c.Source } else { return $null }
}

function Get-SdkScore($path) {
    if (-not $path -or -not (Test-Path $path)) { return -1 }

    $score = 0
    if (Test-Path (Join-Path $path 'platform-tools\adb.exe')) { $score += 4 }
    if (Test-Path (Join-Path $path 'platforms'))              { $score += 3 }
    if (Test-Path (Join-Path $path 'build-tools'))            { $score += 2 }
    if (Test-Path (Join-Path $path 'licenses'))               { $score += 1 }
    return $score
}

Write-Host ""
Write-Host "SPAS APK - build environment check" -ForegroundColor Cyan
Write-Host ("Machine: {0}   PowerShell: {1}" -f $env:COMPUTERNAME, $PSVersionTable.PSVersion) -ForegroundColor DarkGray
Write-Host ("-" * 78)

# --- 1. Node.js -------------------------------------------------------------
# Capacitor 7 needs Node 20+. Node 18 works for Capacitor 6 but is end-of-life.
$nodePath = Have 'node'
if ($nodePath) {
    $nodeVer = (& node -v) -replace 'v',''
    $nodeMajor = [int]($nodeVer -split '\.')[0]
    if ($nodeMajor -ge 20)     { Say 'OK'   'Node.js' "v$nodeVer" }
    elseif ($nodeMajor -ge 18) { Say 'WARN' 'Node.js' "v$nodeVer - works, but 20 LTS or newer is recommended" }
    else                       { Say 'FAIL' 'Node.js' "v$nodeVer - too old, install Node 20 LTS" }
} else {
    Say 'FAIL' 'Node.js' 'NOT FOUND - install Node 20 LTS from nodejs.org'
}

# --- 2. npm -----------------------------------------------------------------
if (Have 'npm') { Say 'OK' 'npm' ("v" + (& npm -v)) }
else            { Say 'FAIL' 'npm' 'NOT FOUND - ships with Node' }

# --- 3. Java JDK ------------------------------------------------------------
# The Android Gradle Plugin needs JDK 17 or 21. JDK 8/11 will fail the build,
# and a JRE (no javac) is not enough - Gradle needs the full JDK.
$javaPath = Have 'java'
if ($javaPath) {
    $javaRaw = (& java -version 2>&1) | Select-Object -First 1
    $javaVer = 0
    if ($javaRaw -match '"(\d+)') { $javaVer = [int]$Matches[1] }
    if ($javaVer -eq 17 -or $javaVer -eq 21) { Say 'OK'   'Java JDK' "$javaRaw" }
    elseif ($javaVer -gt 21)                 { Say 'WARN' 'Java JDK' "$javaRaw - newer than 21, Gradle may not support it yet" }
    else                                     { Say 'FAIL' 'Java JDK' "$javaRaw - need JDK 17 or 21" }
} else {
    Say 'FAIL' 'Java JDK' 'java NOT on PATH - install Temurin JDK 21'
}

if ($env:JAVA_HOME) {
    if (Test-Path (Join-Path $env:JAVA_HOME 'bin\javac.exe')) {
        Say 'OK' 'JAVA_HOME' $env:JAVA_HOME
    } else {
        Say 'FAIL' 'JAVA_HOME' "$env:JAVA_HOME - has no bin\javac.exe (this is a JRE, not a JDK)"
    }
} else {
    Say 'WARN' 'JAVA_HOME' 'not set - Gradle usually still finds java, but set it to be safe'
}

# --- 4. Android SDK location ------------------------------------------------
$sdkCandidates = @()
if ($env:ANDROID_HOME)     { $sdkCandidates += $env:ANDROID_HOME }
if ($env:ANDROID_SDK_ROOT) { $sdkCandidates += $env:ANDROID_SDK_ROOT }
if ($env:LOCALAPPDATA)     { $sdkCandidates += (Join-Path $env:LOCALAPPDATA 'Android\Sdk') }
$sdkCandidates += 'C:\Android\Sdk'
$sdkCandidates += 'C:\Android\sdk'

$sdk = $null
$bestScore = -1
foreach ($candidate in ($sdkCandidates | Select-Object -Unique)) {
    $score = Get-SdkScore $candidate
    if ($score -gt $bestScore) {
        $bestScore = $score
        $sdk = $candidate
    }
}

if ($sdk -and (Test-Path $sdk)) {
    if ($bestScore -ge 10) { Say 'OK' 'Android SDK' $sdk }
    else                   { Say 'WARN' 'Android SDK' "$sdk found, but it looks incomplete - set ANDROID_HOME to the full SDK root" }
} else {
    Say 'FAIL' 'Android SDK' 'NOT FOUND - install via Android Studio, then set ANDROID_HOME'
}

# --- 5. SDK packages --------------------------------------------------------
# The build needs: platform-tools (adb), a platform >= android-34, and build-tools.
if ($sdk -and (Test-Path $sdk)) {

    if (Test-Path (Join-Path $sdk 'platform-tools\adb.exe')) {
        Say 'OK' 'platform-tools' 'adb present'
    } else {
        Say 'FAIL' 'platform-tools' 'adb NOT found - install "Android SDK Platform-Tools"'
    }

    $platDir = Join-Path $sdk 'platforms'
    if (Test-Path $platDir) {
        $plats = Get-ChildItem $platDir -Directory | ForEach-Object { $_.Name }
        $nums  = $plats | ForEach-Object { if ($_ -match 'android-(\d+)') { [int]$Matches[1] } }
        $best  = ($nums | Measure-Object -Maximum).Maximum
        if ($best -ge 34) { Say 'OK'   'SDK platform' ("highest = android-$best  (all: " + ($plats -join ', ') + ")") }
        else              { Say 'FAIL' 'SDK platform' ("highest = android-$best - need android-34 or newer") }
    } else {
        Say 'FAIL' 'SDK platform' 'no platforms installed - install "Android 14 (API 34)" or newer'
    }

    $btDir = Join-Path $sdk 'build-tools'
    if (Test-Path $btDir) {
        $bts = Get-ChildItem $btDir -Directory | ForEach-Object { $_.Name }
        Say 'OK' 'build-tools' ($bts -join ', ')
    } else {
        Say 'FAIL' 'build-tools' 'NOT installed - install "Android SDK Build-Tools"'
    }

    # Licences: unaccepted licences fail the build with a confusing message.
    $licDir = Join-Path $sdk 'licenses'
    if (Test-Path $licDir) {
        $licCount = (Get-ChildItem $licDir -File | Measure-Object).Count
        if ($licCount -ge 1) { Say 'OK'   'SDK licences' "$licCount accepted" }
        else                 { Say 'FAIL' 'SDK licences' 'none accepted - run: sdkmanager --licenses' }
    } else {
        Say 'FAIL' 'SDK licences' 'no licenses folder - run: sdkmanager --licenses'
    }
}

# --- 6. Git -----------------------------------------------------------------
if (Have 'git') { Say 'OK' 'Git' (& git --version) }
else            { Say 'WARN' 'Git' 'NOT FOUND - only needed if you clone rather than copy the folder' }

# --- 7. Gradle --------------------------------------------------------------
# Not required: Capacitor generates a gradlew wrapper that downloads its own.
if (Have 'gradle') { Say 'OK' 'Gradle (optional)' ((& gradle -v | Select-String 'Gradle ') -join ' ').Trim() }
else               { Say 'OK' 'Gradle (optional)' 'not installed - fine, the gradlew wrapper handles it' }

# --- 8. Disk space ----------------------------------------------------------
# First build pulls ~2 GB of Gradle + dependencies.
$drive = (Get-Item $PSScriptRoot).PSDrive.Name
$free  = [math]::Round((Get-PSDrive $drive).Free / 1GB, 1)
if ($free -ge 10)     { Say 'OK'   'Disk free' "$free GB on $drive`:" }
elseif ($free -ge 5)  { Say 'WARN' 'Disk free' "$free GB on $drive`: - first build needs ~2 GB, this is tight" }
else                  { Say 'FAIL' 'Disk free' "$free GB on $drive`: - not enough" }

# --- 9. Network -------------------------------------------------------------
# The first build downloads from both; a blocked corporate network is a common
# cause of a build that fails minutes in with no obvious reason.
# NB: do not name this loop variable $host - that is a read-only PowerShell
# automatic variable and assigning to it throws at runtime.
foreach ($endpoint in @('registry.npmjs.org', 'dl.google.com')) {
    $ok = Test-NetConnection -ComputerName $endpoint -Port 443 -InformationLevel Quiet -WarningAction SilentlyContinue
    if ($ok) { Say 'OK'   "Reach $endpoint" 'port 443 open' }
    else     { Say 'FAIL' "Reach $endpoint" 'UNREACHABLE - build will fail on download' }
}

# --- Summary ----------------------------------------------------------------
Write-Host ("-" * 78)
if ($script:Fails -eq 0 -and $script:Warns -eq 0) {
    Write-Host "ALL CHECKS PASSED - this machine can build the APK." -ForegroundColor Green
} elseif ($script:Fails -eq 0) {
    Write-Host "$($script:Warns) warning(s), no blockers - should build." -ForegroundColor Yellow
} else {
    Write-Host "$($script:Fails) blocker(s) and $($script:Warns) warning(s) - fix the [FAIL] lines above." -ForegroundColor Red
}
Write-Host ""
Write-Host "Copy everything between the lines below and send it back:" -ForegroundColor Cyan
Write-Host ("=" * 78)
$script:Report | ForEach-Object { Write-Host $_ }
Write-Host ("=" * 78)
Write-Host ""
