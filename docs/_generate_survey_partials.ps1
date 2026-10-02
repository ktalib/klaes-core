$ErrorActionPreference = "Stop"
$root = "C:\xampp\htdocs\klas"
$src  = Join-Path $root "docs\prototypes\Survey-Module.html"
$base = "resources\views\survey_module"

# id|view-path (relative to survey_module, dots->slash)|section-id
$spec = @"
dashboard|dashboard|dashboard
compensation-dashboard|compensation/dashboard|compensation-dashboard
projects|compensation/projects|projects
project-register|compensation/project_register|project-register
compensation-cases|compensation/cases|compensation-cases
compensation-register|compensation/case_register|compensation-register
compensation-beneficiaries|compensation/beneficiaries|compensation-beneficiaries
compensation-trees|compensation/trees|compensation-trees
compensation-calculator|compensation/calculator|compensation-calculator
compensation-land|compensation/land|compensation-land
compensation-op|compensation/op|compensation-op
compensation-reports|compensation/reports|compensation-reports
gkn-dashboard|gkn/dashboard|gkn-dashboard
gkn-lands|gkn/lands|gkn-lands
gkn-register|gkn/register|gkn-register
gkn-tracking|gkn/tracking|gkn-tracking
gkn-reports|gkn/reports|gkn-reports
misc-kn|records/misc|misc-kn
lpkn|records/lpkn|lpkn
examination|workflow/examination|examination
occupancy|workflow/occupancy|occupancy
gis|tools/gis|gis
farm-plot-allocation|tools/plot_allocation|farm-plot-allocation
reports|reports|reports
"@

$lines = [System.IO.File]::ReadAllLines($src)
$sections = Join-Path $root (Join-Path $base "partials\sections")
if (-not (Test-Path $sections)) { New-Item -ItemType Directory $sections | Out-Null }

function Slice-Page([string]$id, [string[]]$lines) {
  # find opener div.page with id="page-<id>"
  $open = -1
  for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match ('<div class="page active" id="page-' + [regex]::Escape($id) + '"')) {
      $open = $i; break
    }
    if ($lines[$i] -match ('<div class="page" id="page-' + [regex]::Escape($id) + '"')) {
      $open = $i; break
    }
  }
  if ($open -lt 0) { throw "Opener not found for page-$id" }

  # measure indent of the opener
  $indent = ([regex]::Match($lines[$open], '^\s*')).Value.Length

  # find matching close: a </div> that restores us to the opener's indent level.
  # walk forward, tracking div depth relative to baseline
  $depth = 0
  $started = $false
  for ($i = $open; $i -lt $lines.Count; $i++) {
    $line = $lines[$i]
    $hasOpen  = ([regex]::Matches($line, '<div\b')).Count
    $hasClose = ([regex]::Matches($line, '</div>')).Count
    $depth += $hasOpen - $hasClose
    if ($depth -le 0 -and $i -gt $open) { $close = $i; break }
  }
  if (-not $close) { throw "Close not found for page-$id" }

  return ,@($open, $close)
}

$report = New-Object System.Collections.ArrayList
foreach ($row in ($spec -split "`n" | Where-Object { $_.Trim() })) {
  $parts = $row -split '\|'
  $id    = $parts[0]
  $view  = $parts[1]
  $secId = $parts[2]

  $r = Slice-Page $id $lines
  $open  = $r[0]
  $close = $r[1]

  # inner verbatim content between opener and its matching close
  $inner = ($lines[($open)..($close)]) -join "`n"
  $secFile = Join-Path $sections "_page_$secId.blade.php"
  Set-Content -LiteralPath $secFile -Value $inner -Encoding UTF8

  # blade wrapper
  $viewRel = $view -replace '\\', '/'
  $viewPath = Join-Path $root (Join-Path $base ($viewRel + ".blade.php"))
  $dir = Split-Path $viewPath
  if (-not (Test-Path $dir)) { New-Item -ItemType Directory $dir | Out-Null }

  $blade = @"
@extends('survey_module.layouts.prototype')

@php
  `$PageTitle = 'Survey';
  `$PageSubtitle = '$($secId -replace '-',' ')';
@endphp

@section('content')
@include('survey_module.partials.sections._page_$secId')
@endsection
"@
  Set-Content -LiteralPath $viewPath -Value $blade -Encoding UTF8

  [void]$report.Add(("{0,-26} => {1,-40}  ({2}-{3}) bytes=$("{0:N0}" -f (Get-Item \$secFile).Length))" -f $id, $view, $open, $close))
}
$report