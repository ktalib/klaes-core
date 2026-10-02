<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Report on Application questionnaire (rebuild plan Phase 4, §3a, §6 item 1).
 *
 * One nullable column per blank on the official "Report on Application –
 * Cadastral Department" form, filled in on the report chain's Report step:
 *
 *   govt_item_no                Government Item No. GKN/…
 *   sltr_no, sit_no             SLTR/… and SIT/… when the file has them. Stored
 *                               because neither is reliably derivable: a receipt
 *                               only knows the number it arrived under, and
 *                               file_indexings has no SLTR column. Blank means
 *                               "derive from the receipt's source registry".
 *   q1_plan_sufficient          Q1 yes/no
 *   q2_ground_open              Q2 yes/no, and if No:
 *   q2_overlapping_title          the title or application lying over the land
 *   q3_beaconed                 Q3 yes/no; if Yes:
 *   q3_tracing_no                 (a) Tracing No.
 *   q3_deposition_plan_no         (b) Deposition Plan No.; if No:
 *   q3_unapproved_town_plan_no    (a) unapproved Town Plan No.
 *   q3_layout_no                  (b) Lay-Out No.
 *   q3_separate_survey            (c) separate survey required, yes/no
 *   q4_town_plan                Q4 yes/no; if Yes:
 *   q4_town_plan_no               (a) Town Plan No.
 *   q4_shape_agrees               (b) yes/no
 *   q4_purpose                    (c) Residential | Commercial | Industrial |
 *                                     Agricultural | the officer's words for other
 *   q4_area_for_purpose           (d) yes/no
 *   q5_previous_title           Q5 yes/no, and if Yes:
 *   q5_details                    the details
 *   q6_railway                  Q6 yes/no
 *   q7_trunk_road               Q7 yes/no
 *   area_applied_ha             Q8, hectares only
 *
 * YES/NO is nvarchar(3) holding 'Yes' / 'No' -- the same values the existing
 * survey_necessary column holds and its validator accepts, so the two read alike
 * and nothing translates between them. The print upper-cases it.
 *
 * AREA IS STORED ONCE, IN HECTARES. Acres are derived at print as ha x 2.47,
 * the factor the form itself prints, so the two figures on the paper always
 * agree. Area applied for is the applicant's figure, which can differ from the
 * charted one, so it is an officer-confirmed value; the form pre-fills it from
 * the linked plan description (or chart) through AreaCalculator when blank.
 *
 * ALL NULLABLE, NO DEFAULTS, NO INDEXES: an ALTER that only adds nullable
 * columns is a metadata change on SQL Server and does not rewrite the table.
 *
 * The code works before this runs: CadastralReport::applicationInstalled()
 * checks the columns once per request and, while they are missing, the
 * questionnaire panel reads "pending installation", the Report step is not
 * gated on it, and the official print fills only the derived blanks.
 *
 * EVERY COLUMN IS GUARDED BY hasColumn(), so a part-applied run can be re-run.
 * down() drops only these columns, and only those that exist.
 *
 * DEPLOY WITH THE PATH FLAG. A bare migrate would run 27 unrelated pending
 * migrations against production, and --pretend is NOT a dry run on sqlsrv (it
 * executes):
 *
 *   php artisan migrate --database=sqlsrv \
 *     --path=database/migrations/2026_10_02_100000_add_application_report_columns_to_cadastral_reports.php --force
 */
return new class extends Migration
{
    private const CONN  = 'sqlsrv';
    private const TABLE = 'cadastral_reports';

    /** column => definer */
    private function columns(): array
    {
        $yesNo = fn (string $c) => fn (Blueprint $t) => $t->string($c, 3)->nullable();
        $text  = fn (string $c, int $n) => fn (Blueprint $t) => $t->string($c, $n)->nullable();

        return [
            'govt_item_no'               => $text('govt_item_no', 100),
            'sltr_no'                    => $text('sltr_no', 100),
            'sit_no'                     => $text('sit_no', 100),
            'q1_plan_sufficient'         => $yesNo('q1_plan_sufficient'),
            'q2_ground_open'             => $yesNo('q2_ground_open'),
            'q2_overlapping_title'       => $text('q2_overlapping_title', 500),
            'q3_beaconed'                => $yesNo('q3_beaconed'),
            'q3_tracing_no'              => $text('q3_tracing_no', 100),
            'q3_deposition_plan_no'      => $text('q3_deposition_plan_no', 100),
            'q3_unapproved_town_plan_no' => $text('q3_unapproved_town_plan_no', 100),
            'q3_layout_no'               => $text('q3_layout_no', 100),
            'q3_separate_survey'         => $yesNo('q3_separate_survey'),
            'q4_town_plan'               => $yesNo('q4_town_plan'),
            'q4_town_plan_no'            => $text('q4_town_plan_no', 100),
            'q4_shape_agrees'            => $yesNo('q4_shape_agrees'),
            'q4_purpose'                 => $text('q4_purpose', 100),
            'q4_area_for_purpose'        => $yesNo('q4_area_for_purpose'),
            'q5_previous_title'          => $yesNo('q5_previous_title'),
            'q5_details'                 => $text('q5_details', 1000),
            'q6_railway'                 => $yesNo('q6_railway'),
            'q7_trunk_road'              => $yesNo('q7_trunk_road'),
            'area_applied_ha'            => fn (Blueprint $t) => $t->decimal('area_applied_ha', 18, 4)->nullable(),
        ];
    }

    public function up(): void
    {
        $s = Schema::connection(self::CONN);

        if (! $s->hasTable(self::TABLE)) {
            return;
        }

        foreach ($this->columns() as $column => $define) {
            if (! $s->hasColumn(self::TABLE, $column)) {
                $s->table(self::TABLE, fn (Blueprint $t) => $define($t));
            }
        }
    }

    public function down(): void
    {
        $s = Schema::connection(self::CONN);

        if (! $s->hasTable(self::TABLE)) {
            return;
        }

        foreach (array_keys($this->columns()) as $column) {
            if ($s->hasColumn(self::TABLE, $column)) {
                $s->table(self::TABLE, fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
