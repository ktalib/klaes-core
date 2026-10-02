$ErrorActionPreference = "Stop"
$root = "C:\xampp\htdocs\klas"
$src  = Join-Path $root "docs\prototypes\Survey-Module.html"
$base = Join-Path $root "resources\views\survey_module"
$sec  = Join-Path $base "partials\sections"
if (-not (Test-Path $sec)) { New-Item -ItemType Directory -Path $sec | Out-Null }

$lines = [System.IO.File]::ReadAllLines($src)

# page-id -> (view dotted name, title, subtitle)
$map = [ordered]@{
  'dashboard'              = @('survey_module.dashboard',                    'Dashboard',                 'Overview')
  'compensation-dashboard' = @('survey_module.compensation.dashboard',       'Compensation Dashboard',    'Monitoring & valuation')
  'projects'               = @('survey_module.compensation.projects',        'Project Management',        'Compensation projects')
  'project-register'       = @('survey_module.compensation.project_register','Create Project Register',   'Register a compensation project')
  'compensation-cases'     = @('survey_module.compensation.cases',           'Compensation Cases',        'All compensation cases')
  'compensation-register'  = @('survey_module.compensation.case_register',   'Register Compensation Case','New compensation case')
  'compensation-beneficiaries' = @('survey_module.compensation.beneficiaries','Beneficiaries',           'Case beneficiaries')
  'compensation-trees'     = @('survey_module.compensation.trees',           'Economic Trees',            'Tree census & valuation')
  'compensation-calculator'= @('survey_module.compensation.calculator',      'Compensation Calculator',   'Rates & entitlement')
  'compensation-land'      = @('survey_module.compensation.land',            'Land Allocation',           'Land-for-land allocation')
  'compensation-op'        = @('survey_module.compensation.op',              'Occupancy Permit (OP)',     'OP workflow queue')
  'compensation-reports'   = @('survey_module.compensation.reports',         'Compensation Reports',      'Compensation reporting')
  'gkn-dashboard'          = @('survey_module.gkn.dashboard',                'GKN Dashboard',             'Government lands index')
  'gkn-lands'              = @('survey_module.gkn.lands',                    'GKN Government Lands',      'Indexed parcels')
  'gkn-register'           = @('survey_module.gkn.register',                 'Register GKN',              'New GKN record')
  'gkn-tracking'           = @('survey_module.gkn.tracking',                 'GKN File Tracking',         'Track GKN files')
  'gkn-reports'            = @('survey_module.gkn.reports',                  'GKN Reports',               'GKN reporting')
  'misc-kn'                = @('survey_module.records.misc',                 'Misc KN',                   'Miscellaneous records')
  'lpkn'                   = @('survey_module.records.lpkn',                 'LPKN',                      'LPKN records')
  'examination'            = @('survey_module.workflow.examination',         'Examination',               'Examination workflow')
  'occupancy'              = @('survey_module.workflow.occupancy',           'Occupancy Workflow',        'OP workflow')
  'gis'                    = @('survey_module.tools.gis',                    'GIS',                       'KANGIS integration')
  'farm-plot-allocation'   = @('survey_module.tools.plot_allocation',        'Farm Plot Allocation',      '50:50 plot registers')
  'reports'                = @('survey_module.reports',                      'Reports',                   'Survey Department reporting')
}

# navigateTo target -> route name (for onclick rewrite)
$routeOf = @{
  'dashboard'='survey-module.dashboard'; 'compensation-dashboard'='survey-module.compensation.dashboard';
  'projects'='survey-module.compensation.projects'; 'project-register'='survey-module.compensation.project-register';
  'compensation-cases'='survey-module.compensation.cases'; 'compensation-register'='survey-module.compensation.case-register';
  'compensation-beneficiaries'='survey-module.compensation.beneficiaries'; 'compensation-trees'='survey-module.compensation.trees';
  'compensation-calculator'='survey-module.compensation.calculator'; 'compensation-land'='survey-module.compensation.land';
  'compensation-op'='survey-module.compensation.op'; 'compensation-reports'='survey-module.compensation.reports';
  'gkn-dashboard'='survey-module.gkn.dashboard'; 'gkn-lands'='survey-module.gkn.lands';
  'gkn-register'='survey-module.gkn.register'; 'gkn-tracking'='survey-module.gkn.tracking';
  'gkn-reports'='survey-module.gkn.reports'; 'misc-kn'='survey-module.records.misc'; 'lpkn'='survey-module.records.lpkn';
  'examination'='survey-module.workflow.examination'; 'occupancy'='survey-module.workflow.occupancy';
  'gis'='survey-module.tools.gis'; 'farm-plot-allocation'='survey-module.tools.plot-allocation';
  'reports'='survey-module.reports'
}

function Find-Page([string]$id) {
  $start = -1; $start = -1
  for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match 'id="page-" + [regex]::Escape($id) + '"') { $start = $i; break }
  }
  return $start
}
