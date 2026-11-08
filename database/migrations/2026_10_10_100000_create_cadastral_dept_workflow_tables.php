<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastral Department workflow: cases, their movement history and task ticks.
 *
 * DRAFT -- NOT RUN. Waiting for the user's approval. Run with --path only:
 *   php artisan migrate --path=database/migrations/2026_10_10_100000_create_cadastral_dept_workflow_tables.php
 * (--pretend executes on sqlsrv; never use it.)
 *
 * Everything is additive and guarded by hasTable. History rows (movements,
 * tasks) are append-only: the code never updates or deletes them.
 *
 * Deliberately NOT here yet: FMC, correspondence file, Land 12, Yes/No form,
 * field jacket, Job No., I to S, and the bill. Each waits for its template and
 * fields (docs/plans/CADASTRAL_DEPT_WORKFLOW.md, open questions).
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        if (! $s->hasTable('cadastral_cases')) {
            $s->create('cadastral_cases', function (Blueprint $t) {
                $t->id();
                $t->string('case_ref', 30);
                $t->string('workflow', 20);                 // conversion | direct | verification
                $t->string('sub_type', 30)->nullable();     // direct: resettlement | direct_allocation
                $t->string('file_type', 20);                // config cadastral_dept.file_types
                $t->string('file_source', 20);              // config cadastral_dept.file_sources: where it came from
                $t->string('file_number', 100);             // from the global file number selector
                $t->unsignedBigInteger('file_indexing_id')->nullable(); // null when only commissioned
                $t->string('file_system', 20)->nullable();  // selector tab: MLS | KANGIS | NEWKANGIS | ...
                $t->string('tracking_id', 50)->nullable();
                $t->string('file_title', 255)->nullable();
                $t->string('location', 255)->nullable();    // district, LGA (never the plot)
                $t->string('correspondence_fileno', 100)->nullable(); // verification: the matched correspondence file
                $t->boolean('duplicate_flag')->default(false); // file came in before, or is on the duplicate register
                $t->string('duplicate_note', 500)->nullable();
                $t->string('current_step', 50);
                $t->string('current_desk', 40);
                $t->string('status', 20)->default('in_progress'); // in_progress | returned | completed
                $t->dateTime('received_at');
                $t->dateTime('sla_deadline')->nullable();
                $t->string('timer_status', 20)->default('on_time'); // on_time | reminder | warning | default | penalty
                $t->text('exception_reason')->nullable();
                $t->unsignedBigInteger('exception_approved_by')->nullable();
                $t->dateTime('exception_until')->nullable();
                $t->dateTime('defaulted_at')->nullable();
                $t->dateTime('penalty_applied_at')->nullable();
                $t->unsignedBigInteger('received_by')->nullable();
                $t->dateTime('closed_at')->nullable();
                $t->text('remarks')->nullable();
                $t->timestamps();

                $t->index('case_ref', 'cad_cases_ref_idx');
                $t->index('file_number', 'cad_cases_fileno_idx');
                $t->index(['current_desk', 'status'], 'cad_cases_desk_status_idx');
                $t->index('timer_status', 'cad_cases_timer_status_idx');
            });
        }

        $this->ensureCadastralCaseTimerColumns();

        if (! $s->hasTable('cadastral_case_movements')) {
            $s->create('cadastral_case_movements', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cadastral_case_id');
                $t->string('from_step', 50)->nullable();
                $t->string('from_desk', 40)->nullable();
                $t->string('to_step', 50)->nullable();
                $t->string('to_desk', 40)->nullable();
                $t->string('outcome', 30);                  // received | forward | recommended | ... | close
                $t->text('remarks')->nullable();
                $t->unsignedBigInteger('acted_by')->nullable();
                $t->dateTime('acted_at');
                $t->timestamps();

                $t->index('cadastral_case_id', 'cad_case_mov_case_idx');
            });
        }

        if (! $s->hasTable('cadastral_case_tasks')) {
            $s->create('cadastral_case_tasks', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cadastral_case_id');
                $t->string('step', 50);
                $t->string('task_key', 50);
                $t->text('note')->nullable();
                $t->unsignedBigInteger('done_by')->nullable();
                $t->dateTime('done_at');
                $t->timestamps();

                $t->index(['cadastral_case_id', 'step'], 'cad_case_task_step_idx');
            });
        }
    }

    protected function ensureCadastralCaseTimerColumns(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasTable('cadastral_cases')) {
            return;
        }

        $columns = [
            'sla_deadline' => ['type' => 'dateTime', 'nullable' => true],
            'timer_status' => ['type' => 'string', 'length' => 20, 'nullable' => false, 'default' => 'on_time'],
            'exception_reason' => ['type' => 'text', 'nullable' => true],
            'exception_approved_by' => ['type' => 'unsignedBigInteger', 'nullable' => true],
            'exception_until' => ['type' => 'dateTime', 'nullable' => true],
            'defaulted_at' => ['type' => 'dateTime', 'nullable' => true],
            'penalty_applied_at' => ['type' => 'dateTime', 'nullable' => true],
        ];

        foreach ($columns as $name => $column) {
            if ($schema->hasColumn('cadastral_cases', $name)) {
                continue;
            }

            $schema->table('cadastral_cases', function (Blueprint $table) use ($name, $column) {
                $type = $column['type'];
                $nullable = $column['nullable'] ?? false;
                $default = $column['default'] ?? null;

                $field = $table->{$type}($name, $column['length'] ?? null);

                if ($nullable) {
                    $field->nullable();
                }
                if ($default !== null) {
                    $field->default($default);
                }
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: production has no backups, so rolling back must
        // not drop case history. Drop by hand if ever really needed.
    }
};
