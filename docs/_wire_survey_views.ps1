$ErrorActionPreference = "Stop"
$root = "C:\xampp\htdocs\klas\resources\views\survey_module"

# rows: id|viewPathNoExt|pageTitle|pageSubtitle
$rows = @(
  'dashboard|dashboard|Survey Dashboard|Department overview',
  'compensation-dashboard|compensation/dashboard|Compensation Dashboard|Monitoring & valuation overview',
  'projects|compensation/projects|Project Management|Compensation projects register',
  'project-register|compensation/project_register|Project Register|Create / edit compensation project',
  'compensation-cases|compensation/cases|Compensation Cases|Case register & workflow',
  'compensation-register|compensation/case_register|Case Register|Register a compensation case',
  'compensation-beneficiaries|compensation/beneficiaries|Beneficiaries|Case beneficiaries register',
  'compensation-trees|compensation/trees|Economic Trees|Tree census & valuation',
  'compensation-calculator|compensation/calculator|Compensation Calculator|Rate & entitlement calculator',
  'compensation-land|compensation/land|Land Allocation|Land-for-land allocation scheme',
  'compensation-op|compensation/op|OP Generation|Occupancy Permit generation workflow',
  'compensation-reports|compensation/reports|Compensation Reports|Compensation reporting',
  'gkn-dashboard|gkn/dashboard|GKN Dashboard|Government lands index overview',
  'gkn-lands|gkn/lands|Government Lands|GKN lands register',
  'gkn-register|gkn/register|Register GKN|Register a government land file',
  'gkn-tracking|gkn/tracking|File Tracking|GKN file tracking',
  'gkn-reports|gkn/reports|GKN Reports|GKN reporting',
  'misc-kn|records/misc|Misc KN|Miscellaneous KN records',
  'lpkn|records/lpkn|LPKN|LPKN records',
  'examination|workflow/examination|Examination|Examination workflow',
  'occupancy|workflow/occupancy|Occupancy Permit Workflow|Occupancy permit workflow',
  'gis|tools/gis|GIS|GIS toolkit',
  'farm-plot-allocation|tools/plot_allocation|Farm Plot Allocation|Plot allocation registers',
  'reports|reports|Reports|Cross-module reports'
)

foreach ($r in $rows) {
  $p = $r.Split('|')
  $section = '_page-' + $p[0]
  $viewRel = $p[1]
  $title   = $p[2]
  $sub     = $p[3]

  $content = @(
    "@extends('survey_module.layouts.prototype')",
    '',
    '@php',
    "  `$PageTitle = '" + $title + "';",
    "  `$PageSubtitle = '" + $sub + "';",
    '@endphp',
    '',
    "@section('content')",
    "  @include('survey_module.partials.sections." + $section + "')",
    '@endsection',
    ''
  ) -join "`n"

  $dir  = Join-Path $root (Split-Path $viewRel)
  if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
  $file = Join-Path $root ($viewRel + '.blade.php')
  Set-Content -LiteralPath $file -Value $content -Encoding UTF8
  '{0,-30} -> {1}  ({2} B)' -f $p[0], $viewRel, (Get-Item $file).Length
}
"`nDONE - 24 views wired"