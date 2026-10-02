$ErrorActionPreference = "Stop"
$root   = "C:\xampp\htdocs\klas"
$src    = Join-Path $root "docs\prototypes\Survey-Module.html"
$enable = Join-Path $root "resources\views\survey_module\layouts\prototype.blade.php"
if (-not (Test-Path $enable)) { throw "layout missing: $enable" }

$lines = [System.IO.File]::ReadAllLines($src)

# id|blade-view-path|title|subtitle
$spec = @"
dashboard|dashboard|Dashboard|Department overview
compensation-dashboard|compensation\dashboard|Compensation Dashboard|Compensation monitoring
projects|compensation\projects|Project Management|Compensation projects
project-register|compensation\project_register|Project Register|New compensation project
compensation-cases|compensation\cases|Compensation Cases|Case register
compensation-register|compensation\case_register|Register Compensation Case|New compensation case
compensation-beneficiaries|compensation\beneficiaries|Beneficiaries|Case beneficiaries
compensation-trees|compensation\trees|Economic Trees|Tree census & valuation
compensation-calculator|compensation\calculator|Compensation Calculator|Rate calculator
compensation-land|compensation\land|Land Allocation|Land-for-land allocation
compensation-op|compensation\op|OP Generation|Occupancy permit generation
compensation-reports|compensation\reports|Compensation Reports|Compensation reporting
gkn-dashboard|gkn\dashboard|GKN Dashboard|GKN index
gkn-lands|gkn\lands|Government Lands|GKN government lands register
gkn-register|gkn\register|Register GKN|New government land record
gkn-tracking|gkn\tracking|GKN Tracking|File tracking
gkn-reports|gkn\reports|GKN Reports|GKN reporting
misc-kn|records\misc|Misc KN|Miscellaneous KN records
lpkn|records\lpkn|LPKN|LPKN records
examination|workflow\examination|Examination|Examination workflow
occupancy|workflow\occupancy|Occupancy Permit Workflow|OP workflow
gis|tools\gis|GIS|GIS toolkit
farm-plot-allocation|tools\plot_allocation|Farm Plot Allocation|Plot allocation
reports|reports|Reports|Survey reports
"@

$sections = New-Object System.Collections.ArrayList
$linesSpec = $spec -split "`n" | Where-Object { $_.Trim() -ne "" }
foreach ($sp in $linesSpec) {
  $parts = $sp.Split("|")
  $sections.Add([pscustomobject]@{
    id = $parts[0]; view = $parts[1]; title = $parts[2]; sub = $parts[3]
  }) | Out-Null
}

function Get-Page([string]$id, [string[]]$all) {
  $start = -1
  for ($i = 0; $i -lt $all.Count; $i++) {
    if ($all[$i] -match "id=\x22page-" + [regex]::Escape($id) + "\x22") { $start = $i; break }
  }
  if ($start -lt 0) { throw "page id not found: $id" }
  $openIndent = ([regex]::Match($all[$start], "^\s*")).Value.Length
  $end = -1
  for ($i = $start + 1; $i -lt $all.Count; $i++) {
    $line = $all[$i]
    if ($line -match "^\s*</div>\s*$") {
      $indent = ([regex]::Match($line, "^\s*")).Value.Length
      if ($indent -eq $openIndent) { $end = $i; break }
    }
  }
  if ($end -lt 0) { throw "unterminated page: $id" }
  return ,($start, $end)
}

$partRoot = Join-Path $root "resources\views\survey_module\partials\sections"
if (-not (Test-Path $partRoot)) { New-Item -ItemType Directory -Path $partRoot | Out-Null }

$log = New-Object System.Collections.ArrayList
foreach ($s in $sections) {
  $r  = Get-Page $s.id $lines
  $start = $r[0]; $end = $r[1]
  # inner = everything under the page wrapper div (exclude wrapper open/close lines)
  $inner = ($lines[($start + 1)..($end - 1)]) -join "`n"

  # relative indent of children vs the wrapper (wrapper open indent handled by layout already);
  # the layout renders @yield('content') inside <div class="page ... active" id="page-..">; we keep
  # the raw inner body verbatim (icons + classes keep prototype's FontAwesome names).
  $partial = Join-Path $partRoot ("_page_" + $s.id + ".blade.php")
  Set-Content -LiteralPath $partial -Value $inner -Encoding UTF8

  $viewDir  = Join-Path $root ("resources\views\survey_module\" + (Split-Path $s.view))
  if (-not (Test-Path $viewDir)) { New-Item -ItemType Directory -Path $viewDir | Out-Null }

  $blade = @"
@extends('survey_module.layouts.prototype')

@php
  `$PageTitle = '$($s.title)';
  `$PageSubtitle = '$($s.sub)';
  `$PageId = '$($s.id)';
  `$PrototypeSection = '_page_$($s.id)';
@endphp

@section('content')
  @include('survey_module.partials.sections._page_$($s.id)')
@endsection
"@
  $viewFile = Join-Path $root ("resources\views\survey_module\" + $s.view + ".blade.php")
  Set-Content -LiteralPath $viewFile -Value $blade -Encoding UTF8

  $log.Add(("{0,-28} -> {1,-40} {2,6} bytes" -f $s.id, $s.view, (Get-Item $partial).Length)) | Out-Null
}
$log