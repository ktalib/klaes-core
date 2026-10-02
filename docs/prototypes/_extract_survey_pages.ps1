$ErrorActionPreference = "Stop"
$root = "C:\xampp\htdocs\klas"
$src = Join-Path $root "docs\prototypes\Survey-Module.html"
$lines = [System.IO.File]::ReadAllLines($src)
$base = "resources\views\survey_module"
$secDir = Join-Path $root "$base\partials\sections"
if (-not (Test-Path $secDir)) { New-Item -ItemType Directory -Path $secDir | Out-Null }

# page-id <=> view path (blade file) + title
$map = [ordered]@{
  'dashboard'                = @('dashboard',                                'Survey Dashboard',                        'Overview')
  'compensation-dashboard'   = @('compensation\dashboard',                   'Compensation Dashboard',                  'Monitoring & valuation')
  'projects'                 = @('compensation\projects',                    'Project Management',                      'Compensation projects')
  'project-register'         = @('compensation\project_register',            'Create Project',                          'Register a compensation project')
  'compensation-cases'       = @('compensation\cases',                       'Compensation Cases',                      'All compensation cases')
  'compensation-register'    = @('compensation\case_register',               'Register Case',                           'New compensation case')
  'compensation-beneficiaries'= @('compensation\beneficiaries',              'Beneficiaries',                           'Case beneficiaries')
  'compensation-trees'       = @('compensation\trees',                       'Economic Trees',                          'Tree census & valuation')
  'compensation-calculator'  = @('compensation\calculator',                  'Compensation Calculator',                 'Rates & entitlement')
  'compensation-land'        = @('compensation\land',                        'Land Allocation',                         'Land-for-land scheme')
  'compensation-op'          = @('compensation\op',                          'Occupancy Permit (OP)',                   'OP generation')
  'compensation-reports'     = @('compensation\reports',                     'Compensation Reports',                    'Compensation reporting')
  'gkn-dashboard'            = @('gkn\dashboard',                            'GKN Dashboard',                           'Government registered lands')
  'gkn-lands'                = @('gkn\lands',                                'GKN Government Lands',                    'Indexed parcels')
  'gkn-register'             = @('gkn\register',                             'Register GKN',                            'New GKN record')
  'gkn-tracking'             = @('gkn\tracking',                             'GKN File Tracking',                       'Track files')
  'gkn-reports'              = @('gkn\reports',                              'GKN Reports',                             'GKN reporting')
  'misc-kn'                  = @('records\misc',                             'Misc KN',                                 'Miscellaneous records')
  'lpkn'                     = @('records\lpkn',                             'LPKN',                                    'Land records')
  'examination'              = @('workflow\examination',                     'Examination',                             'Examination workflow')
  'occupancy'                = @('workflow\occupancy',                       'Occupancy Permit Workflow',               'OP workflow')
  'gis'                      = @('tools\gis',                                'GIS',                                     'Geospatial tools')
  'farm-plot-allocation'     = @('tools\plot_allocation',                    'Farm Plot Allocation',                    'Plot registers')
  'reports'                  = @('reports',                                  'Reports',                                 'Cross-module reports')
}

# navigateTo target <=> route
$navTarget = @{
  'dashboard'='survey-module.dashboard'; 'compensation-dashboard'='survey-module.compensation.dashboard';
  'projects'='survey-module.compensation.projects'; 'project-register'='survey-module.compensation.projects.register';
  'compensation-cases'='survey-module.compensation.cases'; 'compensation-register'='survey-module.compensation.register';
  'compensation-beneficiaries'='survey-module.compensation.beneficiaries'; 'compensation-trees'='survey-module.compensation.trees';
  'compensation-calculator'='survey-module.compensation.calculator'; 'compensation-land'='survey-module.compensation.land';
  'compensation-op'='survey-module.compensation.op'; 'compensation-reports'='survey-module.compensation.reports';
  'gkn-dashboard'='survey-module.gkn.dashboard'; 'gkn-lands'='survey-module.gkn.lands'; 'gkn-register'='survey-module.gkn.register';
  'gkn-tracking'='survey-module.gkn.tracking'; 'gkn-reports'='survey-module.gkn.reports';
  'misc-kn'='survey-module.records.misc'; 'lpkn'='survey-module.records.lpkn';
  'examination'='survey-module.workflow.examination'; 'occupancy'='survey-module.workflow.occupancy';
  'gis'='survey-module.tools.gis'; 'farm-plot-allocation'='survey-module.tools.plot_allocation';
  'reports'='survey-module.reports'
}

function Get-PageRange($id) {
  $start=-1; $depth=0; $end=-1
  for($i=0;$i -lt $lines.Count;$i++){
    if($start -eq -1){
      if($lines[$i] -match '<div class="page[^"]*"[^>]*id="page-'+[regex]::Escape($id)+'"'){ $start=$i; $depth=1 }
    } else {
      $opens=([regex]::Matches($lines[$i],'\<div[\s>]')).Count
      $closes=([regex]::Matches($lines[$i],'\</div>')).Count
      $depth += $opens - $closes
      if($depth -le 0){ $end=$i; break }
    }
  }
  return @($start,$end)
}

function Rewrite-Icons($html) {
  # FontAwesome (prototype) is already loaded by the layout; keep fas classes verbatim.
  return $html
}

$log=@()
foreach($id in $map.Keys){
  $meta=$map[$id]; $view=$meta[0]; $title=$meta[1]; $sub=$meta[2]
  $r=Get-PageRange $id
  if($r[0] -lt 0 -or $r[1] -lt 0){ throw "range failed for $id" }
  # inner = lines after the wrapper div (keep indentation, drop wrapper line)
  $inner = ($lines[($r[0]+1)..($r[1]-1)]) -join "`n"
  # rewrite onclick navigateTo -> real links
  foreach($t in $navTarget.Keys){
    $inner = $inner -replace 'onclick="navigateTo\(''?'+[regex]::Escape($t)+''?\)"',
                          "href=`"{{ route('$($navTarget[$t])') }}`""
  }
  $sec = Join-Path $secDir "_$id`_content.blade.php"
  Set-Content -LiteralPath $sec -Value ("{{-- $title :: $sub --}}`n"+$inner) -Encoding UTF8

  # view wrapper
  $viewPath = Join-Path $root (Join-Path $base "$view.blade.php")
  $dir = Split-Path $viewPath
  if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir | Out-Null }
  $blade = @"
@extends('survey_module.layouts.prototype')

@php
  `$PageTitle = '$title';
  `$PageSubtitle = '$sub';
@endphp

@section('page-content')
@include('survey_module.partials.sections._${id}_content')
@endsection
"@
  Set-Content -LiteralPath $viewPath -Value $blade -Encoding UTF8
  $log += ("{0,-28} -> {1,-30} [{2} bytes section]" -f $id,$view,(Get-Item $sec).Length)
}
$log | ForEach-Object { Write-Output $_ }