<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gaps found while building the Survey module UI.
 *
 *  - survey_examinations had no record of WHO passed or returned an item; only
 *    updated_by hinted at it.
 *  - survey_file_movements could not say who carried the file, and linked to a
 *    GKN parcel only by string match on file_ref.
 *  - survey_op_records had nowhere for a permit-level remark (step notes live on
 *    survey_op_workflow_steps).
 *  - survey_lpkn had no coordinates, so the GIS layer could only ever show a
 *    total, never a mapped count.
 */
return new class extends Migration
{
    private const CONN = 'sqlsrv';

    public function up(): void
    {
        $s = Schema::connection(self::CONN);

        $s->table('survey_examinations', function (Blueprint $t) {
            $t->unsignedBigInteger('reviewed_by')->nullable()->after('reviewed_at');
        });

        $s->table('survey_file_movements', function (Blueprint $t) {
            $t->string('carried_by', 255)->nullable()->after('purpose');
            $t->unsignedBigInteger('survey_gkn_record_id')->nullable()->after('file_ref');
            $t->foreign('survey_gkn_record_id')->references('id')->on('survey_gkn_records');
        });

        $s->table('survey_op_records', function (Blueprint $t) {
            $t->text('remarks')->nullable()->after('status');
        });

        $s->table('survey_lpkn', function (Blueprint $t) {
            $t->string('coordinates', 255)->nullable()->after('land_use');
        });
    }

    public function down(): void
    {
        $s = Schema::connection(self::CONN);

        $s->table('survey_examinations', fn (Blueprint $t) => $t->dropColumn('reviewed_by'));

        $s->table('survey_file_movements', function (Blueprint $t) {
            $t->dropForeign(['survey_gkn_record_id']);
            $t->dropColumn(['carried_by', 'survey_gkn_record_id']);
        });

        $s->table('survey_op_records', fn (Blueprint $t) => $t->dropColumn('remarks'));
        $s->table('survey_lpkn', fn (Blueprint $t) => $t->dropColumn('coordinates'));
    }
};
