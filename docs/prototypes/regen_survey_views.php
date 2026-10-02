<?php
/**
 * Regenerate the 24 survey-module page views from the prototype's own
 * page headings, so $PageTitle / $PageDescription (read by admin.header)
 * carry the exact wording the prototype showed.
 */

$proto = file_get_contents('docs/prototypes/Survey-Module.html');

function protoPage(string $proto, string $id): string
{
    $lines = preg_split("/\r?\n/", $proto);
    $s = -1; $d = 0;
    for ($i = 0; $i < count($lines); $i++) {
        if ($s < 0) {
            if (preg_match('/<div class="page[^"]*"[^>]*id="page-' . preg_quote($id, '/') . '"/', $lines[$i])) {
                $s = $i; $d = 1;
            }
        } else {
            $d += substr_count($lines[$i], '<div') - substr_count($lines[$i], '</div>');
            if ($d <= 0) return implode("\n", array_slice($lines, $s + 1, $i - $s - 1));
        }
    }
    return '';
}

// page id => [view path, section partial]
$map = [
    'dashboard'                  => ['dashboard',                     '_page-dashboard'],
    'compensation-dashboard'     => ['compensation/dashboard',        '_page-compensation-dashboard'],
    'projects'                   => ['compensation/projects',         '_page-projects'],
    'project-register'           => ['compensation/project_register', '_page-project-register'],
    'compensation-cases'         => ['compensation/cases',            '_page-compensation-cases'],
    'compensation-register'      => ['compensation/case_register',    '_page-compensation-register'],
    'compensation-beneficiaries' => ['compensation/beneficiaries',    '_page-compensation-beneficiaries'],
    'compensation-trees'         => ['compensation/trees',            '_page-compensation-trees'],
    'compensation-calculator'    => ['compensation/calculator',       '_page-compensation-calculator'],
    'compensation-land'          => ['compensation/land',             '_page-compensation-land'],
    'compensation-op'            => ['compensation/op',               '_page-compensation-op'],
    'compensation-reports'       => ['compensation/reports',          '_page-compensation-reports'],
    'gkn-dashboard'              => ['gkn/dashboard',                 '_page-gkn-dashboard'],
    'gkn-lands'                  => ['gkn/lands',                     '_page-gkn-lands'],
    'gkn-register'               => ['gkn/register',                  '_page-gkn-register'],
    'gkn-tracking'               => ['gkn/tracking',                  '_page-gkn-tracking'],
    'gkn-reports'                => ['gkn/reports',                   '_page-gkn-reports'],
    'misc-kn'                    => ['records/misc',                  '_page-misc-kn'],
    'lpkn'                       => ['records/lpkn',                  '_page-lpkn'],
    'examination'                => ['workflow/examination',          '_page-examination'],
    'occupancy'                  => ['workflow/occupancy',            '_page-occupancy'],
    'gis'                        => ['tools/gis',                     '_page-gis'],
    'farm-plot-allocation'       => ['tools/plot_allocation',         '_page-farm-plot-allocation'],
    'reports'                    => ['reports',                       '_page-reports'],
];

$base = 'resources/views/survey_module/';
$q = fn(string $s): string => "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $s) . "'";

foreach ($map as $pid => [$view, $section]) {
    $html = protoPage($proto, $pid);

    preg_match('/<h2>([^<]*)<\/h2>/', $html, $t);
    preg_match('/<h2>[^<]*<\/h2>\s*\n\s*<p>([^<]*)<\/p>/', $html, $p);

    $title = trim($t[1] ?? '');
    $desc  = trim(html_entity_decode($p[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $title = trim(html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    // The prototype dashboard had no in-page heading; it used the global bar.
    if ($pid === 'dashboard') { $title = 'Dashboard'; $desc = 'Survey Department overview'; }

    if ($title === '') { printf("  !! no heading found for %s — skipped\n", $pid); continue; }

    $blade = "@extends('survey_module.layouts.klaes')\n\n"
           . "@section('page-title', " . $q($title . ' — KLAES') . ")\n\n"
           . "@php\n"
           . "  \$PageTitle = " . $q($title) . ";\n"
           . "  \$PageDescription = " . $q($desc) . ";\n"
           . "@endphp\n\n"
           . "@section('survey-content')\n"
           . "  @include('survey_module.partials.sections." . $section . "')\n"
           . "@endsection\n";

    $path = $base . $view . '.blade.php';
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0777, true);
    file_put_contents($path, $blade);

    printf("  %-30s %-28s %s\n", $view, $title, mb_strimwidth($desc, 0, 52, '...'));
}
