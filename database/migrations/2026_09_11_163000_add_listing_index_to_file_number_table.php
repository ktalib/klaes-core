<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'IX_fileNumber_listing_source';

    public function up(): void
    {
        $connection = DB::connection('sqlsrv');

        if (!Schema::connection('sqlsrv')->hasTable('fileNumber') || $this->indexExists()) {
            return;
        }

        // Supports the server-side /file-numbers list.  Its first phase filters
        // active MLS rows by source/type and groups them by MLS file number.
        $connection->statement(
            'CREATE NONCLUSTERED INDEX ' . self::INDEX . '
             ON dbo.fileNumber ([is_deleted], [SOURCE], [type], [mlsfNo], [id])
             INCLUDE ([created_at])'
        );
    }

    public function down(): void
    {
        if ($this->indexExists()) {
            DB::connection('sqlsrv')->statement('DROP INDEX ' . self::INDEX . ' ON dbo.fileNumber');
        }
    }

    private function indexExists(): bool
    {
        return DB::connection('sqlsrv')->table('sys.indexes')
            ->where('object_id', DB::raw("OBJECT_ID('fileNumber')"))
            ->where('name', self::INDEX)
            ->exists();
    }
};
