<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users.photo_face_* — the recorded verdict of the face check on the picture a user
 * actually has on file.
 *
 * The mandatory-photo gate (RequireProfilePhoto) used to ask one question: is there a
 * file behind the `profile` column? That lets a stock cartoon avatar satisfy it, which
 * is exactly what several hundred accounts carry — the picture is present, but it is
 * not a photograph of the person, so it identifies nobody on a file or a request.
 *
 * Face detection runs in the BROWSER (face-api.js; there is no PHP detector here), so
 * the verdict has to be persisted for the server to gate on it. It is written once per
 * picture, by js/profile-photo-self-check.js on the owner's next page load — and by the
 * shared profile card when a Super Admin opens someone's record.
 *
 * Columns:
 *   photo_face_status     'pass' | 'fail' | 'override'. NULL means not yet checked, and
 *                         is deliberately NOT a rejection: the gate stays open until a
 *                         picture has actually been judged, so a detector that will not
 *                         load can never lock the whole staff out.
 *   photo_face_reason     the detector's own words ("No human face detected"), shown to
 *                         the user so a rejection can be acted on rather than just felt.
 *   photo_face_checked_at when it was judged.
 *   photo_face_path       WHICH picture was judged — the stored `profile` value at the
 *                         time. A verdict never carries over to a different file, so a
 *                         row whose photo was replaced by any path that skipped
 *                         ProfilePhotoService is re-checked rather than trusted.
 *
 * 'override' is the escape hatch: a real photograph that the heuristic refuses would
 * otherwise lock a member of staff out of the entire system with no way back, since the
 * upload card runs the same detector on the same file. `profile:face-status --override`
 * marks it accepted for good.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasColumn('users', 'photo_face_status')) {
            $schema->table('users', function (Blueprint $table) {
                $table->string('photo_face_status', 12)->nullable();
            });
        }

        if (!$schema->hasColumn('users', 'photo_face_reason')) {
            $schema->table('users', function (Blueprint $table) {
                $table->string('photo_face_reason', 160)->nullable();
            });
        }

        if (!$schema->hasColumn('users', 'photo_face_checked_at')) {
            $schema->table('users', function (Blueprint $table) {
                $table->dateTime('photo_face_checked_at')->nullable();
            });
        }

        if (!$schema->hasColumn('users', 'photo_face_path')) {
            $schema->table('users', function (Blueprint $table) {
                $table->string('photo_face_path', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        foreach (['photo_face_status', 'photo_face_reason', 'photo_face_checked_at', 'photo_face_path'] as $column) {
            if ($schema->hasColumn('users', $column)) {
                $schema->table('users', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
