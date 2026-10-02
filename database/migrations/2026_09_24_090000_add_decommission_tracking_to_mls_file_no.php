<?php

use App\Support\DecommissionScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    /**
     * Carry the decommission flag on mls_file_no too.
     *
     * 2026_08_15_100000 made decommissioning a flag instead of a delete across the
     * live tables, but left mls_file_no out. flagDecommissioned() skips any table
     * without the column, silently — so the register agreed with the archive while
     * mls_file_no went on reporting retired files as live. It is the table the
     * subdivision batch picker reads `source` from, so a plot retired by a later
     * Change of Purpose still counted as an active Subdivision child there.
     *
     * Same canonical column set as the earlier migration, so every live table now
     * describes its own decommission state without joining decommissioned_files.
     *
     * NOTE: nothing filters listings on this column, and nothing should start to.
     * A decommissioned file must GO ON SHOWING in the view tables — flagged and
     * badged, not hidden. The flag is there to be displayed and to drive lineage,
     * not to suppress the row. That is the whole point of flagging over deleting.
     */
    private const TABLE = 'mls_file_no';

    /** column => SQL Server type/definition */
    private const COLUMNS = [
        'is_decommissioned'      => 'TINYINT NOT NULL DEFAULT 0',
        'decommissioned_at'      => 'DATETIME NULL',
        'decommissioned_by'      => 'NVARCHAR(255) NULL',
        'decommissioning_reason' => 'NVARCHAR(MAX) NULL',
        'successor_file_no'      => 'NVARCHAR(MAX) NULL',
    ];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        $conn   = DB::connection('sqlsrv');

        if (!$schema->hasTable(self::TABLE)) {
            return;
        }

        // Added only when absent, so this is safe to re-run and safe on a database
        // where the table was patched by hand.
        foreach (self::COLUMNS as $column => $definition) {
            if ($schema->hasColumn(self::TABLE, $column)) {
                continue;
            }

            $conn->statement('ALTER TABLE [' . self::TABLE . "] ADD [{$column}] {$definition}");
        }

        $index = 'ix_' . self::TABLE . '_is_decommissioned';
        if (!$this->indexExists(self::TABLE, $index)) {
            // Filtered on 1, as on the other live tables: decommissioned rows are the
            // rare minority, which keeps the index tiny while still serving
            // "show me the decommissioned ones".
            $conn->statement(
                "CREATE NONCLUSTERED INDEX [{$index}] ON [" . self::TABLE . '] ([is_decommissioned]) WHERE [is_decommissioned] = 1'
            );
        }

        $this->backfill();
    }

    /**
     * Every file already retired went through decommissioned_files, so the archive
     * is what the new columns are filled from. Title-status flags are not
     * decommissionings and are excluded — see DecommissionScope.
     */
    private function backfill(): void
    {
        $conn = DB::connection('sqlsrv');

        $archived = DecommissionScope::real(
            $conn->table('decommissioned_files')
                ->selectRaw('
                    MAX(id) AS id,
                    COALESCE(mls_file_no, file_no) AS file_no,
                    MAX(decommissioning_date) AS decommissioning_date,
                    MAX(decommissioned_by) AS decommissioned_by,
                    MAX(decommissioning_reason) AS decommissioning_reason,
                    MAX(successor_file_no) AS successor_file_no
                ')
                ->whereNotNull(DB::raw('COALESCE(mls_file_no, file_no)'))
                ->groupBy(DB::raw('COALESCE(mls_file_no, file_no)'))
        )->get();

        foreach ($archived as $row) {
            $fileNo = trim((string) $row->file_no);
            if ($fileNo === '') {
                continue;
            }

            $conn->table(self::TABLE)
                ->where('full_file_number', $fileNo)
                ->update([
                    'is_decommissioned'      => 1,
                    'decommissioned_at'      => $row->decommissioning_date,
                    'decommissioned_by'      => $row->decommissioned_by,
                    'decommissioning_reason' => $row->decommissioning_reason,
                    'successor_file_no'      => $row->successor_file_no,
                ]);
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        $conn   = DB::connection('sqlsrv');

        if (!$schema->hasTable(self::TABLE)) {
            return;
        }

        $index = 'ix_' . self::TABLE . '_is_decommissioned';
        if ($this->indexExists(self::TABLE, $index)) {
            $conn->statement("DROP INDEX [{$index}] ON [" . self::TABLE . ']');
        }

        foreach (array_keys(self::COLUMNS) as $column) {
            if (!$schema->hasColumn(self::TABLE, $column)) {
                continue;
            }

            // SQL Server names DEFAULT constraints implicitly; drop it before the column.
            $conn->statement(
                "DECLARE @df NVARCHAR(255);
                 SELECT @df = dc.name FROM sys.default_constraints dc
                   JOIN sys.columns c ON c.default_object_id = dc.object_id
                  WHERE dc.parent_object_id = OBJECT_ID('" . self::TABLE . "') AND c.name = '{$column}';
                 IF @df IS NOT NULL EXEC('ALTER TABLE [" . self::TABLE . "] DROP CONSTRAINT [' + @df + ']');
                 ALTER TABLE [" . self::TABLE . "] DROP COLUMN [{$column}]"
            );
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return (bool) DB::connection('sqlsrv')->selectOne(
            'SELECT 1 AS found FROM sys.indexes WHERE object_id = OBJECT_ID(?) AND name = ?',
            [$table, $index]
        );
    }
};
