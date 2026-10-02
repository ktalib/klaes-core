<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalise file_indexings.related_fileno to a JSON array on every row.
 *
 * Most rows already store a JSON array (e.g. ["RES-1992-100"]), written by the Merger/
 * Subdivision/Separation/Extension commissioning branches. A handful of legacy rows (the audit
 * found ~18, mostly Change-of-Purpose files) store a bare string instead, which consumers that
 * json_decode() the column parse fragilely. This converts any non-JSON, non-empty value into a
 * single- or multi-element JSON array so the format is uniform.
 *
 * Values already starting with '[' are left untouched. Comma-separated legacy values are split
 * into multiple elements; everything else becomes a single-element array.
 *
 * Idempotent: re-running skips rows that are already JSON. No down() — format is not reverted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasColumn('file_indexings', 'related_fileno')) {
            return;
        }

        $conn = DB::connection('sqlsrv');

        $conn->table('file_indexings')
            ->select('id', 'related_fileno')
            ->whereNotNull('related_fileno')
            ->where('related_fileno', '<>', '')
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($conn) {
                foreach ($rows as $row) {
                    $raw = trim((string) $row->related_fileno);
                    if ($raw === '' || $raw[0] === '[') {
                        continue; // already JSON (or empty) — leave as-is
                    }

                    // Split comma-separated legacy values; keep single values whole (do NOT split
                    // on '&' etc., which appear inside individual legacy file numbers).
                    $parts = array_values(array_filter(array_map('trim', explode(',', $raw)), fn($v) => $v !== ''));
                    if (empty($parts)) {
                        continue;
                    }

                    $conn->table('file_indexings')
                        ->where('id', $row->id)
                        ->update(['related_fileno' => json_encode($parts)]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally irreversible: normalisation is not reverted.
    }
};
