<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The applicant's email, captured (optionally) on the commissioning form beside
 * the phone number. It becomes the email on the LAAS Portal account that
 * commissioning opens; when left blank a placeholder is generated there and
 * nothing is stored here.
 */
return new class extends Migration
{
    private const TABLES = ['mls_file_no', 'fileNumber'];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        foreach (self::TABLES as $tableName) {
            if ($schema->hasColumn($tableName, 'email')) {
                continue;
            }

            $schema->table($tableName, function (Blueprint $table) {
                $table->string('email', 150)->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        foreach (self::TABLES as $tableName) {
            if (!$schema->hasColumn($tableName, 'email')) {
                continue;
            }

            $schema->table($tableName, function (Blueprint $table) {
                $table->dropColumn('email');
            });
        }
    }
};
