<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 | MySQL `settings.value` was varchar(191): the MLPP-SG portal's "How it works" steps (about 480 characters as
 | JSON) and its flash banners (CR 11112026-2, item 11) could never be saved, so Configurable Entries > Surveyor
 | Portal failed on every save. Widened to TEXT; no data changes. Backup of the table before this:
 | storage/app/backups/klaes_settings_before_widen_20261111_233200.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('mysql')->statement('ALTER TABLE `settings` MODIFY `value` TEXT NOT NULL');
    }

    public function down(): void
    {
        // Only safe while every value still fits in 191 characters.
        DB::connection('mysql')->statement('ALTER TABLE `settings` MODIFY `value` VARCHAR(191) NOT NULL');
    }
};
