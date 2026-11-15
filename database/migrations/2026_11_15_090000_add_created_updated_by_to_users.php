<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Users list: Created By / Date Created / Updated By / Date Updated (Kunle, 2026-11-15).
 *
 * users.updated_at moves on every sign-in (remember token, OTP stamps), so it cannot
 * say when an account was last edited. details_updated_at is set only when an admin
 * saves the account. Additive: existing accounts keep NULLs (their creator was never
 * recorded) and show N/A.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('users', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('users', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('users', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('users', 'details_updated_at')) {
                $table->dateTime('details_updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('users', function (Blueprint $table) {
            foreach (['created_by', 'updated_by', 'details_updated_at'] as $column) {
                if (Schema::connection('sqlsrv')->hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
