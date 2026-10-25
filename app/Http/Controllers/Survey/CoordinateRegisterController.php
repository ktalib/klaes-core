<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Examination/Verification > Coordinate Register (docs/KLAES-CORE Survey Module.md
 * 5.6, transcript: "the register of all these coordinates"): every coordinate the
 * module holds, in one read-only list.
 *
 *   I to S       survey_its_coordinates (List of Coordinates)
 *   Beacon       survey_beacon_register lines with X / Y
 *   Control pt   survey_control_point_register lines with X / Y
 *   LPKN layout  survey_lpkn_coordinates (the older layout screens)
 *
 * Reads only. Northing is Y, Easting is X throughout.
 */
class CoordinateRegisterController extends Controller
{
    public const SOURCES = [
        'its'     => 'I to S list',
        'beacon'  => 'Beacon Register',
        'control' => 'Control Points',
        'lpkn'    => 'LPKN layout',
    ];

    public function index(Request $r)
    {
        $q = $this->filtered($r);

        $counts = DB::connection('sqlsrv')->query()->fromSub($this->union(), 'c')
            ->selectRaw('source, COUNT(*) n')->groupBy('source')->pluck('n', 'source');

        return view('survey_module.workflow.coordinate_register', [
            'points' => $q->orderByDesc('recorded_at')->orderBy('point_id')->paginate(25)->withQueryString(),
            'counts' => $counts,
        ]);
    }

    /** The current selection as CSV, for GIS. */
    public function export(Request $r): StreamedResponse
    {
        $q = $this->filtered($r)->orderBy('source')->orderBy('point_id');

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Source', 'Point', 'Northing (Y)', 'Easting (X)', 'Elevation', 'File No.', 'Reference', 'Recorded']);
            foreach ($q->cursor() as $p) {
                fputcsv($out, [self::SOURCES[$p->source] ?? $p->source, $p->point_id, $p->northing, $p->easting, $p->elevation,
                    $p->file_number, $p->reference, $p->recorded_at]);
            }
            fclose($out);
        }, 'coordinate-register-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtered(Request $r)
    {
        $q = DB::connection('sqlsrv')->query()->fromSub($this->union(), 'c');

        if (array_key_exists($s = $r->query('source'), self::SOURCES)) $q->where('source', $s);
        if ($term = trim((string) $r->query('q', ''))) {
            $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';
            $q->where(fn ($w) => $w->where('point_id', 'like', $like)->orWhere('file_number', 'like', $like)->orWhere('reference', 'like', $like));
        }

        return $q;
    }

    /** One row per point: source, point_id, northing, easting, elevation, file_number, reference, recorded_at, link. */
    private function union()
    {
        $db = DB::connection('sqlsrv');

        $its = $db->table('survey_its_coordinates')->whereNull('deleted_at')->selectRaw(
            "'its' AS source, beacon_id AS point_id, CAST(northing AS decimal(18,4)) AS northing, CAST(easting AS decimal(18,4)) AS easting,
             CAST(elevation AS decimal(18,4)) AS elevation, file_number, its_number AS reference, created_at AS recorded_at,
             CASE WHEN survey_its_id IS NOT NULL THEN CONCAT('survey-', survey_its_id) ELSE CONCAT('cadastral-', cadastral_survey_job_id) END AS link");

        $beacon = $db->table('survey_beacon_register')->whereNull('deleted_at')->whereNotNull('coord_x')->whereNotNull('coord_y')->selectRaw(
            "'beacon' AS source, beacon_from AS point_id, CAST(coord_y AS decimal(18,4)) AS northing, CAST(coord_x AS decimal(18,4)) AS easting,
             CAST(NULL AS decimal(18,4)) AS elevation, file_number, CONCAT('Line ', id, CASE WHEN beacon_to IS NOT NULL THEN CONCAT(' (', beacon_from, ' to ', beacon_to, ')') ELSE '' END) AS reference,
             CAST(issue_date AS datetime) AS recorded_at, CAST(id AS nvarchar(40)) AS link");

        $control = $db->table('survey_control_point_register')->whereNull('deleted_at')->whereNotNull('coord_x')->whereNotNull('coord_y')->selectRaw(
            "'control' AS source, beacon_from AS point_id, CAST(coord_y AS decimal(18,4)) AS northing, CAST(coord_x AS decimal(18,4)) AS easting,
             CAST(NULL AS decimal(18,4)) AS elevation, file_number, CONCAT('Line ', id, CASE WHEN beacon_to IS NOT NULL THEN CONCAT(' (', beacon_from, ' to ', beacon_to, ')') ELSE '' END) AS reference,
             CAST(issue_date AS datetime) AS recorded_at, CAST(id AS nvarchar(40)) AS link");

        $lpkn = $db->table('survey_lpkn_coordinates as c')->join('survey_lpkn as l', 'l.id', '=', 'c.survey_lpkn_id')
            ->whereNull('c.deleted_at')->whereNull('l.deleted_at')->whereNotNull('c.northing')->whereNotNull('c.easting')->selectRaw(
            "'lpkn' AS source, c.beacon_id AS point_id, CAST(c.northing AS decimal(18,4)) AS northing, CAST(c.easting AS decimal(18,4)) AS easting,
             CAST(c.elevation AS decimal(18,4)) AS elevation, l.lpkn_number AS file_number, l.layout_name AS reference, c.created_at AS recorded_at,
             CAST(l.id AS nvarchar(40)) AS link");

        return $its->unionAll($beacon)->unionAll($control)->unionAll($lpkn);
    }
}
