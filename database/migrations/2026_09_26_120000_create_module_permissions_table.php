<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Action-level permissions, per user, per module.
 *
 * Before this table the app had no action permissions at all: users.assign_role answered
 * "may this user see this module", and nothing anywhere answered "may they create, edit,
 * delete or print in it". users.user_actions looked like the answer but is written and
 * displayed without ever being read — and only 1 of 597 rows has a value.
 *
 * Shape notes:
 *
 *   module_name, not module_id. The grant in users.assign_role is a NAME, and 22 granted
 *   names have no user_roles row to point a foreign key at — 8 of which the sidebar
 *   actively checks, for 38 users. A FK would make those users unrepresentable, so the
 *   name is the key, normalized through App\Support\Permissions\ModuleName.
 *
 *   One row per (user, module) with a column per action, rather than a row per grant.
 *   Worst case is 597 x 174 = ~104k rows instead of ~620k, and the user modal renders
 *   its whole matrix from one query.
 *
 *   can_view exists for completeness but is NOT the source of sidebar visibility — that
 *   stays on users.assign_role, so this table cannot take a module away from anyone who
 *   can see it today. See App\Support\Permissions\ModulePermissions.
 *
 * sqlsrv: the User model lives on that connection, not the default mysql one.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    private const TABLE = 'module_permissions';

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable(self::TABLE)) {
            return;
        }

        $schema->create(self::TABLE, function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('user_id');
            // Normalized module name. 190 fits the longest production name with room
            // to spare and keeps the unique index inside SQL Server's key size limit.
            $table->string('module_name', 190);

            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_print')->default(false);
            $table->boolean('can_export')->default(false);

            $table->timestamps();

            $table->unique(['user_id', 'module_name'], 'module_permissions_user_module_unique');
            $table->index('user_id', 'module_permissions_user_id_index');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists(self::TABLE);
    }
};
