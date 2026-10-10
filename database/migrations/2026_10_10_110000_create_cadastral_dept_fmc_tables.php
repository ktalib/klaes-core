<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastral Department workflow, step 7: the File Movement Card (FMC).
 *
 * Run with --path only:
 *   php artisan migrate --path=database/migrations/2026_10_10_110000_create_cadastral_dept_fmc_tables.php
 * (--pretend executes on sqlsrv; never use it.)
 *
 * Additive and guarded. The FMC header is a snapshot of the file's details
 * taken at commissioning. The card's log has two parts: the workflow's own
 * movements (cadastral_case_movements, already recorded) and the QR log-ins /
 * log-outs units make with the card, which go in cadastral_fmc_logs. Log rows
 * are append-only. cadastral_cases gets plot / district / LGA as separate
 * columns so they can be shown as Plot No., District, LGA, Location.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        if ($s->hasTable('cadastral_cases')) {
            foreach (['plot_no' => 50, 'district' => 150, 'lga' => 100] as $col => $len) {
                if (! $s->hasColumn('cadastral_cases', $col)) {
                    $s->table('cadastral_cases', fn (Blueprint $t) => $t->string($col, $len)->nullable());
                }
            }
        }

        if (! $s->hasTable('cadastral_fmcs')) {
            $s->create('cadastral_fmcs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cadastral_case_id');
                $t->string('fmc_no', 30);
                $t->string('qr_token', 64);
                $t->string('file_number', 100);
                $t->string('file_title', 255)->nullable();
                $t->string('allottee_name', 255)->nullable();
                $t->string('plot_no', 50)->nullable();
                $t->string('district', 150)->nullable();
                $t->string('lga', 100)->nullable();
                $t->string('location', 255)->nullable();
                $t->string('block_no', 50)->nullable();
                $t->string('layout_no', 50)->nullable();
                $t->dateTime('commissioned_at');
                $t->unsignedBigInteger('commissioned_by')->nullable();
                $t->unsignedInteger('print_count')->default(0);
                $t->timestamps();

                $t->index('cadastral_case_id', 'cad_fmc_case_idx');
                $t->index('qr_token', 'cad_fmc_token_idx');
                $t->index('file_number', 'cad_fmc_fileno_idx');
            });
        }

        if (! $s->hasTable('cadastral_fmc_logs')) {
            $s->create('cadastral_fmc_logs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cadastral_fmc_id');
                $t->string('direction', 3);                 // in | out
                $t->string('unit_key', 40)->nullable();     // desk key from config, or null for Other
                $t->string('unit_name', 150);               // label shown on the card
                $t->string('reason', 500)->nullable();
                $t->dateTime('logged_at');
                $t->unsignedBigInteger('logged_by')->nullable();
                $t->timestamps();

                $t->index('cadastral_fmc_id', 'cad_fmc_log_fmc_idx');
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: no backups here, so a rollback must not drop card history.
    }
};
