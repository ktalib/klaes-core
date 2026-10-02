<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    /**
     * Record which EDMS master folder a file's documents belong in.
     *
     * The EDMS trees gain a folder between the registry and the file number —
     * Regular, Merger/Children, Subdivision/Mother, Extension/Old,
     * Temporary_File, Change_of_Purpose/New and the rest (App\Services\Edms\
     * EdmsFileType is the catalogue). The path a document is stored at now
     * depends on this value, so it has to live beside `registry` on the same
     * three tables — and for the same reason: the scans and the typed pages
     * carry their own copy so a half-finished move still resolves.
     *
     *   file_indexings.edms_file_type  the file's classification (canonical)
     *   scannings.edms_file_type       the tree the original actually sits in
     *   pagetypings.edms_file_type     the tree the typed copy actually sits in
     *
     * NULL is the normal starting state and means "unclassified": those files
     * keep the old layout, directly under the registry folder, and nothing moves
     * until an operator picks a type. There is deliberately no default and no
     * backfill — guessing a file's nature from its number would put documents in
     * the wrong master folder, which is exactly what this feature exists to fix.
     *
     * NOTE: file_indexings.file_type already exists and holds something else
     * entirely (the applicant type — Individual / Corporate / Government), which
     * is why this column is named edms_file_type rather than reusing it.
     *
     * Re-runnable: each column is added only when absent.
     */
    private const TABLES = [
        'file_indexings',
        'scannings',
        'pagetypings',
    ];

    private const COLUMN = 'edms_file_type';

    /** Long enough for the longest key (change_of_purpose_old) with room to spare. */
    private const DEFINITION = 'NVARCHAR(64) NULL';

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        $conn = DB::connection('sqlsrv');

        foreach (self::TABLES as $table) {
            if (!$schema->hasTable($table)) {
                continue;
            }

            if ($schema->hasColumn($table, self::COLUMN)) {
                continue;
            }

            $conn->statement(sprintf(
                'ALTER TABLE [%s] ADD [%s] %s',
                $table,
                self::COLUMN,
                self::DEFINITION
            ));
        }

        // Every screen that lists a registry's files will now filter by this
        // column; without an index that is a scan of file_indexings (>130k rows).
        if ($schema->hasTable('file_indexings') && $schema->hasColumn('file_indexings', self::COLUMN)) {
            $exists = $conn->selectOne(
                "SELECT 1 AS found FROM sys.indexes WHERE name = 'IX_file_indexings_edms_file_type' AND object_id = OBJECT_ID('file_indexings')"
            );

            if (!$exists) {
                $conn->statement(
                    'CREATE INDEX [IX_file_indexings_edms_file_type] ON [file_indexings] ([edms_file_type])'
                );
            }
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        $conn = DB::connection('sqlsrv');

        $exists = $conn->selectOne(
            "SELECT 1 AS found FROM sys.indexes WHERE name = 'IX_file_indexings_edms_file_type' AND object_id = OBJECT_ID('file_indexings')"
        );

        if ($exists) {
            $conn->statement('DROP INDEX [IX_file_indexings_edms_file_type] ON [file_indexings]');
        }

        foreach (self::TABLES as $table) {
            if ($schema->hasTable($table) && $schema->hasColumn($table, self::COLUMN)) {
                $conn->statement(sprintf('ALTER TABLE [%s] DROP COLUMN [%s]', $table, self::COLUMN));
            }
        }
    }
};
