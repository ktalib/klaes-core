<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add successor_file_no to decommissioned_files.
 *
 * Lineage is currently stored only new -> old (the child's file_indexings.related_fileno points
 * back to its parent). There is no old -> successor pointer, so from a decommissioned/superseded
 * file you cannot directly find the file that replaced it. This column stores that forward link,
 * populated at decommission time (Merger/Subdivision/Separation/Extension via
 * PlotWorkflowService::decommissionFiles(), and Change of Purpose via the MLS engine). Legal
 * Search and the dependent modules use it to redirect/annotate a superseded file to its successor.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('decommissioned_files')
            && !Schema::connection('sqlsrv')->hasColumn('decommissioned_files', 'successor_file_no')) {
            Schema::connection('sqlsrv')->table('decommissioned_files', function (Blueprint $table) {
                $table->string('successor_file_no', 255)->nullable()->after('decommissioning_reason');
                $table->index('successor_file_no');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('decommissioned_files', 'successor_file_no')) {
            Schema::connection('sqlsrv')->table('decommissioned_files', function (Blueprint $table) {
                $table->dropIndex(['successor_file_no']);
                $table->dropColumn('successor_file_no');
            });
        }
    }
};
