<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phone-change verification for LAAS applicants.
 *
 * An applicant's phone number is not ordinary profile data: it is the address
 * every workflow SMS is delivered to, and half of their sign-in credential. A
 * mistyped digit would silently cut them off from a statutory process they
 * cannot otherwise track. So a change is staged in `pending_phone` and only
 * applied once a code sent TO THE NEW NUMBER is entered — which is the only
 * thing that actually proves the applicant can receive messages there.
 *
 * Column names follow the convention already used for staff signature OTPs in
 * App\Services\LandOfficerSignatureService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('laas_applicants', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('laas_applicants', 'pending_phone')) {
                $table->string('pending_phone', 30)->nullable()->after('phone');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('laas_applicants', 'verification_code')) {
                $table->string('verification_code', 10)->nullable()->after('pending_phone');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('laas_applicants', 'verification_code_expires_at')) {
                $table->dateTime('verification_code_expires_at')->nullable()->after('verification_code');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('laas_applicants', 'verification_attempts')) {
                $table->unsignedSmallInteger('verification_attempts')->default(0)->after('verification_code_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('laas_applicants', function (Blueprint $table) {
            foreach ([
                'pending_phone',
                'verification_code',
                'verification_code_expires_at',
                'verification_attempts',
            ] as $column) {
                if (Schema::connection('sqlsrv')->hasColumn('laas_applicants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
