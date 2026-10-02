<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provenance for the gender columns added on 2026-07-28.
 *
 * Gender is 0.004% populated on file_indexings and is about to be filled largely
 * by inference from the file title. A derived value that cannot be told apart
 * from a captured one makes every PRS gender chart uncheckable, so record where
 * each value came from:
 *
 *   captured      entered by a user on the indexing / commissioning form
 *   oss_sex       copied from oss_applications.sex (captured on the OSS form)
 *   pair          copied from the paired mls_file_no / file_indexings row
 *   honorific     inferred from Alhaji / Hajiya / Mallam / Mrs ... in the name
 *   organisation  name is a company or public body, so gender does not apply
 *   joint         two parties of differing gender named in one title
 *
 * Anything other than 'captured' is reversible:
 *   UPDATE t SET gender = NULL, gender_source = NULL WHERE gender_source <> 'captured'
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    private array $tables = ['file_indexings', 'mls_file_no', 'indexing_duplicates'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }

            if (Schema::connection('sqlsrv')->hasColumn($table, 'gender_source')) {
                continue;
            }

            Schema::connection('sqlsrv')->table($table, function (Blueprint $t) {
                $t->string('gender_source', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::connection('sqlsrv')->hasColumn($table, 'gender_source')) {
                continue;
            }

            Schema::connection('sqlsrv')->table($table, function (Blueprint $t) {
                $t->dropColumn(['gender_source']);
            });
        }
    }
};
