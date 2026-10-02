<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Street, district and LGA became multi-select, so their columns now hold
 * several names joined with " | ".
 *
 * nvarchar(255) was too tight: the longest district name is 56 characters, so
 * 255 held only about four selections before silently failing validation.
 * 500 comfortably holds eight.
 *
 * Only these three per prefix are widened. State stays single-valued, and
 * house/plot are short free text.
 *
 * Verified safe before choosing the delimiter: no district, street or LGA name
 * in the lookup tables contains "|", "," or ";" (178 districts do contain "/",
 * which is why that was not used). Raw ALTER rather than ->change() so this does
 * not depend on doctrine/dbal, and none of these columns carries an index.
 */
return new class extends Migration
{
    private const CONN = 'sqlsrv';

    /** table => address prefix */
    private const TARGETS = [
        'survey_projects'     => 'prop_',
        'survey_comp_cases'   => 'prop_',
        'survey_gkn_records'  => 'prop_',
        'survey_misc_kn'      => 'prop_',
        'survey_lpkn'         => 'prop_',
        'survey_beneficiaries' => 'addr_',
    ];

    private const MULTI = ['street', 'district', 'lga'];

    private function resize(int $length): void
    {
        $db = DB::connection(self::CONN);

        foreach (self::TARGETS as $table => $prefix) {
            foreach (self::MULTI as $suffix) {
                $column = $prefix . $suffix;

                // Skip anything that is not actually there, so a partial schema
                // cannot abort the whole migration.
                $exists = $db->selectOne(
                    'SELECT 1 AS present FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?',
                    [$table, $column]
                );
                if (!$exists) continue;

                $db->statement("ALTER TABLE [$table] ALTER COLUMN [$column] nvarchar($length) NULL");
            }
        }
    }

    public function up(): void
    {
        $this->resize(500);
    }

    public function down(): void
    {
        // Truncation risk is why this is not automatic in reverse: any row
        // holding more than 255 characters of selections would lose data. The
        // tables are empty at the time of writing, so this is safe now.
        $this->resize(255);
    }
};
