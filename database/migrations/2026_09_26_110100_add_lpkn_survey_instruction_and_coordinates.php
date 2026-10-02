<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LPKN work the concept note specifies but the schema could not hold.
 *
 * The note asks the LPKN section to cover layout plan number issuance,
 * "Instructions to Survey (I to S)" generation, a List of Coordinates form,
 * field observation computation and a plan validation workflow. Only the
 * register existed, so this adds:
 *
 *  - issuance details for the layout plan number itself
 *  - the Instruction to Survey (its own number, date, recipient, issuing officer)
 *  - a validation workflow (status / by / at / notes)
 *  - survey_lpkn_coordinates: one row per beacon, which is the List of
 *    Coordinates and the place field observations are computed from
 */
return new class extends Migration
{
    private const CONN = 'sqlsrv';

    public function up(): void
    {
        $s = Schema::connection(self::CONN);

        $s->table('survey_lpkn', function (Blueprint $t) {
            // Layout plan number issuance
            $t->date('issued_at')->nullable()->after('record_date');
            $t->string('issued_by', 255)->nullable()->after('issued_at');

            // Instruction to Survey (I to S)
            $t->string('its_number', 50)->nullable()->after('issued_by');
            $t->date('its_issued_at')->nullable()->after('its_number');
            $t->string('its_recipient', 255)->nullable()->after('its_issued_at');
            $t->string('its_issued_by', 255)->nullable()->after('its_recipient');
            $t->text('its_instructions')->nullable()->after('its_issued_by');

            // Plan validation workflow
            $t->string('validation_status', 30)->nullable()->after('status');
            $t->string('validated_by', 255)->nullable()->after('validation_status');
            $t->timestamp('validated_at')->nullable()->after('validated_by');
            $t->text('validation_notes')->nullable()->after('validated_at');

            $t->index('its_number');
            $t->index('validation_status');
        });

        // List of Coordinates: one row per beacon on a layout.
        $s->create('survey_lpkn_coordinates', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('survey_lpkn_id');
            $t->unsignedInteger('sort_order')->default(0);
            $t->string('beacon_id', 50);
            // Survey coordinates are metres to 3dp; 15,3 leaves room for any grid.
            $t->decimal('northing', 15, 3)->nullable();
            $t->decimal('easting', 15, 3)->nullable();
            $t->decimal('elevation', 15, 3)->nullable();
            // Field observations, computed leg by leg around the traverse.
            $t->string('bearing', 30)->nullable();       // e.g. 123°45'30"
            $t->decimal('distance', 12, 3)->nullable();  // metres to the next beacon
            $t->string('remarks', 500)->nullable();

            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->foreign('survey_lpkn_id')->references('id')->on('survey_lpkn');
            $t->index(['survey_lpkn_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        $s = Schema::connection(self::CONN);

        $s->dropIfExists('survey_lpkn_coordinates');

        $s->table('survey_lpkn', function (Blueprint $t) {
            $t->dropIndex(['its_number']);
            $t->dropIndex(['validation_status']);
            $t->dropColumn([
                'issued_at', 'issued_by',
                'its_number', 'its_issued_at', 'its_recipient', 'its_issued_by', 'its_instructions',
                'validation_status', 'validated_by', 'validated_at', 'validation_notes',
            ]);
        });
    }
};
