<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LAAS Portal accounts.
 *
 * Applicants of the public Land Allocation Application System. Deliberately a
 * separate table from `users`: the portal sits outside admin auth on its own
 * `laas` guard, exactly as PHS members and Online-LS users do.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('laas_applicants')) {
            return;
        }

        Schema::connection('sqlsrv')->create('laas_applicants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('email', 150)->unique();
            $table->string('phone', 30);
            $table->string('password');
            $table->string('nin', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('status', 20)->default('active'); // active | suspended
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('phone');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('laas_applicants');
    }
};
