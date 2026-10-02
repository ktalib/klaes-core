<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';
    protected $table = 'related_file_number';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable($this->table)) {
            Schema::connection($this->connection)->create($this->table, function (Blueprint $table) {
                $table->id();
                $table->string('related_fileno', 500);
                $table->string('prop_id', 100)->nullable();
                $table->string('source_table', 50);
                $table->unsignedBigInteger('source_id');
                $table->string('file_number', 255)->nullable();
                $table->timestamps();

                $table->index('related_fileno');
                $table->index('prop_id');
                // NOT unique: one source row legitimately carries several related numbers
                // (a merger quotes every source file). The live table, built from
                // database/migrations/manual/create_related_file_number_table.sql, has no
                // unique index either -- RelatedFileNumberRegistrar is what keeps a given
                // (source, number) pair from being written twice.
                $table->index(['source_table', 'source_id']);
            });
        }

        $this->copyFromFileIndexings();
        $this->copyFromDeprecatedRecords();
        $this->copyFromFileNumber();
        $this->copyFromDcivFileNo();
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists($this->table);
    }

    private function copyFromFileIndexings(): void
    {
        DB::connection($this->connection)->statement("
            INSERT INTO [{$this->table}] (related_fileno, prop_id, source_table, source_id, file_number, created_at, updated_at)
            SELECT
                LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500)))) AS related_fileno,
                CAST(s.prop_id AS NVARCHAR(100))                       AS prop_id,
                'file_indexings'                                       AS source_table,
                s.id                                                   AS source_id,
                s.file_number                                          AS file_number,
                SYSUTCDATETIME(),
                SYSUTCDATETIME()
            FROM [file_indexings] s
            WHERE s.related_fileno IS NOT NULL
              AND LEN(LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500))))) > 0
              AND NOT EXISTS (
                  SELECT 1 FROM [{$this->table}] t
                  WHERE t.source_table = 'file_indexings' AND t.source_id = s.id
              )
        ");
    }

    private function copyFromDeprecatedRecords(): void
    {
        DB::connection($this->connection)->statement("
            INSERT INTO [{$this->table}] (related_fileno, prop_id, source_table, source_id, file_number, created_at, updated_at)
            SELECT
                LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500)))) AS related_fileno,
                CAST(s.prop_id AS NVARCHAR(100))                       AS prop_id,
                'deprecated_records'                                   AS source_table,
                s.id                                                   AS source_id,
                s.file_number                                          AS file_number,
                SYSUTCDATETIME(),
                SYSUTCDATETIME()
            FROM [deprecated_records] s
            WHERE s.related_fileno IS NOT NULL
              AND LEN(LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500))))) > 0
              AND NOT EXISTS (
                  SELECT 1 FROM [{$this->table}] t
                  WHERE t.source_table = 'deprecated_records' AND t.source_id = s.id
              )
        ");
    }

    private function copyFromFileNumber(): void
    {
        DB::connection($this->connection)->statement("
            INSERT INTO [{$this->table}] (related_fileno, prop_id, source_table, source_id, file_number, created_at, updated_at)
            SELECT
                LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500)))) AS related_fileno,
                NULL                                                   AS prop_id,
                'fileNumber'                                           AS source_table,
                s.id                                                   AS source_id,
                COALESCE(s.NewKANGISFileNo, s.kangisFileNo)            AS file_number,
                SYSUTCDATETIME(),
                SYSUTCDATETIME()
            FROM [fileNumber] s
            WHERE s.related_fileno IS NOT NULL
              AND LEN(LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500))))) > 0
              AND NOT EXISTS (
                  SELECT 1 FROM [{$this->table}] t
                  WHERE t.source_table = 'fileNumber' AND t.source_id = s.id
              )
        ");
    }

    private function copyFromDcivFileNo(): void
    {
        DB::connection($this->connection)->statement("
            INSERT INTO [{$this->table}] (related_fileno, prop_id, source_table, source_id, file_number, created_at, updated_at)
            SELECT
                LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500)))) AS related_fileno,
                NULL                                                   AS prop_id,
                'dciv_file_no'                                         AS source_table,
                s.id                                                   AS source_id,
                s.full_file_number                                     AS file_number,
                SYSUTCDATETIME(),
                SYSUTCDATETIME()
            FROM [dciv_file_no] s
            WHERE s.related_fileno IS NOT NULL
              AND LEN(LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500))))) > 0
              AND NOT EXISTS (
                  SELECT 1 FROM [{$this->table}] t
                  WHERE t.source_table = 'dciv_file_no' AND t.source_id = s.id
              )
        ");
    }
};
