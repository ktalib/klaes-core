<?php

use App\Services\Edms\EdmsFileType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    /**
     * The EDMS file-type lookup table.
     *
     * The catalogue used to be a PHP const, so adding a type — the registry does
     * ask — meant a deploy. It lives here now, and App\Services\Edms\EdmsFileType
     * reads it (cached), keeping its const only as the seed written below and as
     * the fallback for a database this migration has not reached yet.
     *
     * One row per selectable end state, described by the three dropdowns that
     * pick it:
     *
     *   category  regular | parcel_update | title_status
     *   type      subdivision, merger, extension, regrant, litigation, …
     *   variant   old | new, or NULL
     *
     * Only Regrant and Resettlement split into Old and New. Every other type is
     * a single folder with a NULL variant.
     *
     * `code` is the value stored in file_indexings.edms_file_type (and the same
     * column on scannings / pagetypings), and `folder` is the path segment on
     * disk. Both are stable — a relabelled row must keep its code, or already
     * filed documents stop resolving.
     *
     * Re-runnable: the table is created only when absent, and the seed inserts
     * only the codes that are not there yet, so it never overwrites a row the
     * registry has edited.
     */
    private const TABLE = 'edms_file_types';

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasTable(self::TABLE)) {
            $schema->create(self::TABLE, function ($table) {
                $table->increments('id');
                // What the edms_file_type column stores — the canonical key.
                $table->string('code', 64)->unique();
                $table->string('category', 32);
                $table->string('category_label', 64);
                $table->string('type', 64);
                $table->string('type_label', 64);
                // NULL for a type that is a complete answer on its own (Regular).
                $table->string('variant', 32)->nullable();
                $table->string('variant_label', 64)->nullable();
                // The full display label, "Subdivision — Mother".
                $table->string('label', 128);
                // Path segment under the registry folder, e.g.
                // Parcel_Update/Subdivision/Mother.
                $table->string('folder', 191);
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                // Every dropdown reads the catalogue in this order.
                $table->index(['is_active', 'sort_order'], 'IX_edms_file_types_active_order');
                $table->index(['category', 'type'], 'IX_edms_file_types_category_type');
            });
        }

        $this->seed();

        EdmsFileType::flush();
    }

    /** Insert any seed row whose code is not already present. */
    private function seed(): void
    {
        $conn = DB::connection('sqlsrv');
        $now = now();

        $existing = $conn->table(self::TABLE)->pluck('code')->all();
        $existing = array_flip(array_map('strval', $existing));

        $rows = [];

        foreach (EdmsFileType::seedRows() as $row) {
            if (isset($existing[$row['code']])) {
                continue;
            }

            $rows[] = $row + ['created_at' => $now, 'updated_at' => $now];
        }

        if ($rows !== []) {
            $conn->table(self::TABLE)->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists(self::TABLE);

        EdmsFileType::flush();
    }
};
